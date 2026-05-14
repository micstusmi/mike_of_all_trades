<?php
header('Content-Type: application/json');

$file = __DIR__ . '/blockouts.json';

if(!file_exists($file)){
    echo json_encode([]);
    exit;
}

$data = json_decode(file_get_contents($file), true);

echo json_encode($data ?: []);