<?php

require_once __DIR__ . '/db.php';

function ai_safe_filename(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return trim($name, '_') ?: 'upload';
}

function ai_new_reference(string $mode = 'general'): string {
    return 'AI-' . strtoupper($mode) . '-' . date('Ymd-His') . '-' . random_int(100, 999);
}

function ai_db(): PDO {
    return getDbConnection();
}

function ai_create_conversation(string $mode = 'painting'): array {
    $pdo = ai_db();
    $reference = ai_new_reference($mode);

    $stmt = $pdo->prepare("
        INSERT INTO ai_conversations (reference, service, status)
        VALUES (?, ?, 'started')
    ");
    $stmt->execute([$reference, $mode]);

    return ai_load_conversation($reference);
}

function ai_load_conversation(string $reference): ?array {
    $pdo = ai_db();

    $stmt = $pdo->prepare("SELECT * FROM ai_conversations WHERE reference = ?");
    $stmt->execute([$reference]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT question_id, answer_json FROM ai_answers WHERE conversation_id = ?");
    $stmt->execute([$conversation['id']]);

    $answers = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $answers[$row['question_id']] = json_decode($row['answer_json'], true);
    }

    $conversation['answers'] = $answers;

    return $conversation;
}

function ai_save_answer(string $reference, string $questionId, $answer): void {
    $pdo = ai_db();
    $conversation = ai_load_conversation($reference);

    if (!$conversation) {
        throw new RuntimeException('Conversation not found.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO ai_answers (conversation_id, question_id, answer_json)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE answer_json = VALUES(answer_json)
    ");

    $stmt->execute([
        $conversation['id'],
        $questionId,
        json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);

    $stmt = $pdo->prepare("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$conversation['id']]);
}

function ai_add_message(string $reference, string $role, string $message): void {
    $pdo = ai_db();
    $conversation = ai_load_conversation($reference);

    if (!$conversation) {
        throw new RuntimeException('Conversation not found.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO ai_messages (conversation_id, role, message)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([$conversation['id'], $role, $message]);
}

function ai_save_upload_record(string $reference, string $original, string $saved, string $mime, int $size, string $path): void {
    $pdo = ai_db();
    $conversation = ai_load_conversation($reference);

    if (!$conversation) {
        throw new RuntimeException('Conversation not found.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO ai_uploads
        (conversation_id, original_filename, saved_filename, mime_type, file_size, storage_path)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $conversation['id'],
        $original,
        $saved,
        $mime,
        $size,
        $path
    ]);
}
