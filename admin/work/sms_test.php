<?php
session_start();
require_once __DIR__.'/../../includes/work_tracker.php';
require_once __DIR__.'/../../includes/sms_broadcast.php';
$role=$_SESSION['user_role']??$_SESSION['role']??null;
if($role!=='admin'){http_response_code(403);die('Admin login required.');}
$enabled=mot_sms_enabled();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMS Broadcast Test</title><style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;margin:0;color:#17202a}.wrap{max-width:760px;margin:auto;padding:18px}
.card{background:#fff;border-radius:14px;padding:18px;margin:14px 0;box-shadow:0 2px 10px #0001}
input,textarea{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ccd1d5;border-radius:8px;font:inherit}
.btn{border:0;border-radius:10px;padding:12px 15px;background:#17202a;color:#fff;font-weight:800;cursor:pointer}
.good{background:#eaf7ee;border:1px solid #8fd19e}.warn{background:#fff7df;border:1px solid #e8c96d}.small{font-size:13px;color:#667085}
</style></head><body><div class="wrap">
<p><a href="index.php">← Work Tracker</a></p><h1>SMS Broadcast test</h1>
<div class="card <?=$enabled?'good':'warn'?>">
<b>Gateway sending: <?=$enabled?'ENABLED':'OFF'?></b>
<p class="small">This page sends one controlled test. It does not yet wire Start/Stop automatically.</p>
</div>
<div class="card"><form method="post" action="../../api/work/sms_test_send.php">
<label><b>Mobile</b></label><input name="mobile" required placeholder="04xx xxx xxx"><br><br>
<label><b>Message</b></label><textarea name="message" rows="4" required>Mike Of All Trades SMS test. If you receive this, the website SMS connection is working.</textarea><br><br>
<button class="btn">SEND ONE TEST SMS</button>
</form></div>
</div></body></html>
