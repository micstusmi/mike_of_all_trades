<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/work_tracker.php';
$sql=file_get_contents(__DIR__.'/migration_v10_1_invoice_ledger.sql');if($sql===false)throw new RuntimeException('Migration SQL not found.');
foreach(array_filter(array_map('trim',explode(';',$sql))) as $statement)$pdo->exec($statement);
echo "V10.1 invoice ledger installation complete. Historical invoices are not treated as reconciled until Mike selects their source records.\n";
