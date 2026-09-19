<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once __DIR__.'/../includes/work_receipts.php';
$jobId=(int)($argv[1]??0);$lock=fopen(sys_get_temp_dir().'/mot_receipt_ocr.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(0);
while(true){$pdo->beginTransaction();try{$sql="SELECT * FROM work_receipts WHERE status='processing'".($jobId>0?' AND job_id='.(int)$jobId:'')." ORDER BY id LIMIT 1 FOR UPDATE";$receipt=$pdo->query($sql)->fetch(PDO::FETCH_ASSOC);$pdo->commit();if(!$receipt)break;try{wr_process_receipt($pdo,$receipt);}catch(Throwable $e){$pdo->prepare("UPDATE work_receipts SET status='failed',error_message=? WHERE id=? AND status='processing'")->execute([mb_substr($e->getMessage(),0,4000),(int)$receipt['id']]);}}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();break;}}
flock($lock,LOCK_UN);fclose($lock);
