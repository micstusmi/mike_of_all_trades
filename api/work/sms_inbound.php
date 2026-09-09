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

$from = mot_webhook_first($flat, [
    'from','originator','source','sender','sender_number','source_number','mobile_from'
]);
$to = mot_webhook_first($flat, [
    'to','destination','recipient','recipient_number','destination_number','mobile_to'
]);
$message = mot_webhook_first($flat, [
    'message','body','text','content','message_text','payload'
]);
$smsref = mot_webhook_first($flat, [
    'smsref','message_id','messageid','id','message.id'
]);
$event = mot_webhook_event_name($flat) ?: 'customer_reply';

$st=$pdo->prepare("
    INSERT INTO work_sms_gateway_events
    (direction,event_kind,mobile_to,mobile_from,message,smsref,provider_status,provider_response)
    VALUES('inbound',?,?,?,?,?,?,?)
");
$st->execute([
    $event,
    $to,
    $from,
    $message,
    $smsref,
    $event,
    mot_webhook_store_raw($env)
]);

http_response_code(200);
header('Content-Type: text/plain');
echo "OK";
