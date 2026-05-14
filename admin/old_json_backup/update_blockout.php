<?php

require 'auth.php';

header('Content-Type: application/json');

$file = __DIR__ . '/blockouts.json';

$events = json_decode(
    file_get_contents($file),
    true
) ?: [];

$id = $_POST['id'] ?? '';

foreach($events as &$event){

    if((string)$event['id'] === (string)$id){

        $type = $_POST['type'] ?? 'personal';

        $bg = $type === 'work'
            ? '#f39200'
            : '#dc3545';

        $event['title'] = $_POST['title'];
        $event['start'] = $_POST['start'];
        $event['end'] = $_POST['end'];
        $event['notes'] = $_POST['notes'];
        $event['type'] = $type;
        $event['backgroundColor'] = $bg;
        $event['borderColor'] = $bg;
    }
}

file_put_contents(
    $file,
    json_encode($events, JSON_PRETTY_PRINT)
);

echo json_encode([
    'success' => true
]);