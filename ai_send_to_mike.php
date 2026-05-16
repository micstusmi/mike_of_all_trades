<?php
header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$chat = trim($_POST['chat'] ?? '');

if (!$chat) {
    echo json_encode([
        'success' => false,
        'message' => 'No chat information was supplied.'
    ]);
    exit;
}

$subject = 'AI Intake Help Request - Mike Of All Trades';

$body =
"AI intake help request received.

Customer name: {$name}
Customer email: {$email}
Customer phone: {$phone}

Conversation:
{$chat}
";

$headers = "From: Mike Of All Trades <mike@mikeofalltrades.com.au>\r\n";
$headers .= "Reply-To: " . ($email ?: "mike@mikeofalltrades.com.au") . "\r\n";

@mail('mike@mikeofalltrades.com.au', $subject, $body, $headers);

echo json_encode([
    'success' => true,
    'message' => 'Thanks — this has been sent to Mike.'
]);