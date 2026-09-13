<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/zoho_functions.php';

const AI_QUOTE_MAX_ATTACHMENTS = 15;
const AI_QUOTE_MAX_FILE_BYTES = 10 * 1024 * 1024;
const AI_QUOTE_MAX_TOTAL_BYTES = 25 * 1024 * 1024;
const AI_QUOTE_MAX_ESTIMATE_JSON_BYTES = 200 * 1024;

function normaliseAiQuoteFiles(array $fileBag): array
{
    if (!isset($fileBag['name'])) {
        return [];
    }

    if (!is_array($fileBag['name'])) {
        return [$fileBag];
    }

    $files = [];
    $count = count($fileBag['name']);

    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name' => $fileBag['name'][$i] ?? '',
            'type' => $fileBag['type'][$i] ?? '',
            'tmp_name' => $fileBag['tmp_name'][$i] ?? '',
            'error' => $fileBag['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileBag['size'][$i] ?? 0
        ];
    }

    return $files;
}

function validateAiQuoteAttachments(array $files): array
{
    $files = array_values(array_filter(
        $files,
        fn($file) =>
            ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE
    ));

    if (count($files) > AI_QUOTE_MAX_ATTACHMENTS) {
        throw new RuntimeException(
            'Please attach no more than 15 files to a quote.'
        );
    }

    $validated = [];
    $totalBytes = 0;

    foreach ($files as $file) {

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'One of the quote attachments could not be received.'
            );
        }

        $size = (int)($file['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException(
                'One of the quote attachments is empty.'
            );
        }

        if ($size > AI_QUOTE_MAX_FILE_BYTES) {
            throw new RuntimeException(
                'Each quote attachment must be 10 MB or smaller.'
            );
        }

        $tmpPath = $file['tmp_name'] ?? '';

        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException(
                'One of the quote attachments could not be verified.'
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        $allowed = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf'
        ];

        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException(
                'Quote attachments must be JPG, PNG, WEBP or PDF files.'
            );
        }

        $totalBytes += $size;

        if ($totalBytes > AI_QUOTE_MAX_TOTAL_BYTES) {
            throw new RuntimeException(
                'The combined quote attachments are too large. Please keep the total below 25 MB.'
            );
        }

        $safeName = preg_replace(
            '/[^A-Za-z0-9._ -]/',
            '_',
            basename((string)($file['name'] ?? 'attachment'))
        );

        $validated[] = [
            'name' => $safeName ?: 'attachment',
            'mime' => $mime,
            'tmp_name' => $tmpPath,
            'size' => $size
        ];
    }

    return $validated;
}


function validateStructuredEstimateJson(string $raw): array
{
    $raw = trim($raw);

    if ($raw === '') {
        return [];
    }

    if (strlen($raw) > AI_QUOTE_MAX_ESTIMATE_JSON_BYTES) {
        throw new RuntimeException(
            'The detailed estimate data is unexpectedly large. Please reopen the quote form and try again.'
        );
    }

    $estimate = json_decode($raw, true);

    if (!is_array($estimate)) {
        throw new RuntimeException(
            'The detailed estimate data could not be read.'
        );
    }

    if (!isset($estimate['tasks']) || !is_array($estimate['tasks'])) {
        throw new RuntimeException(
            'The detailed estimate is missing its task breakdown.'
        );
    }

    return $estimate;
}

function structuredEstimateTaskSummary(array $estimate): string
{
    $lines = [];

    foreach (($estimate['tasks'] ?? []) as $task) {
        if (!is_array($task)) continue;

        $title = trim((string)($task['title'] ?? ''));
        if ($title === '') continue;

        $likely = 0.0;
        foreach (($task['components'] ?? []) as $component) {
            if (is_array($component) && is_numeric($component['likely_hours'] ?? null)) {
                $likely += max(0, (float)$component['likely_hours']);
            }
        }

        $lines[] = '- ' . $title . ': approx. ' . round($likely, 2) . ' labour hours';
    }

    $projectLikely = 0.0;
    foreach (($estimate['project_components'] ?? []) as $component) {
        if (is_array($component) && is_numeric($component['likely_hours'] ?? null)) {
            $projectLikely += max(0, (float)$component['likely_hours']);
        }
    }

    if ($projectLikely > 0) {
        $lines[] = '- Shared project planning/logistics: approx. ' . round($projectLikely, 2) . ' labour hours';
    }

    return implode("\n", $lines);
}

function saveStructuredAiQuote(
    PDO $pdo,
    string $conversationToken,
    array $estimate,
    string $estimateId,
    float $hours,
    float $price
): void {
    if ($conversationToken === '' || empty($estimate)) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id FROM ai_conversations WHERE conversation_token = ? LIMIT 1'
        );
        $stmt->execute([$conversationToken]);
        $conversationId = $stmt->fetchColumn();

        if (!$conversationId) {
            return;
        }

        $package = [
            'structured_estimate' => $estimate,
            'formal_hours' => $hours,
            'formal_price' => $price,
            'saved_at' => date('c')
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO ai_quotes (conversation_id, estimate_json, zoho_estimate_id, approved) VALUES (?, ?, ?, 0)'
        );
        $stmt->execute([
            (int)$conversationId,
            json_encode($package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $estimateId
        ]);
    } catch (Throwable $e) {
        // Quote delivery must not fail merely because optional structured archive storage failed.
        error_log('Structured AI quote archive skipped: ' . $e->getMessage());
    }
}

/**
 * ---------------------------------------------------------
 * DUPLICATE QUOTE SUBMISSION PROTECTION
 * ---------------------------------------------------------
 *
 * The lock stores only a tiny JSON record (no photos/PDFs).
 * An identical quote request is treated as already sent for 24 hours.
 */
const AI_QUOTE_IDEMPOTENCY_TTL = 86400; // 24 hours

function buildAiQuoteSubmissionKey(
    string $conversationToken,
    string $email,
    string $notes,
    float $hours,
    float $price,
    array $attachments,
    string $structuredEstimateJson = ''
): string {
    $fileParts = [];

    foreach ($attachments as $attachment) {
        $fileParts[] =
            ($attachment['name'] ?? '') . ':' .
            ($attachment['size'] ?? 0) . ':' .
            ($attachment['mime'] ?? '');
    }

    return hash(
        'sha256',
        implode('|', [
            $conversationToken,
            strtolower($email),
            $notes,
            number_format($hours, 2, '.', ''),
            number_format($price, 2, '.', ''),
            implode(',', $fileParts),
            hash('sha256', $structuredEstimateJson)
        ])
    );
}

function openAiQuoteSubmissionLock(string $submissionKey): array
{
    $dir = __DIR__ . '/quote_submission_locks';

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(
                'Could not create quote submission lock directory.'
            );
        }
    }

    $path = $dir . '/ai_' . $submissionKey . '.json';
    $handle = fopen($path, 'c+');

    if (!$handle) {
        throw new RuntimeException(
            'Could not create quote submission protection lock.'
        );
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);

        throw new RuntimeException(
            'Could not lock quote submission.'
        );
    }

    rewind($handle);
    $raw = stream_get_contents($handle);
    $existing = $raw ? json_decode($raw, true) : null;

    if (
        is_array($existing) &&
        !empty($existing['success']) &&
        !empty($existing['estimate_id']) &&
        (time() - (int)($existing['timestamp'] ?? 0)) < AI_QUOTE_IDEMPOTENCY_TTL
    ) {
        return [
            'handle' => $handle,
            'path' => $path,
            'duplicate' => true,
            'existing' => $existing
        ];
    }

    /*
     * If an old completed record exists, overwrite it when this
     * new submission succeeds.
     */
    return [
        'handle' => $handle,
        'path' => $path,
        'duplicate' => false,
        'existing' => null
    ];
}

function completeAiQuoteSubmissionLock(
    $handle,
    array $result
): void {
    ftruncate($handle, 0);
    rewind($handle);

    fwrite(
        $handle,
        json_encode(
            $result,
            JSON_UNESCAPED_SLASHES
        )
    );

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function releaseAiQuoteSubmissionLock($handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request.');
    }

    $email = trim($_POST['email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $hours = (float)($_POST['hours'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $conversationToken =
        trim($_POST['conversation_token'] ?? '');
    $structuredEstimateRaw =
        trim((string)($_POST['structured_estimate_json'] ?? ''));
    $structuredEstimate =
        validateStructuredEstimateJson($structuredEstimateRaw);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception(
            'A valid customer email is required.'
        );
    }

    if (!$notes) {
        throw new Exception(
            'Quote notes are required.'
        );
    }

    if ($price <= 0) {
        throw new Exception(
            'Estimated price must be greater than zero.'
        );
    }

    $incomingFiles =
        isset($_FILES['attachments'])
            ? normaliseAiQuoteFiles(
                $_FILES['attachments']
            )
            : [];

    $attachments =
        validateAiQuoteAttachments(
            $incomingFiles
        );

    $submissionKey =
        buildAiQuoteSubmissionKey(
            $conversationToken,
            $email,
            $notes,
            $hours,
            $price,
            $attachments,
            $structuredEstimateRaw
        );

    $submissionLock =
        openAiQuoteSubmissionLock(
            $submissionKey
        );

    $submissionLockHandle =
        $submissionLock['handle'];

    if (!empty($submissionLock['duplicate'])) {
        $existing =
            $submissionLock['existing'];

        releaseAiQuoteSubmissionLock(
            $submissionLockHandle
        );

        echo json_encode([
            'success' => true,
            'duplicate_prevented' => true,
            'estimate_id' => $existing['estimate_id'],
            'attachment_count' => (int)($existing['attachment_count'] ?? 0)
        ]);
        exit;
    }

    $name = 'AI Quote Customer';
    $phone = '';
    $address = '';

    $conversationLink = '';

    if ($conversationToken) {
        $conversationLink =
            'https://mikeofalltrades.com.au/view_ai_conversation.php?token=' .
            urlencode($conversationToken);
    }

    $description =
        "AI-assisted quote request\n\n" .
        $notes . "\n\n" .
        "Estimated labour hours: " . $hours . "\n\n" .
        "This quotation is based on the information, photographs, plans and documents supplied at the time of quotation. " .
        "Any material change to the supplied information, site conditions, scope, plans or specifications may require a revised quotation or variation.\n\n" .
        "Estimated pricing and timeframes are a guide only. Final pricing may vary depending on materials, access, existing conditions, and any unexpected issues discovered during the job.";

    $taskSummary = structuredEstimateTaskSummary($structuredEstimate);

    if ($taskSummary !== '') {
        $description .=
            "\n\nLabour planning summary:\n" .
            $taskSummary;
    }

    if (!empty($attachments)) {
        $description .=
            "\n\nCustomer-supplied source files included with quote email:\n";

        foreach ($attachments as $attachment) {
            $description .=
                "- " .
                $attachment['name'] .
                "\n";
        }
    }

    if ($conversationLink) {
        $description .=
            "\n\nAI conversation link:\n" .
            $conversationLink;
    }

    $customer_id =
        getOrCreateZohoCustomer(
            $name,
            $email,
            $phone,
            $address
        );

    if (!$customer_id) {
        throw new Exception(
            'Failed to create or find Zoho customer.'
        );
    }

    $estimate =
        createZohoEstimate(
            $customer_id,
            $name,
            $description,
            $price
        );

    if (($estimate['code'] ?? 0) >= 400) {
        throw new Exception(
            'Zoho estimate failed: ' .
            ($estimate['raw'] ?? 'Unknown error')
        );
    }

    $estimate_id =
        $estimate['json']['estimate']['estimate_id']
        ?? null;

    if (!$estimate_id) {
        throw new Exception(
            'No Zoho estimate ID returned.'
        );
    }

    /*
     * The original uploaded browser files exist only in PHP's
     * temporary upload area for this request. They are forwarded
     * directly to the Zoho estimate-email endpoint and are not
     * copied into permanent web-server storage.
     */
    $send =
        sendZohoEstimateWithAttachments(
            $estimate_id,
            $email,
            $attachments
        );

    if (($send['code'] ?? 0) >= 400) {
        error_log(
            'Zoho estimate ' .
            $estimate_id .
            ' created but attachment email failed: ' .
            ($send['raw'] ?? 'Unknown error')
        );

        throw new Exception(
            'Estimate was created in Zoho, but the email with the supplied files failed. ' .
            'Please do not submit a duplicate quote yet; Mike can review the created estimate.'
        );
    }

    saveStructuredAiQuote(
        $pdo,
        $conversationToken,
        $structuredEstimate,
        (string)$estimate_id,
        $hours,
        $price
    );

    completeAiQuoteSubmissionLock(
        $submissionLockHandle,
        [
            'success' => true,
            'timestamp' => time(),
            'estimate_id' => $estimate_id,
            'attachment_count' => count($attachments)
        ]
    );

    echo json_encode([
        'success' => true,
        'duplicate_prevented' => false,
        'estimate_id' => $estimate_id,
        'attachment_count' => count($attachments)
    ]);

} catch (Throwable $e) {

    if (
        isset($submissionLockHandle) &&
        is_resource($submissionLockHandle)
    ) {
        releaseAiQuoteSubmissionLock(
            $submissionLockHandle
        );
    }

    error_log(
        'generate_quote_request.php error: ' .
        $e->getMessage()
    );

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
