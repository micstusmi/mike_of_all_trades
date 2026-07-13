<?php
require_once __DIR__ . '/includes/painting_upload.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $result = painting_save_ai_quote_request($_POST, $_FILES);

    echo json_encode([
        'success' => true,
        'message' => 'AI painting quote request received.',
        'reference' => $result['reference'],
        'saved_files' => $result['saved_files']
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
