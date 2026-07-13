<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/trip_auth.php';

requireTripLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../documents.php');
    exit;
}

$tripId = (int) $_SESSION['trip_id'];
$memberId = (int) $_SESSION['trip_member_id'];

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$weekId = (int) ($_POST['trip_week_id'] ?? 0);

$file = $_FILES['document'] ?? null;

if (
    $title === ''
    || !$file
    || $file['error'] !== UPLOAD_ERR_OK
) {
    $_SESSION['document_error'] =
        'Please select a valid document to upload.';

    header('Location: ../documents.php');
    exit;
}

if ((int) $file['size'] > 10 * 1024 * 1024) {
    $_SESSION['document_error'] =
        'The file is larger than the 10 MB limit.';

    header('Location: ../documents.php');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowedTypes[$mimeType])) {
    $_SESSION['document_error'] =
        'Only PDF, JPG, PNG and WEBP files are allowed.';

    header('Location: ../documents.php');
    exit;
}

if ($weekId) {
    $weekStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM trip_weeks
        WHERE id = ?
          AND trip_id = ?
    ");

    $weekStmt->execute([
        $weekId,
        $tripId
    ]);

    if ((int) $weekStmt->fetchColumn() === 0) {
        $weekId = 0;
    }
}

$storageDirectory =
    dirname(__DIR__) . '/storage/documents';

if (!is_dir($storageDirectory)) {
    mkdir($storageDirectory, 0750, true);
}

$storedFilename =
    bin2hex(random_bytes(24))
    . '.'
    . $allowedTypes[$mimeType];

$destination =
    $storageDirectory . '/' . $storedFilename;

if (!move_uploaded_file(
    $file['tmp_name'],
    $destination
)) {
    $_SESSION['document_error'] =
        'The file could not be stored.';

    header('Location: ../documents.php');
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO trip_documents (
        trip_id,
        trip_week_id,
        title,
        description,
        original_filename,
        stored_filename,
        mime_type,
        file_size,
        uploaded_by_member_id
    ) VALUES (
        :trip_id,
        :week_id,
        :title,
        :description,
        :original_filename,
        :stored_filename,
        :mime_type,
        :file_size,
        :member_id
    )
");

$stmt->execute([
    'trip_id' => $tripId,
    'week_id' => $weekId ?: null,
    'title' => $title,
    'description' => $description ?: null,
    'original_filename' => basename($file['name']),
    'stored_filename' => $storedFilename,
    'mime_type' => $mimeType,
    'file_size' => (int) $file['size'],
    'member_id' => $memberId
]);

$_SESSION['document_message'] =
    'Document uploaded successfully.';

header('Location: ../documents.php');
exit;
