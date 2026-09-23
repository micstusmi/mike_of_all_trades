<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';

const ALPHA_IMPORT_CORE = ['work_customers','work_properties','work_jobs'];

/** Inspect source schema and relationships. Never reads passwords, tokens or media files. */
function alpha_mike_inventory(PDO $source): array {
    $schema = (string)$source->query('SELECT DATABASE()')->fetchColumn();
    if ($schema === '') throw new RuntimeException('Choose the Mike source database.');
    $q = $source->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME LIKE 'work\\_%' ORDER BY TABLE_NAME");
    $q->execute([$schema]);
    $tables = $q->fetchAll(PDO::FETCH_COLUMN);
    foreach (ALPHA_IMPORT_CORE as $required) if (!in_array($required,$tables,true)) throw new RuntimeException('Missing source table: '.$required);
    $requiredColumns = [
        'work_customers'=>['id','display_name','email','phone'],
        'work_properties'=>['id','customer_id','address'],
        'work_jobs'=>['id','customer_id','property_id','customer_name','job_address','original_scope','current_scope','status','created_at','original_estimate_amount','original_estimate_hours','agreed_hourly_rate','payment_mode','unpaid_balance_limit','work_already_value','materials_already_value','payments_received','planned_start_at','planned_finish_at','media_archive_url'],
    ];
    $cols = $source->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    foreach ($requiredColumns as $table=>$names) {
        $cols->execute([$schema,$table]);
        $missing = array_diff($names,$cols->fetchAll(PDO::FETCH_COLUMN));
        if ($missing) throw new RuntimeException('Source schema needs migration before import: '.$table.'.'.implode(',',$missing));
    }
    $counts = [];
    foreach ($tables as $table) {
        if (!preg_match('/^work_[a-zA-Z0-9_]+$/D',$table)) throw new RuntimeException('Unexpected source table name.');
        $counts[$table] = (int)$source->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    }
    $q = $source->query('SELECT COUNT(*) FROM work_properties p LEFT JOIN work_customers c ON c.id=p.customer_id WHERE c.id IS NULL');
    $badProperties = (int)$q->fetchColumn();
    $q = $source->query('SELECT COUNT(*) FROM work_jobs j LEFT JOIN work_customers c ON c.id=j.customer_id LEFT JOIN work_properties p ON p.id=j.property_id AND p.customer_id=j.customer_id WHERE c.id IS NULL OR p.id IS NULL OR TRIM(j.job_address)=\'\'');
    $badJobs = (int)$q->fetchColumn();
    $deferred = array_diff_key($counts,array_flip(ALPHA_IMPORT_CORE));
    return ['source_schema'=>$schema,'core_counts'=>array_intersect_key($counts,array_flip(ALPHA_IMPORT_CORE)),
        'deferred_counts'=>$deferred,'deferred_rows'=>array_sum($deferred),
        'invalid_properties'=>$badProperties,'invalid_jobs'=>$badJobs];
}

function alpha_mike_import_summary(PDO $target, array $ctx): ?array {
    if ($ctx['role'] !== 'owner') throw new RuntimeException('Owner access required.');
    $q = $target->prepare('SELECT imported_at,source_name,inventory_json FROM alpha_import_runs WHERE business_id=? ORDER BY id DESC LIMIT 1');
    $q->execute([(int)$ctx['business_id']]);
    $run = $q->fetch();
    if (!$run) return null;
    $run['inventory'] = json_decode($run['inventory_json'],true,512,JSON_THROW_ON_ERROR);
    unset($run['inventory_json']);
    $q = $target->prepare('SELECT entity,COUNT(*) AS imported FROM alpha_legacy_links WHERE business_id=? GROUP BY entity');
    $q->execute([(int)$ctx['business_id']]);
    $run['imported'] = array_column($q->fetchAll(),'imported','entity');
    return $run;
}

function alpha_mike_target_check(PDO $target, int $businessId): void {
    if ($businessId < 1) throw new InvalidArgumentException('Choose the destination business ID.');
    $q = $target->prepare("SELECT COUNT(*) FROM alpha_memberships WHERE business_id=? AND role='owner' AND disabled_at IS NULL");
    $q->execute([$businessId]);
    if ((int)$q->fetchColumn() < 1) throw new RuntimeException('Destination needs its own active owner first.');
    foreach (['alpha_customers','alpha_properties','alpha_jobs','alpha_legacy_links','alpha_import_runs'] as $table) {
        $q = $target->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE business_id=?');
        $q->execute([$businessId]);
        if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Destination is not empty: '.$table);
    }
}

/** Atomic target transaction; source connection needs SELECT permission only. */
function alpha_import_mike_core(PDO $source, PDO $target, int $businessId): array {
    if ($source->inTransaction() || $target->inTransaction()) throw new RuntimeException('Close existing transactions before import.');
    $sourceSchema = (string)$source->query('SELECT DATABASE()')->fetchColumn();
    $targetSchema = (string)$target->query('SELECT DATABASE()')->fetchColumn();
    if ($sourceSchema === '' || $targetSchema === '' || $sourceSchema === $targetSchema) throw new RuntimeException('Source and target database names must differ.');
    $source->exec('SET TRANSACTION READ ONLY');
    $source->beginTransaction();
    try {
        $inventory = alpha_mike_inventory($source);
        if ($inventory['invalid_properties'] || $inventory['invalid_jobs']) throw new RuntimeException('Repair unlinked customers/properties/jobs before importing.');
        $target->beginTransaction();
        try {
            $lock = $target->prepare('SELECT id FROM alpha_businesses WHERE id=? FOR UPDATE');
            $lock->execute([$businessId]);
            if (!$lock->fetch()) throw new RuntimeException('Destination business not found.');
            alpha_mike_target_check($target,$businessId);
            $maps = ['customer'=>[],'property'=>[],'job'=>[]];
            $link = $target->prepare('INSERT INTO alpha_legacy_links (business_id,entity,source_id,target_id) VALUES (?,?,?,?)');
            $customerInsert = $target->prepare('INSERT INTO alpha_customers(business_id,name,email,phone) VALUES(?,?,?,?)');
            foreach ($source->query('SELECT id,display_name,email,phone FROM work_customers ORDER BY id') as $row) {
                $name = trim((string)$row['display_name']);
                if ($name === '') throw new RuntimeException('Empty source customer name #'.$row['id']);
                $customerInsert->execute([$businessId,$name,$row['email'],$row['phone']]);
                $maps['customer'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'customer',$row['id'],$maps['customer'][(int)$row['id']]]);
            }
            $propertyInsert = $target->prepare('INSERT INTO alpha_properties(business_id,customer_id,address) VALUES(?,?,?)');
            foreach ($source->query('SELECT id,customer_id,address FROM work_properties ORDER BY id') as $row) {
                $address = trim((string)$row['address']);
                if ($address === '' || !isset($maps['customer'][(int)$row['customer_id']])) throw new RuntimeException('Invalid source property #'.$row['id']);
                $propertyInsert->execute([$businessId,$maps['customer'][(int)$row['customer_id']],$address]);
                $maps['property'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'property',$row['id'],$maps['property'][(int)$row['id']]]);
            }
            $jobInsert = $target->prepare('INSERT INTO alpha_jobs(business_id,customer_id,property_id,title,status,created_at) VALUES(?,?,?,?,?,?)');
            $details = $target->prepare('INSERT INTO alpha_job_legacy_details(business_id,job_id,original_scope,current_scope,original_estimate_amount,original_estimate_hours,agreed_hourly_rate,payment_mode,unpaid_balance_limit,work_already_value,materials_already_value,payments_received,planned_start_at,planned_finish_at,media_archive_url,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $sql = 'SELECT id,customer_id,property_id,customer_name,job_address,original_scope,current_scope,status,created_at,original_estimate_amount,original_estimate_hours,agreed_hourly_rate,payment_mode,unpaid_balance_limit,work_already_value,materials_already_value,payments_received,planned_start_at,planned_finish_at,media_archive_url FROM work_jobs ORDER BY id';
            foreach ($source->query($sql) as $row) {
                $customerId = $maps['customer'][(int)$row['customer_id']] ?? null;
                $propertyId = $maps['property'][(int)$row['property_id']] ?? null;
                if (!$customerId || !$propertyId) throw new RuntimeException('Unlinked source job #'.$row['id']);
                $scopeLine = trim((string)strtok((string)$row['original_scope'],"\n"));
                $title = mb_substr($scopeLine ?: ((string)$row['customer_name'].' · '.(string)$row['job_address']),0,190);
                $jobInsert->execute([$businessId,$customerId,$propertyId,$title,$row['status'],$row['created_at']]);
                $newId = (int)$target->lastInsertId();
                $link->execute([$businessId,'job',$row['id'],$newId]);
                $details->execute([$businessId,$newId,$row['original_scope'],$row['current_scope'],$row['original_estimate_amount'],$row['original_estimate_hours'],$row['agreed_hourly_rate'],$row['payment_mode'],$row['unpaid_balance_limit'],$row['work_already_value'],$row['materials_already_value'],$row['payments_received'],$row['planned_start_at'],$row['planned_finish_at'],$row['media_archive_url'],$row['created_at']]);
                $maps['job'][(int)$row['id']] = $newId;
            }
            foreach (['customer'=>'work_customers','property'=>'work_properties','job'=>'work_jobs'] as $entity=>$table) {
                if (count($maps[$entity]) !== $inventory['core_counts'][$table]) throw new RuntimeException('Count mismatch for '.$entity);
            }
            $q = $target->prepare('INSERT INTO alpha_import_runs (business_id,source_name,inventory_json) VALUES (?,?,?)');
            $q->execute([$businessId,$sourceSchema,json_encode($inventory,JSON_THROW_ON_ERROR)]);
            $target->commit();
        } catch (Throwable $e) { if ($target->inTransaction()) $target->rollBack(); throw $e; }
        $source->rollBack();
        return $inventory;
    } catch (Throwable $e) { if ($source->inTransaction()) $source->rollBack(); throw $e; }
}
