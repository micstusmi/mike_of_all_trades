<?php
declare(strict_types=1);
require_once __DIR__.'/../src/legacy_import.php';
if (getenv('EZ_ALPHA_TEST_DATABASE') !== 'YES') exit(1);

$sourceDsn = getenv('EZ_MIKE_READONLY_DSN') ?: '';
if ($sourceDsn === '') throw new RuntimeException('Missing fixture DSN.');
$source = new PDO($sourceDsn,(string)getenv('EZ_MIKE_READONLY_USER'),(string)getenv('EZ_MIKE_READONLY_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$target = alpha_db();
$target->exec("INSERT INTO alpha_businesses(name) VALUES('Synthetic Mike import')");
$business = (int)$target->lastInsertId();
$target->exec("INSERT INTO alpha_businesses(name) VALUES('Unrelated business')");
$other = (int)$target->lastInsertId();
$password = password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT);
$q = $target->prepare('INSERT INTO alpha_users(email,password_hash,email_verified_at) VALUES(?,?,UTC_TIMESTAMP())');
$q->execute(['import-owner-'.bin2hex(random_bytes(8)).'@example.invalid',$password]);
$owner = (int)$target->lastInsertId();
$m = $target->prepare("INSERT INTO alpha_memberships(business_id,user_id,role) VALUES(?,?,'owner')");
$m->execute([$business,$owner]);
$m->execute([$other,$owner]);

$inventory = alpha_mike_inventory($source);
if ($inventory['core_counts']['work_jobs'] !== 1 || $inventory['deferred_rows'] !== 1) throw new RuntimeException('Inventory missed deferred data.');
if ((float)$inventory['source_totals']['payments_amount'] !== 100.0) throw new RuntimeException('Source payment total wrong.');
$done = alpha_import_mike_core($source,$target,$business);
if ($done['deferred_rows'] !== 1) throw new RuntimeException('Partial status was hidden.');
$q = $target->prepare("SELECT target_id FROM alpha_legacy_links WHERE business_id=? AND entity='job' AND source_id=91");
$q->execute([$business]);
$jobId = (int)$q->fetchColumn();
$job = alpha_job($target,$business,$jobId);
if (!$job || $job['status'] !== 'paused' || (int)$job['mike_job_id'] !== 91 || $job['original_scope'] === '' || $job['media_archive_url'] === '') throw new RuntimeException('Imported job detail missing.');
if (alpha_job($target,$other,$jobId) !== null) throw new RuntimeException('Other business read Mike job.');
if (alpha_mike_job_history($target,['business_id'=>$other,'role'=>'owner'],$jobId) !== null) throw new RuntimeException('Other business read Mike history.');
$history = alpha_mike_job_history($target,['business_id'=>$business,'role'=>'owner'],$jobId);
if (count($history['tasks']) !== 1 || count($history['sessions']) !== 1 || count($history['breaks']) !== 1 || count($history['materials']) !== 1 || count($history['payments']) !== 1 || (float)$history['payments'][0]['amount'] !== 100.0) throw new RuntimeException('Job history missing or altered.');
if (alpha_mike_import_summary($target,['business_id'=>$other,'role'=>'owner']) !== null) throw new RuntimeException('Other business read import report.');
$report = alpha_mike_import_summary($target,['business_id'=>$business,'role'=>'owner']);
if ((int)$report['imported']['job'] !== 1 || (int)$report['imported']['task'] !== 1 || (int)$report['inventory']['deferred_counts']['work_receipts'] !== 1) throw new RuntimeException('Reconciliation report incorrect.');
try { alpha_import_mike_core($source,$target,$business); throw new RuntimeException('Repeat import accepted.'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(),'Destination is not empty')) throw $e; }
if ((int)$source->query('SELECT COUNT(*) FROM work_jobs')->fetchColumn() !== 1) throw new RuntimeException('Source job changed.');
echo "PASS: isolated job history import, source IDs, partial inventory and repeat protection.\n";
