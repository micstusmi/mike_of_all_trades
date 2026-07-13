<?php

function painting_safe_filename(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return trim($name, '_') ?: 'upload';
}

function painting_save_ai_quote_request(array $post, array $files): array {
    $name = trim($post['name'] ?? '');
    $phone = trim($post['phone'] ?? '');
    $email = trim($post['email'] ?? '');
    $address = trim($post['address'] ?? '');
    $description = trim($post['description'] ?? '');

    if ($name === '' || $phone === '' || $email === '') {
        throw new RuntimeException('Name, phone and email are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Valid email is required.');
    }

    if ($description === '') {
        throw new RuntimeException('Please describe the painting job.');
    }

    $reference = 'PAINT-AI-' . date('Ymd-His') . '-' . random_int(100, 999);
    $baseDir = dirname(__DIR__) . '/painting_ai_uploads';
    $requestDir = $baseDir . '/' . $reference;

    if (!is_dir($requestDir)) {
        mkdir($requestDir, 0775, true);
    }

    file_put_contents($baseDir . '/.htaccess', "Options -Indexes\nphp_flag engine off\n");

    $allowedExt = ['jpg','jpeg','png','webp','pdf'];
    $maxBytes = 12 * 1024 * 1024;
    $saved = [];

    if (!empty($files['uploads']['name'][0])) {
        foreach ($files['uploads']['name'] as $i => $originalName) {
            if (($files['uploads']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            if (($files['uploads']['size'][$i] ?? 0) > $maxBytes) {
                throw new RuntimeException('One uploaded file is too large. Maximum 12MB per file.');
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                throw new RuntimeException('Only JPG, PNG, WEBP and PDF uploads are allowed.');
            }

            $safeName = uniqid('upload_', true) . '_' . painting_safe_filename($originalName);
            $target = $requestDir . '/' . $safeName;

            if (!move_uploaded_file($files['uploads']['tmp_name'][$i], $target)) {
                throw new RuntimeException('Could not save uploaded file.');
            }

            $saved[] = $safeName;
        }
    }

    $request = [
        'reference' => $reference,
        'created_at' => date('c'),
        'customer' => [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address
        ],
        'description' => $description,
        'files' => $saved,
        'status' => 'received_pending_ai_review'
    ];

    file_put_contents(
        $requestDir . '/request.json',
        json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    return [
        'reference' => $reference,
        'saved_files' => $saved
    ];
}
