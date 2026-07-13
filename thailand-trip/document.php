<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/trip_auth.php';

requireTripLogin();

$tripId = (int) $_SESSION['trip_id'];
$documentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$documentId) {
    http_response_code(400);
    exit('Invalid document.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM trip_documents
    WHERE id = ?
      AND trip_id = ?
    LIMIT 1
");

$stmt->execute([
    $documentId,
    $tripId
]);

$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$storageDirectory =
    realpath(__DIR__ . '/storage/documents');

$filePath = realpath(
    __DIR__
    . '/storage/documents/'
    . $document['stored_filename']
);

if (
    !$storageDirectory
    || !$filePath
    || !str_starts_with(
        $filePath,
        $storageDirectory . DIRECTORY_SEPARATOR
    )
    || !is_file($filePath)
) {
    http_response_code(404);
    exit('Stored file not found.');
}

$downloadName = preg_replace(
    '/[^A-Za-z0-9._ -]/',
    '_',
    $document['original_filename']
);

header('Content-Type: ' . $document['mime_type']);
header(
    'Content-Length: '
    . filesize($filePath)
);
header(
    'Content-Disposition: attachment; filename="'
    . $downloadName
    . '"'
);
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
