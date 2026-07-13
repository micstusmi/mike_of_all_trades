<?php
require_once __DIR__ . '/includes/ai_estimator_config.php';
require_once __DIR__ . '/includes/ai_estimator_storage.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $reference = trim($_POST['reference'] ?? '');

    if ($reference === '') {
        throw new RuntimeException('Missing conversation reference.');
    }

    $conversation = ai_load_conversation($reference);

    if (!$conversation) {
        throw new RuntimeException('Conversation not found.');
    }

    $uploadBase = rtrim($aiEstimatorConfig['upload_dir'], '/') . '/' . $reference;

    if (!is_dir($uploadBase)) {
        mkdir($uploadBase, 0775, true);
    }

    $allowedExt = $aiEstimatorConfig['allowed_upload_extensions'];
    $maxBytes = ((int)$aiEstimatorConfig['max_upload_size_mb']) * 1024 * 1024;
    $savedFiles = [];

    if (!empty($_FILES['uploads']['name'][0])) {
        foreach ($_FILES['uploads']['name'] as $i => $originalName) {
            if ($_FILES['uploads']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            if ($_FILES['uploads']['size'][$i] > $maxBytes) {
                throw new RuntimeException('One uploaded file is too large.');
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                throw new RuntimeException('Only JPG, PNG, WEBP and PDF uploads are allowed.');
            }

            $safeName = uniqid('upload_', true) . '_' . ai_safe_filename($originalName);
            $target = $uploadBase . '/' . $safeName;

            if (!move_uploaded_file($_FILES['uploads']['tmp_name'][$i], $target)) {
                throw new RuntimeException('Could not save uploaded file.');
            }

            $savedFiles[] = $safeName;
        }
    }

    $conversation['uploads'] = $conversation['uploads'] ?? [];
    $conversation['uploads'] = array_merge($conversation['uploads'], $savedFiles);
    $conversation['updated_at'] = date('c');

    ai_save_conversation($conversation);

    echo json_encode([
        'success' => true,
        'reference' => $reference,
        'saved_files' => $savedFiles
    ]);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
