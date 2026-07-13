<?php

$aiEstimatorConfig = [
    'app_name' => "Mike's AI Estimator",
    'default_mode' => 'painting',

    'allowed_modes' => [
        'painting'
    ],

    'upload_dir' => __DIR__ . '/../ai_uploads',
    'conversation_dir' => __DIR__ . '/../ai_conversations',

    'allowed_upload_extensions' => [
        'jpg', 'jpeg', 'png', 'webp', 'pdf'
    ],

    'max_upload_size_mb' => 12
];
