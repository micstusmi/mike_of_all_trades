<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/work_tracker.php';
$columns=[
    'media_archive_url'=>"VARCHAR(2048) NULL AFTER materials_notes",
    'media_archive_description'=>"TEXT NULL AFTER media_archive_url",
    'media_archive_verified_at'=>"DATE NULL AFTER media_archive_description",
    'media_archive_customer_visible'=>"TINYINT(1) NOT NULL DEFAULT 0 AFTER media_archive_verified_at",
    'media_archive_updated_at'=>"DATETIME NULL AFTER media_archive_customer_visible",
];
$check=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='work_jobs' AND COLUMN_NAME=?");
foreach($columns as $name=>$definition){$check->execute([$name]);if((int)$check->fetchColumn()>0){echo "EXISTS: work_jobs.$name\n";continue;}$pdo->exec("ALTER TABLE work_jobs ADD COLUMN `$name` $definition");echo "ADDED: work_jobs.$name\n";}
echo "V8.48 database installation complete.\n";
