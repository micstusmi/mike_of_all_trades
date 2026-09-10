<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function intake_fail(string $message, int $status=400): never {
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;max-width:800px;margin:40px auto;padding:20px}a{color:#06c}</style><h1>Could not import job</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><p><a href="../../admin/work/import_customer_job.php">← Back to importer</a></p>';
    exit;
}
function safe_file_name(string $name): string {
    
/* V8.4A MATERIALS CONTEXT */
$materialsAllowed = ['mike_all','customer_all','shared','labour_only','mike_advise'];
$materialsResponsibility = trim((string)($_POST['materials_responsibility'] ?? 'mike_advise'));
if (!in_array($materialsResponsibility, $materialsAllowed, true)) {
    $materialsResponsibility = 'mike_advise';
}
$materialsNotes = trim((string)($_POST['materials_notes'] ?? ''));
if ($materialsNotes === '') $materialsNotes = null;
$name=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($name)) ?: 'upload';
    return substr($name,0,180);
}
function response_text(array $data): string {
    $out='';
    foreach(($data['output']??[]) as $item){
        foreach(($item['content']??[]) as $c){
            if(isset($c['text']) && is_string($c['text'])) $out.=$c['text'];
        }
    }
    return trim($out);
}

if($_SERVER['REQUEST_METHOD']!=='POST') intake_fail('POST required.',405);
$organisation=trim((string)($_POST['customer_organisation']??''));
$contact=trim((string)($_POST['customer_name']??''));
$phone=wt_normalise_phone((string)($_POST['customer_phone']??''));
$email=trim((string)($_POST['customer_email']??''));
$site=trim((string)($_POST['site_name']??''));
$address=trim((string)($_POST['job_address']??''));
$pasted=trim((string)($_POST['pasted_text']??''));

function intake_schedule_datetime(?string $value): ?string {
    $value=trim((string)$value);
    if($value==='') return null;

    $ts=strtotime($value);
    if($ts===false) intake_fail('Invalid booking date/time.');

    return date('Y-m-d H:i:s',$ts);
}

$plannedStart=intake_schedule_datetime($_POST['planned_start_at']??null);
$plannedFinish=intake_schedule_datetime($_POST['planned_finish_at']??null);

if($plannedStart && $plannedFinish && strtotime($plannedFinish)<strtotime($plannedStart)){
    intake_fail('Expected finish cannot be before the planned start.');
}

$parkingNotes=trim((string)($_POST['parking_notes']??''));
$accessNotes=trim((string)($_POST['access_notes']??''));
$displayName=$organisation!=='' ? $organisation : ($contact!=='' ? $contact : 'Customer');
$jobAddress=$address!=='' ? $address : ($site!=='' ? $site.' showroom / site' : 'Address to be confirmed');

$files=$_FILES['intake_files']??null;
$fileRows=[];
if(is_array($files) && isset($files['name']) && is_array($files['name'])){
    for($i=0;$i<count($files['name']);$i++){
        $err=(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE);
        if($err===UPLOAD_ERR_NO_FILE) continue;
        if($err!==UPLOAD_ERR_OK) intake_fail('One of the uploads failed with PHP upload error '.$err.'.');
        $size=(int)($files['size'][$i]??0);
        if($size<=0 || $size>12*1024*1024) intake_fail('Each upload must be 12 MB or smaller.');
        $tmp=(string)$files['tmp_name'][$i];
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        $allowed=['image/jpeg','image/png','image/webp','application/pdf'];
        if(!in_array($mime,$allowed,true)) intake_fail('Unsupported file type: '.$mime.'. Use JPEG, PNG, WEBP or PDF.');
        $fileRows[]=['tmp'=>$tmp,'name'=>(string)$files['name'][$i],'mime'=>$mime,'size'=>$size];
    }
}
if(count($fileRows)>10) intake_fail('Please upload no more than 10 files at once.');
if(!$fileRows && $pasted==='') intake_fail('Upload at least one screenshot/PDF or paste the customer list.');

$token=bin2hex(random_bytes(32));
$placeholder=$pasted!=='' ? $pasted : 'Customer supplied screenshots/files. AI extraction is in progress.';
try{
    $stmt=$pdo->prepare("INSERT INTO work_jobs
      (public_token,customer_name,customer_organisation,site_name,customer_phone,customer_email,job_address,original_scope,current_scope,unforeseen_conditions,
       original_estimate_amount,original_estimate_hours,agreed_hourly_rate,payment_mode,unpaid_balance_limit,
       work_already_value,materials_already_value,payments_received,revised_forecast_low,revised_forecast_high,status,
       customer_request_text,customer_request_updated_at,ai_breakdown_status,job_source,job_source_detail,original_contact_notes,
       planned_start_at,planned_finish_at,parking_notes,access_notes)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $token,$displayName,$organisation?:null,$site?:null,$phone,$email,$jobAddress,
        $placeholder,'','',null,null,null,'completion',null,0,0,0,null,null,
        'awaiting_agreement',$placeholder,date('Y-m-d H:i:s'),'pending','other',
        'Uploaded screenshots / PDF / pasted list',
        'Original customer material imported into Work Tracker',
        $plannedStart,
        $plannedFinish,
        $parkingNotes!=='' ? $parkingNotes : null,
        $accessNotes!=='' ? $accessNotes : null
    ]);
    $jobId=(int)$pdo->lastInsertId();
/* V8.4A: persist customer's materials expectation without disturbing draft creation. */
$pdo->prepare("UPDATE work_jobs SET materials_responsibility=?, materials_notes=? WHERE id=?")
    ->execute([$materialsResponsibility, $materialsNotes, $jobId]);

}catch(Throwable $e){ intake_fail('Could not create the draft job: '.$e->getMessage(),500); }

// Send the live link immediately, before AI analysis, so the job is already logged.
$job=wt_job($pdo,$jobId);
if($phone!==''){
    $message='Mike of All Trades: Your job has been logged. I am processing the list you supplied.';

    if($plannedStart){
        $message.=' I have proposed '.
            date('D j M',strtotime($plannedStart)).
            ' at '.
            date('g:i a',strtotime($plannedStart)).
            ' for the booking.';
    }

    $message.=' You can already review or update the live job here: '.wt_public_url($job);

    wt_send_sms($pdo,$jobId,$phone,$message,'job_logged_link');
}

$base=wt_env('WORKTRACKER_PRIVATE_UPLOAD_DIR',dirname(__DIR__,2).'/storage/private/job_intake');
$jobDir=rtrim($base,'/').'/job_'.$jobId;
if(!is_dir($jobDir) && !mkdir($jobDir,0770,true) && !is_dir($jobDir)) intake_fail('Draft job #'.$jobId.' was created, but the private upload directory could not be created.',500);
$privateRoot=dirname($jobDir);
$ht=$privateRoot.'/.htaccess';
if(!is_file($ht)) @file_put_contents($ht,"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");

$stored=[];
$insFile=$pdo->prepare("INSERT INTO work_job_intake_files(job_id,original_name,stored_name,relative_path,mime_type,file_size,sha256) VALUES(?,?,?,?,?,?,?)");
foreach($fileRows as $idx=>$f){
    $safe=safe_file_name($f['name']);
    $storedName=sprintf('%02d_',($idx+1)).bin2hex(random_bytes(4)).'_'.$safe;
    $dest=$jobDir.'/'.$storedName;
    if(!move_uploaded_file($f['tmp'],$dest)) intake_fail('Draft job #'.$jobId.' was created, but an uploaded file could not be preserved.',500);
    $sha=hash_file('sha256',$dest) ?: null;
    $relative='job_'.$jobId.'/'.$storedName;
    $insFile->execute([$jobId,$f['name'],$storedName,$relative,$f['mime'],filesize($dest)?:$f['size'],$sha]);
    $stored[]=['id'=>(int)$pdo->lastInsertId(),'path'=>$dest,'name'=>$f['name'],'mime'=>$f['mime']];
}

$apiKey=wt_env('OPENAI_API_KEY');
if(!$apiKey){
    $pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='failed',ai_breakdown_error=? WHERE id=?")->execute(['OPENAI_API_KEY is not configured',$jobId]);
    header('Location: ../../admin/work/intake_review.php?id='.$jobId.'&ai_failed=1'); exit;
}
$model=wt_env('WORKTRACKER_AI_MODEL','gpt-5.6-luna');
$prompt=<<<TXT
You are extracting a customer's requested maintenance/showroom work from screenshots, photos, PDFs and/or pasted text for Mike of All Trades in Victoria, Australia.

This stage is transcription and organisation, NOT the detailed work plan. Preserve the customer's meaning and product names/codes closely. Do not invent missing scope, measurements, colours, quantities, locations or installation requirements.
Deduplicate exact repeated screenshots/items. If an item references a ticket or product code but the required physical work is unclear, keep the item and mark it as needing clarification instead of guessing.
Where a location/site prefix is repeated (for example "Niddrie -"), identify the site separately and remove the repeated site prefix from each item when that is clearly safe.
Return JSON only with this exact shape:
{"organisation_name":null,"site_name":null,"items":[{"text":"...","needs_clarification":false,"clarification_reason":""}],"notes":""}

Known organisation supplied by Mike: {$organisation}
Known site supplied by Mike: {$site}
Pasted customer text, if any:
---
{$pasted}
---
TXT;
$content=[['type'=>'input_text','text'=>$prompt]];
foreach($stored as $f){
    $bytes=file_get_contents($f['path']);
    if($bytes===false) continue;
    $b64=base64_encode($bytes);
    if($f['mime']==='application/pdf'){
        $content[]=['type'=>'input_file','filename'=>$f['name'],'file_data'=>'data:application/pdf;base64,'.$b64,'detail'=>'high'];
    }else{
        $content[]=['type'=>'input_image','image_url'=>'data:'.$f['mime'].';base64,'.$b64,'detail'=>'high'];
    }
}
$payload=json_encode(['model'=>$model,'input'=>[['role'=>'user','content'=>$content]],'max_output_tokens'=>8000],JSON_UNESCAPED_SLASHES);
$ch=curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Content-Type: application/json']]);
$raw=curl_exec($ch);$curlErr=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if($raw===false||$http<200||$http>=300){
    $msg=$curlErr?:('OpenAI extraction failed with HTTP '.$http);
    $pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='failed',ai_breakdown_error=? WHERE id=?")->execute([$msg,$jobId]);
    header('Location: ../../admin/work/intake_review.php?id='.$jobId.'&ai_failed=1'); exit;
}
$data=json_decode($raw,true);$text=response_text(is_array($data)?$data:[]);
$text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($text));
$parsed=json_decode($text,true);
if(!is_array($parsed)||!isset($parsed['items'])||!is_array($parsed['items'])){
    $pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='failed',ai_breakdown_error=? WHERE id=?")->execute(['AI returned an unexpected intake-extraction format.',$jobId]);
    header('Location: ../../admin/work/intake_review.php?id='.$jobId.'&ai_failed=1'); exit;
}

$pdo->beginTransaction();
try{
    $pdo->prepare('DELETE FROM work_job_intake_items WHERE job_id=?')->execute([$jobId]);
    $ins=$pdo->prepare('INSERT INTO work_job_intake_items(job_id,item_order,request_text,needs_clarification,clarification_reason) VALUES(?,?,?,?,?)');
    $lines=[];$order=0;
    foreach($parsed['items'] as $item){
        $txt=trim((string)($item['text']??'')); if($txt==='') continue;
        $order+=10;$need=!empty($item['needs_clarification'])?1:0;$reason=trim((string)($item['clarification_reason']??''));
        $ins->execute([$jobId,$order,$txt,$need,$reason?:null]);
        $lines[]=$txt;
    }
    if(!$lines && $pasted!=='') $lines=preg_split('/\R+/',trim($pasted))?:[];
    $requestText=implode("\n",array_map(fn($v)=>trim((string)$v),array_filter($lines,fn($v)=>trim((string)$v)!=='')));
    if($requestText==='') $requestText=$placeholder;
    $detectedOrg=$organisation!==''?$organisation:trim((string)($parsed['organisation_name']??''));
    $detectedSite=$site!==''?$site:trim((string)($parsed['site_name']??''));
    $pdo->prepare("UPDATE work_jobs SET customer_organisation=?,site_name=?,customer_name=?,customer_request_text=?,customer_request_updated_at=NOW(),original_scope=?,ai_breakdown_status='pending',ai_breakdown_error=NULL WHERE id=?")
        ->execute([$detectedOrg?:null,$detectedSite?:null,$detectedOrg?:$displayName,$requestText,$requestText,$jobId]);
    $pdo->prepare("INSERT INTO work_job_request_revisions(job_id,source,previous_text,new_text,note,requires_review,reviewed_at) VALUES(?,'intake',?,?,?,0,NOW())")
        ->execute([$jobId,$placeholder,$requestText,'AI extracted requested items from original customer files']);
    $pdo->commit();
}catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare("UPDATE work_jobs SET ai_breakdown_status='failed',ai_breakdown_error=? WHERE id=?")->execute(['Could not save extracted list: '.$e->getMessage(),$jobId]); }

header('Location: ../../admin/work/intake_review.php?id='.$jobId.'&extracted=1');
exit;
