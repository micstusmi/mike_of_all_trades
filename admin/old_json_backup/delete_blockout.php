<?php

require 'auth.php';

header('Content-Type: application/json');

$file = __DIR__ . '/blockouts.json';

if(!file_exists($file)){

    echo json_encode([
        'success' => false
    ]);

    exit;
}

$events = json_decode(
    file_get_contents($file),
    true
) ?: [];

$id = $_POST['id'] ?? '';

$newEvents = [];

foreach($events as $event){

    if((string)$event['id'] !== (string)$id){

        $newEvents[] = $event;
    }
}

file_put_contents(
    $file,
    json_encode($newEvents, JSON_PRETTY_PRINT)
);

echo json_encode([
    'success' => true
]);