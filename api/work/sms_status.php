<?php
require_once __DIR__ . '/../../includes/work_tracker.php';
$ref=trim($_GET['ref']??''); $smsref=trim($_GET['smsref']??''); $status=trim($_GET['status']??'');
$stmt=$pdo->prepare("UPDATE work_sms_messages SET provider_ref=COALESCE(NULLIF(?,''),provider_ref), delivery_status=?, raw_payload=? WHERE local_ref=?");
$stmt->execute([$smsref,$status,json_encode($_GET),$ref]);
http_response_code(200); echo "OK";
