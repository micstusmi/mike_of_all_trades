<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';

const ALPHA_IMPORT_CORE = ['work_customers','work_properties','work_jobs','work_tasks','work_sessions','work_session_breaks','work_materials','work_payments'];

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
        'work_tasks'=>['id','job_id','task_order','title','description','status','customer_visible','completed_at','created_at'],
        'work_sessions'=>['id','job_id','task_id','started_at','ended_at','category','billable','notes','session_source','retrospective_hours'],
        'work_session_breaks'=>['id','session_id','started_at','ended_at','reason','note'],
        'work_materials'=>['id','job_id','purchased_at','description','supplier','cost','paid_by','receipt_path','notes'],
        'work_payments'=>['id','job_id','amount','payment_type','method','notes','paid_at'],
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
    $relations = [
        'task_job'=>'SELECT COUNT(*) FROM work_tasks t LEFT JOIN work_jobs j ON j.id=t.job_id WHERE j.id IS NULL',
        'session_job_task'=>'SELECT COUNT(*) FROM work_sessions s LEFT JOIN work_jobs j ON j.id=s.job_id LEFT JOIN work_tasks t ON t.id=s.task_id AND t.job_id=s.job_id WHERE j.id IS NULL OR (s.task_id IS NOT NULL AND t.id IS NULL)',
        'break_session'=>'SELECT COUNT(*) FROM work_session_breaks b LEFT JOIN work_sessions s ON s.id=b.session_id WHERE s.id IS NULL',
        'material_job'=>'SELECT COUNT(*) FROM work_materials m LEFT JOIN work_jobs j ON j.id=m.job_id WHERE j.id IS NULL',
        'payment_job'=>'SELECT COUNT(*) FROM work_payments p LEFT JOIN work_jobs j ON j.id=p.job_id WHERE j.id IS NULL',
    ];
    $invalid = [];
    foreach ($relations as $key=>$sql) $invalid[$key] = (int)$source->query($sql)->fetchColumn();
    $deferred = array_diff_key($counts,array_flip(ALPHA_IMPORT_CORE));
    $totals = [
        'materials_cost'=>(string)$source->query('SELECT COALESCE(SUM(cost),0) FROM work_materials')->fetchColumn(),
        'payments_amount'=>(string)$source->query('SELECT COALESCE(SUM(amount),0) FROM work_payments')->fetchColumn(),
    ];
    return ['source_schema'=>$schema,'core_counts'=>array_intersect_key($counts,array_flip(ALPHA_IMPORT_CORE)),
        'deferred_counts'=>$deferred,'deferred_rows'=>array_sum($deferred),'source_totals'=>$totals,
        'invalid_properties'=>$badProperties,'invalid_jobs'=>$badJobs,'invalid_relations'=>$invalid];
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

function alpha_mike_job_history(PDO $target, array $ctx, int $jobId): ?array {
    $businessId = (int)$ctx['business_id'];
    if (!alpha_job($target,$businessId,$jobId)) return null;
    $sets = [
        'tasks'=>'SELECT id,task_order,title,description,status,completed_at FROM alpha_tasks WHERE business_id=? AND job_id=? ORDER BY task_order,id LIMIT 500',
        'sessions'=>'SELECT id,task_id,started_at,ended_at,category,billable,notes,session_source,retrospective_hours FROM alpha_work_sessions WHERE business_id=? AND job_id=? ORDER BY started_at,id LIMIT 500',
        'materials'=>'SELECT purchased_at,description,supplier,cost,paid_by,notes FROM alpha_materials WHERE business_id=? AND job_id=? ORDER BY purchased_at,id LIMIT 500',
        'payments'=>'SELECT paid_at,amount,payment_type,method,notes FROM alpha_payments WHERE business_id=? AND job_id=? ORDER BY paid_at,id LIMIT 500',
    ];
    $history = [];
    foreach ($sets as $name=>$sql) {
        $q = $target->prepare($sql);
        $q->execute([$businessId,$jobId]);
        $history[$name] = $q->fetchAll();
    }
    $q = $target->prepare('SELECT b.session_id,b.started_at,b.ended_at,b.reason,b.note FROM alpha_session_breaks b JOIN alpha_work_sessions s ON s.business_id=b.business_id AND s.id=b.session_id WHERE b.business_id=? AND s.job_id=? ORDER BY b.started_at,b.id LIMIT 500');
    $q->execute([$businessId,$jobId]);
    $history['breaks'] = $q->fetchAll();
    return $history;
}

function alpha_mike_target_check(PDO $target, int $businessId): void {
    if ($businessId < 1) throw new InvalidArgumentException('Choose the destination business ID.');
    $q = $target->prepare("SELECT COUNT(*) FROM alpha_memberships WHERE business_id=? AND role='owner' AND disabled_at IS NULL");
    $q->execute([$businessId]);
    if ((int)$q->fetchColumn() < 1) throw new RuntimeException('Destination needs its own active owner first.');
    foreach (['alpha_customers','alpha_properties','alpha_jobs','alpha_tasks','alpha_work_sessions','alpha_session_breaks','alpha_materials','alpha_payments','alpha_legacy_links','alpha_import_runs'] as $table) {
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
        if ($inventory['invalid_properties'] || $inventory['invalid_jobs'] || array_sum($inventory['invalid_relations'])) throw new RuntimeException('Repair unlinked source records before importing.');
        $target->beginTransaction();
        try {
            $lock = $target->prepare('SELECT id FROM alpha_businesses WHERE id=? FOR UPDATE');
            $lock->execute([$businessId]);
            if (!$lock->fetch()) throw new RuntimeException('Destination business not found.');
            alpha_mike_target_check($target,$businessId);
            $maps = ['customer'=>[],'property'=>[],'job'=>[],'task'=>[],'session'=>[],'break'=>[],'material'=>[],'payment'=>[]];
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
            $taskInsert = $target->prepare('INSERT INTO alpha_tasks (business_id,job_id,task_order,title,description,status,customer_visible,completed_at,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach ($source->query('SELECT id,job_id,task_order,title,description,status,customer_visible,completed_at,created_at FROM work_tasks ORDER BY id') as $row) {
                $taskInsert->execute([$businessId,$maps['job'][(int)$row['job_id']],$row['task_order'],$row['title'],$row['description'],$row['status'],$row['customer_visible'],$row['completed_at'],$row['created_at']]);
                $maps['task'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'task',$row['id'],$maps['task'][(int)$row['id']]]);
            }
            $sessionInsert = $target->prepare('INSERT INTO alpha_work_sessions (business_id,job_id,task_id,started_at,ended_at,category,billable,notes,session_source,retrospective_hours) VALUES (?,?,?,?,?,?,?,?,?,?)');
            foreach ($source->query('SELECT id,job_id,task_id,started_at,ended_at,category,billable,notes,session_source,retrospective_hours FROM work_sessions ORDER BY id') as $row) {
                $taskId = $row['task_id'] === null ? null : $maps['task'][(int)$row['task_id']];
                $sessionInsert->execute([$businessId,$maps['job'][(int)$row['job_id']],$taskId,$row['started_at'],$row['ended_at'],$row['category'],$row['billable'],$row['notes'],$row['session_source'],$row['retrospective_hours']]);
                $maps['session'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'session',$row['id'],$maps['session'][(int)$row['id']]]);
            }
            $breakInsert = $target->prepare('INSERT INTO alpha_session_breaks (business_id,session_id,started_at,ended_at,reason,note) VALUES (?,?,?,?,?,?)');
            foreach ($source->query('SELECT id,session_id,started_at,ended_at,reason,note FROM work_session_breaks ORDER BY id') as $row) {
                $breakInsert->execute([$businessId,$maps['session'][(int)$row['session_id']],$row['started_at'],$row['ended_at'],$row['reason'],$row['note']]);
                $maps['break'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'break',$row['id'],$maps['break'][(int)$row['id']]]);
            }
            $materialInsert = $target->prepare('INSERT INTO alpha_materials (business_id,job_id,purchased_at,description,supplier,cost,paid_by,notes,receipt_path_reference) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach ($source->query('SELECT id,job_id,purchased_at,description,supplier,cost,paid_by,notes,receipt_path FROM work_materials ORDER BY id') as $row) {
                $materialInsert->execute([$businessId,$maps['job'][(int)$row['job_id']],$row['purchased_at'],$row['description'],$row['supplier'],$row['cost'],$row['paid_by'],$row['notes'],$row['receipt_path']]);
                $maps['material'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'material',$row['id'],$maps['material'][(int)$row['id']]]);
            }
            $paymentInsert = $target->prepare('INSERT INTO alpha_payments (business_id,job_id,amount,payment_type,method,notes,paid_at) VALUES (?,?,?,?,?,?,?)');
            foreach ($source->query('SELECT id,job_id,amount,payment_type,method,notes,paid_at FROM work_payments ORDER BY id') as $row) {
                $paymentInsert->execute([$businessId,$maps['job'][(int)$row['job_id']],$row['amount'],$row['payment_type'],$row['method'],$row['notes'],$row['paid_at']]);
                $maps['payment'][(int)$row['id']] = (int)$target->lastInsertId();
                $link->execute([$businessId,'payment',$row['id'],$maps['payment'][(int)$row['id']]]);
            }
            foreach (['customer'=>'work_customers','property'=>'work_properties','job'=>'work_jobs','task'=>'work_tasks','session'=>'work_sessions','break'=>'work_session_breaks','material'=>'work_materials','payment'=>'work_payments'] as $entity=>$table) {
                if (count($maps[$entity]) !== $inventory['core_counts'][$table]) throw new RuntimeException('Count mismatch for '.$entity);
            }
            foreach (['materials_cost'=>['alpha_materials','cost'],'payments_amount'=>['alpha_payments','amount']] as $label=>[$table,$column]) {
                $q = $target->prepare('SELECT COALESCE(SUM(`'.$column.'`),0) FROM `'.$table.'` WHERE business_id=?');
                $q->execute([$businessId]);
                if (round((float)$q->fetchColumn()*100) !== round((float)$inventory['source_totals'][$label]*100)) throw new RuntimeException('Amount mismatch for '.$label);
            }
            $q = $target->prepare('INSERT INTO alpha_import_runs (business_id,source_name,inventory_json) VALUES (?,?,?)');
            $q->execute([$businessId,$sourceSchema,json_encode($inventory,JSON_THROW_ON_ERROR)]);
            $target->commit();
        } catch (Throwable $e) { if ($target->inTransaction()) $target->rollBack(); throw $e; }
        $source->rollBack();
        return $inventory;
    } catch (Throwable $e) { if ($source->inTransaction()) $source->rollBack(); throw $e; }
}
