<?php

$file = __DIR__ . '/blockouts.json';

$data = [
    [
        'title' => 'TEST',
        'start' => '2026-05-14 09:00',
        'end' => '2026-05-14 10:00'
    ]
];

$result = file_put_contents(
    $file,
    json_encode($data, JSON_PRETTY_PRINT)
);

var_dump($result);