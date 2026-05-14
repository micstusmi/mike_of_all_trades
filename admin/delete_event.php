<?php
require '../includes/auth_admin.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        throw new Exception('Missing event ID.');
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        DELETE FROM calendar_events
        WHERE parent_event_id = ?
    ")->execute([$id]);

    $pdo->prepare("
        DELETE FROM calendar_events
        WHERE id = ?
    ")->execute([$id]);

    $pdo->commit();

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}