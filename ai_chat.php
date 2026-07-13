<?php
require_once __DIR__ . '/includes/ai_estimator_config.php';
require_once __DIR__ . '/includes/ai_estimator_storage.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON.');
    }

    $mode = $input['mode'] ?? $aiEstimatorConfig['default_mode'];

    if (!in_array($mode, $aiEstimatorConfig['allowed_modes'], true)) {
        throw new RuntimeException('Unsupported AI mode.');
    }

    $reference = trim($input['reference'] ?? '');

    if ($reference === '') {
        $conversation = ai_create_conversation($mode);
        $reference = $conversation['reference'];
    } else {
        $conversation = ai_load_conversation($reference);
        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }
    }

    if (!empty($input['answer_id'])) {
        ai_save_answer($reference, $input['answer_id'], $input['answer'] ?? '');
        ai_add_message(
            $reference,
            'customer',
            is_array($input['answer'] ?? null)
                ? implode(', ', $input['answer'])
                : (string)($input['answer'] ?? '')
        );
        $conversation = ai_load_conversation($reference);
    }

    $modeFile = __DIR__ . '/includes/ai_modes/' . basename($mode) . '.php';

    if (!file_exists($modeFile)) {
        throw new RuntimeException('AI mode not found.');
    }

    $modeConfig = require $modeFile;
    $questions = $modeConfig['starter_questions'];
    $answers = $conversation['answers'] ?? [];

    $nextQuestion = null;

    foreach ($questions as $q) {
        if (!array_key_exists($q['id'], $answers)) {
            $nextQuestion = $q;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'reference' => $reference,
        'conversation_complete' => $nextQuestion === null,
        'welcome_message' => $modeConfig['welcome_message'],
        'next_question' => $nextQuestion,
        'answers' => $answers
    ]);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
