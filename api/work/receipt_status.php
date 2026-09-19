<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';
require_once __DIR__.'/../../includes/work_receipts.php';
header('Content-Type: application/json; charset=utf-8');$jobId=(int)($_GET['job_id']??0);wt_job($pdo,$jobId);
$q=$pdo->prepare('SELECT status,COUNT(*) total FROM work_receipts WHERE job_id=? GROUP BY status');$q->execute([$jobId]);$counts=['processing'=>0,'ready'=>0,'applied'=>0,'duplicate'=>0,'failed'=>0,'cancelled'=>0];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$counts[(string)$row['status']]=(int)$row['total'];if($counts['processing']>0)wr_start_worker($jobId);echo json_encode(['ok'=>true,'counts'=>$counts]);
