<?php
require '../includes/auth_user.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $userId = (int)$_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        throw new Exception('Missing booking ID.');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM calendar_events
        WHERE id = ?
        AND customer_id = ?
        AND is_buffer = 0
        LIMIT 1
    ");

    $stmt->execute([$id, $userId]);

    if (!$stmt->fetch()) {
        throw new Exception('You can only delete your own booking.');
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        DELETE FROM calendar_events
        WHERE parent_event_id = ?
        AND is_buffer = 1
    ")->execute([$id]);

    $pdo->prepare("
        DELETE FROM calendar_events
        WHERE id = ?
        AND customer_id = ?
        AND is_buffer = 0
    ")->execute([$id, $userId]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}