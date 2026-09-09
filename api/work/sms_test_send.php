<?php
session_start();
require_once __DIR__.'/../../includes/work_tracker.php';
require_once __DIR__.'/../../includes/sms_broadcast.php';
$role=$_SESSION['user_role']??$_SESSION['role']??null;
if($role!=='admin'){http_response_code(403);die('Admin login required.');}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);die('POST required.');}

$mobile=trim((string)($_POST['mobile']??''));
$message=trim((string)($_POST['message']??''));
$ref=mot_sms_ref('test');
$result=mot_sms_broadcast_send($mobile,$message,$ref);

$st=$pdo->prepare("INSERT INTO work_sms_gateway_events(direction,event_kind,mobile_to,message,our_ref,smsref,provider_status,provider_response) VALUES('outbound','manual_test',?,?,?,?,?,?)");
$st->execute([$result['to'],$message,$result['ref'],$result['smsref'],$result['status'],$result['response']??$result['error']]);

?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMS Test Result</title><style>body{font-family:system-ui;padding:25px;max-width:800px;margin:auto}.ok{background:#eaf7ee;padding:18px;border-radius:12px}.bad{background:#fff0f0;padding:18px;border-radius:12px}code{word-break:break-word}</style></head><body>
<div class="<?=$result['ok']?'ok':'bad'?>">
<h1><?=$result['ok']?'✓ SMS accepted by gateway':'SMS was not accepted'?></h1>
<p><b>To:</b> <?=htmlspecialchars($result['to'])?></p>
<p><b>Our ref:</b> <?=htmlspecialchars((string)$result['ref'])?></p>
<p><b>Status:</b> <?=htmlspecialchars((string)($result['status']??''))?></p>
<?php if($result['smsref']):?><p><b>Gateway ref:</b> <?=htmlspecialchars((string)$result['smsref'])?></p><?php endif;?>
<?php if($result['error']):?><p><b>Error:</b> <?=htmlspecialchars((string)$result['error'])?></p><?php endif;?>
<p><b>Raw response:</b> <code><?=htmlspecialchars((string)($result['response']??''))?></code></p>
</div><p><a href="../../admin/work/sms_test.php">← Back to SMS test</a></p></body></html>
