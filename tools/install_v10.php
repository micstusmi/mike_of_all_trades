<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/work_tracker.php';

$backupTable='work_jobs_v10_backup_'.date('Ymd_His');
$pdo->exec("CREATE TABLE `$backupTable` LIKE work_jobs");
$pdo->exec("INSERT INTO `$backupTable` SELECT * FROM work_jobs");
echo "DATABASE BACKUP: $backupTable\n";

function v10_column(PDO $pdo,string $table,string $column,string $definition): void {
    $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$table,$column]);
    if((int)$q->fetchColumn()>0){echo "EXISTS: $table.$column\n";return;}
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");echo "ADDED: $table.$column\n";
}
function v10_index(PDO $pdo,string $table,string $name,string $sql): void {
    $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");$q->execute([$table,$name]);
    if((int)$q->fetchColumn()>0){echo "EXISTS: $name\n";return;}$pdo->exec($sql);echo "ADDED: $name\n";
}

$pdo->exec("CREATE TABLE IF NOT EXISTS work_customers (id INT UNSIGNED NOT NULL AUTO_INCREMENT,display_name VARCHAR(190) NOT NULL,source_alias VARCHAR(190) NULL,organisation VARCHAR(190) NULL,email VARCHAR(190) NULL,phone VARCHAR(30) NULL,billing_address VARCHAR(500) NULL,payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,zoho_contact_id VARCHAR(50) NULL,zoho_contact_name VARCHAR(190) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uniq_work_customers_zoho(zoho_contact_id),KEY idx_work_customers_email(email),KEY idx_work_customers_phone(phone),KEY idx_work_customers_name(display_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS work_properties (id INT UNSIGNED NOT NULL AUTO_INCREMENT,customer_id INT UNSIGNED NOT NULL,label VARCHAR(190) NULL,address VARCHAR(500) NOT NULL,notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_work_properties_customer(customer_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
v10_column($pdo,'work_jobs','customer_id','INT UNSIGNED NULL AFTER id');
v10_column($pdo,'work_jobs','property_id','INT UNSIGNED NULL AFTER customer_id');
v10_index($pdo,'work_jobs','idx_work_jobs_customer','ALTER TABLE work_jobs ADD KEY idx_work_jobs_customer(customer_id)');
v10_index($pdo,'work_jobs','idx_work_jobs_property','ALTER TABLE work_jobs ADD KEY idx_work_jobs_property(property_id)');

$jobs=$pdo->query("SELECT * FROM work_jobs WHERE customer_id IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach($jobs as $job){
    $pdo->beginTransaction();
    try{
        $name=trim((string)$job['customer_name']);
        $terms=(stripos($name,'Fontaine Industries')!==false)?15:0;
        $q=$pdo->prepare("INSERT INTO work_customers(display_name,source_alias,organisation,email,phone,billing_address,payment_terms_days) VALUES(?,?,?,?,?,?,?)");
        $q->execute([$name,$name,($job['customer_organisation']??null)?:null,($job['customer_email']??null)?:null,($job['customer_phone']??null)?:null,($job['job_address']??null)?:null,$terms]);
        $customerId=(int)$pdo->lastInsertId();
        $q=$pdo->prepare("INSERT INTO work_properties(customer_id,label,address) VALUES(?,?,?)");$q->execute([$customerId,($job['site_name']??null)?:null,(string)$job['job_address']]);$propertyId=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE work_jobs SET customer_id=?,property_id=? WHERE id=?")->execute([$customerId,$propertyId,(int)$job['id']]);
        $pdo->commit();echo "BACKFILLED: job #{$job['id']} -> customer #$customerId, property #$propertyId\n";
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
echo "V10 customer/property installation complete. Existing jobs remain separate until Mike deliberately links them.\n";
