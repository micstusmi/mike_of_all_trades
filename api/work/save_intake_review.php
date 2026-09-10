<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}
$jobId=(int)($_POST['job_id']??0); if($jobId<=0) exit('Invalid job ID');
$job=wt_job($pdo,$jobId);
$ids=$_POST['item_id']??[];$texts=$_POST['item_text']??[];$delete=array_map('intval',$_POST['delete_ids']??[]);$deleteSet=array_flip($delete);
$pdo->beginTransaction();
try{
  $upd=$pdo->prepare('UPDATE work_job_intake_items SET request_text=?,needs_clarification=0,clarification_reason=NULL,item_order=? WHERE id=? AND job_id=?');
  $ins=$pdo->prepare('INSERT INTO work_job_intake_items(job_id,item_order,request_text,needs_clarification) VALUES(?,?,?,0)');
  $del=$pdo->prepare('DELETE FROM work_job_intake_items WHERE id=? AND job_id=?');
  $order=0;
  for($i=0;$i<count($texts);$i++){
    $id=(int)($ids[$i]??0);$txt=trim((string)($texts[$i]??''));
    if($id>0 && isset($deleteSet[$id])){$del->execute([$id,$jobId]);continue;}
    if($txt===''){if($id>0)$del->execute([$id,$jobId]);continue;}
    $order+=10;if($id>0)$upd->execute([$txt,$order,$id,$jobId]);else $ins->execute([$jobId,$order,$txt]);
  }
  $q=$pdo->prepare('SELECT request_text FROM work_job_intake_items WHERE job_id=? ORDER BY item_order,id');$q->execute([$jobId]);$lines=$q->fetchAll(PDO::FETCH_COLUMN);
  $newText=implode("\n",array_map('trim',$lines)); if($newText==='') throw new RuntimeException('The customer request list cannot be empty.');
  $old=(string)($job['customer_request_text']??'');
  $pdo->prepare("UPDATE work_jobs SET customer_request_text=?,original_scope=?,customer_request_updated_at=NOW(),ai_breakdown_status='pending',ai_breakdown_error=NULL WHERE id=?")->execute([$newText,$newText,$jobId]);
  $pdo->prepare("INSERT INTO work_job_request_revisions(job_id,source,previous_text,new_text,note,requires_review,reviewed_at) VALUES(?,'mike',?,?,?,0,NOW())")->execute([$jobId,$old,$newText,'Imported customer list reviewed before detailed AI breakdown']);
  $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(500);exit('Could not save intake review: '.$e->getMessage());}
header('Location: ../../admin/work/manage_job.php?id='.$jobId.'&run_ai=1&intake_reviewed=1#tasks');exit;
