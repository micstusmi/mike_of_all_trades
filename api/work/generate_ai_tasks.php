<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
header('Content-Type: application/json; charset=utf-8');
$jobId=(int)($_POST['job_id']??0); if($jobId<=0){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Invalid job ID']);exit;}
$job=wt_job($pdo,$jobId);
$apiKey=wt_env('OPENAI_API_KEY');
if(!$apiKey){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'OPENAI_API_KEY is not configured']);exit;}
$request=trim((string)($job['customer_request_text']??$job['original_scope']??''));
/* V8.4A AI MATERIALS GUIDANCE */
$materialsMode = (string)($job['materials_responsibility'] ?? 'mike_advise');
$materialsNotes = trim((string)($job['materials_notes'] ?? ''));
$materialsLabels = [
  'mike_all'     => 'Mike is expected to provide all required materials.',
  'customer_all' => 'The customer says they already have all required materials.',
  'shared'       => 'The customer has some materials and expects Mike to provide some materials.',
  'labour_only'  => 'The customer has requested labour only / no Mike-supplied materials.',
  'mike_advise'  => 'The customer is unsure about materials and wants Mike to advise what is required.',
];
$materialsContext = $materialsLabels[$materialsMode] ?? $materialsLabels['mike_advise'];
if ($materialsNotes !== '') $materialsContext .= "\nCustomer materials note: ".$materialsNotes;

if($request===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Customer request is empty']);exit;}

$pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='running',ai_breakdown_error=NULL WHERE id=?")->execute([$jobId]);
$model=wt_env('WORKTRACKER_AI_MODEL','gpt-5.6-luna');
$prompt=<<<TXT
You are preparing a structured handyman job plan for Mike of All Trades in Victoria, Australia.
Break the customer's requested work into practical individual tasks that Mike can start/stop separately.
Return JSON only, with this exact top-level shape: {"tasks":[...]}.
Each task object must contain: title, description, customer_summary, detailed_procedure, time_drivers, waiting_curing_notes, suggested_materials, ai_estimate_low, ai_estimate_high, ai_reasoning.
Use plain English. detailed_procedure should be granular enough to explain real preparation, setup, investigation, cleaning, testing, adjustment, pack-up and documentation where relevant.
Separate elapsed drying/curing/waiting from billable labour. Do not claim every possible step will definitely be required.
Do not state or imply Mike will personally perform licensed electrical or plumbing work. Where regulated work may be required, say that a suitably licensed contractor may be required.
Do not invent measurements, colours, brands, quantities or site conditions. If unknown, identify them as variables in time_drivers.
Estimate labour hours as realistic low/high ranges for each task, not calendar duration.

Materials responsibility supplied with this job:
---
$materialsContext
---

Materials rules:
- Treat the materials responsibility above as explicit customer context.
- If labour_only, do not create a shopping list or assume Mike is supplying products. Only flag a genuinely necessary consumable/part as something to confirm if the work cannot reasonably proceed without it.
- If customer_all, assume the customer intends to supply the required products, but note compatibility/suitability checks where relevant.
- If shared, distinguish customer-supplied items from possible Mike-supplied items. Do not invent which side supplies an unspecified item.
- If mike_all, suggest practical likely materials/consumables but do not invent brands, measurements, colours or quantities.
- If mike_advise, identify likely materials as provisional and explain what Mike needs to confirm.
- suggested_materials is planning information only; it is not evidence that an item was actually purchased or used.

Customer's current requested-work list:
---
$request
---
TXT;
$payload=json_encode(['model'=>$model,'input'=>$prompt,'max_output_tokens'=>12000],JSON_UNESCAPED_SLASHES);
$ch=curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>90,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Content-Type: application/json']]);
$raw=curl_exec($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($raw===false||$status<200||$status>=300){$msg=$err?:('OpenAI request failed with HTTP '.$status);$pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='failed',ai_breakdown_error=? WHERE id=?")->execute([$msg,$jobId]);http_response_code(502);echo json_encode(['ok'=>false,'error'=>$msg]);exit;}
$data=json_decode($raw,true); $text='';
foreach(($data['output']??[]) as $out){foreach(($out['content']??[]) as $c){if(isset($c['text']))$text.=$c['text'];}}
$text=trim($text); $text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$text);
$parsed=json_decode($text,true);
if(!is_array($parsed)||!isset($parsed['tasks'])||!is_array($parsed['tasks'])){$msg='AI returned an unexpected format.';$pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='failed',ai_breakdown_error=? WHERE id=?")->execute([$msg,$jobId]);http_response_code(502);echo json_encode(['ok'=>false,'error'=>$msg]);exit;}

$batch='ai-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
$q=$pdo->prepare("SELECT id FROM work_job_request_revisions WHERE job_id=? ORDER BY id DESC LIMIT 1");$q->execute([$jobId]);$revisionId=$q->fetchColumn();
// Preserve prior AI task history, but hide/cancel untouched superseded AI suggestions before inserting the refreshed breakdown.
$pdo->prepare("UPDATE work_tasks t SET t.status='cancelled',t.customer_visible=0 WHERE t.job_id=? AND t.task_origin='ai_suggested' AND t.status='not_started' AND NOT EXISTS (SELECT 1 FROM work_sessions s WHERE s.task_id=t.id) AND NOT EXISTS (SELECT 1 FROM work_task_change_requests c WHERE c.task_id=t.id)")->execute([$jobId]);
$q=$pdo->prepare("SELECT COALESCE(MAX(task_order),0) FROM work_tasks WHERE job_id=?");$q->execute([$jobId]);$order=(int)$q->fetchColumn();
$ins=$pdo->prepare("INSERT INTO work_tasks(job_id,task_order,title,description,customer_summary,detailed_procedure,time_drivers,waiting_curing_notes,suggested_materials,status,task_origin,ai_batch_key,source_request_revision_id,ai_estimate_low,ai_estimate_high,ai_reasoning,customer_visible) VALUES(?,?,?,?,?,?,?,?,?,'not_started','ai_suggested',?,?,?,?,?,1)");
$count=0;
foreach($parsed['tasks'] as $t){
  $title=trim((string)($t['title']??'')); if($title==='')continue; $order+=10;
  $num=fn($v)=>is_numeric($v)?max(0,(float)$v):null;
  $ins->execute([$jobId,$order,$title,trim((string)($t['description']??''))?:null,trim((string)($t['customer_summary']??''))?:null,trim((string)($t['detailed_procedure']??''))?:null,trim((string)($t['time_drivers']??''))?:null,trim((string)($t['waiting_curing_notes']??''))?:null,trim((string)($t['suggested_materials']??''))?:null,$batch,$revisionId?:null,$num($t['ai_estimate_low']??null),$num($t['ai_estimate_high']??null),trim((string)($t['ai_reasoning']??''))?:null]);
  $count++;
}
$pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='complete',ai_breakdown_generated_at=NOW(),ai_breakdown_error=NULL WHERE id=?")->execute([$jobId]);
echo json_encode(['ok'=>true,'count'=>$count,'batch'=>$batch]);
