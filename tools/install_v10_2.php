<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/work_tracker.php';
$sql=file_get_contents(__DIR__.'/migration_v10_2_rapid_tasks.sql');
if($sql===false)throw new RuntimeException('Could not read the V10.2 migration.');
$pdo->exec($sql);
echo "V10.2 rapid task entry installation complete.\n";
