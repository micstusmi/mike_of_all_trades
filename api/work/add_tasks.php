<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';
require_once __DIR__.'/../../includes/work_tracker.php';
header('Content-Type: application/json; charset=utf-8');

function task_json(int $status,array $payload): never {http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')task_json(405,['ok'=>false,'error'=>'POST required.']);

$jobId=(int)($_POST['job_id']??0);
wt_job($pdo,$jobId);
$requestToken=trim((string)($_POST['request_token']??''));
if($requestToken===''||strlen($requestToken)>64||!preg_match('/^[A-Za-z0-9._-]+$/',$requestToken))task_json(422,['ok'=>false,'error'=>'Invalid request token. Reload the page and try again.']);

$bulk=preg_split('/\R/u',trim((string)($_POST['bulk_tasks']??'')))?:[];
$titles=[];
foreach($bulk as $line){$line=trim($line);if($line!==''&&!in_array($line,$titles,true))$titles[]=$line;}
if(!$titles){$single=trim((string)($_POST['title']??''));if($single!=='')$titles[]=$single;}
if(!$titles)task_json(422,['ok'=>false,'error'=>'Enter a task title or paste one or more task titles.']);
if(count($titles)>50)task_json(422,['ok'=>false,'error'=>'A maximum of 50 tasks can be added at once.']);
foreach($titles as $title){if(mb_strlen($title)>190)task_json(422,['ok'=>false,'error'=>'Each task title must be 190 characters or fewer.']);}

$origins=['original','customer_requested','mike_added','ai_suggested','unforeseen'];
$origin=(string)($_POST['task_origin']??'mike_added');if(!in_array($origin,$origins,true))$origin='mike_added';
$description=count($titles)===1?trim((string)($_POST['description']??'')):'';
$number=static function(string $key): ?float {$value=trim((string)($_POST[$key]??''));if($value==='')return null;return max(0,min(10000,(float)$value));};
$estimateLow=$number('mike_estimate_low');$estimateHigh=$number('mike_estimate_high');
$visible=isset($_POST['customer_visible'])?1:0;

try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT task_ids_json FROM work_task_creation_requests WHERE request_token=? FOR UPDATE');$q->execute([$requestToken]);$existing=$q->fetchColumn();
    if($existing!==false){
        $ids=array_values(array_filter(array_map('intval',(array)json_decode((string)$existing,true))));
        $rows=[];if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT id,title FROM work_tasks WHERE id IN ($marks) ORDER BY task_order,id");$q->execute($ids);$rows=$q->fetchAll(PDO::FETCH_ASSOC);}
        $pdo->commit();task_json(200,['ok'=>true,'duplicate'=>true,'tasks'=>$rows]);
    }
    $q=$pdo->prepare('INSERT INTO work_task_creation_requests(request_token,job_id,created_by_user_id,task_ids_json) VALUES(?,?,?,?)');$q->execute([$requestToken,$jobId,(int)($_SESSION['user_id']??0),'[]']);
    $q=$pdo->prepare('SELECT COALESCE(MAX(task_order),0)+10 FROM work_tasks WHERE job_id=? FOR UPDATE');$q->execute([$jobId]);$order=(int)$q->fetchColumn();
    $insert=$pdo->prepare('INSERT INTO work_tasks(job_id,task_order,title,description,task_origin,mike_estimate_low,mike_estimate_high,customer_visible) VALUES(?,?,?,?,?,?,?,?)');
    $created=[];
    foreach($titles as $title){$insert->execute([$jobId,$order,$title,$description?:null,$origin,$estimateLow,$estimateHigh,$visible]);$created[]=['id'=>(int)$pdo->lastInsertId(),'title'=>$title];$order+=10;}
    $pdo->prepare('UPDATE work_task_creation_requests SET task_ids_json=? WHERE request_token=?')->execute([json_encode(array_column($created,'id')),$requestToken]);
    $pdo->commit();task_json(201,['ok'=>true,'duplicate'=>false,'tasks'=>$created]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Rapid task creation failed: '.$e->getMessage());task_json(500,['ok'=>false,'error'=>'The task/s could not be saved. No partial batch was kept.']);}
