<?php
require_once __DIR__.'/../../includes/work_tracker.php';
require_once __DIR__.'/_sms_webhook_common.php';

if (!mot_webhook_authorised()) {
    http_response_code(401);
    header('Content-Type: text/plain');
    exit('Unauthorized');
}

$env = mot_webhook_payload();
$flat = mot_webhook_flatten($env['data']);

$to = mot_webhook_first($flat, [
    'to','destination','recipient','recipient_number','destination_number','mobile_to'
]);
$ref = mot_webhook_first($flat, [
    'ref','client_ref','client_reference','reference','metadata.ref'
]);
$smsref = mot_webhook_first($flat, [
    'smsref','message_id','messageid','id','message.id'
]);
$status = mot_webhook_first($flat, [
    'status','delivery_status','message_status','event','event_type','type'
]) ?: 'status_update';

$st=$pdo->prepare("
    INSERT INTO work_sms_gateway_events
    (direction,event_kind,mobile_to,our_ref,smsref,provider_status,provider_response)
    VALUES('status','delivery_status',?,?,?,?,?)
");
$st->execute([
    $to,
    $ref,
    $smsref,
    $status,
    mot_webhook_store_raw($env)
]);

http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
