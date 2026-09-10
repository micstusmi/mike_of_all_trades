<?php
require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

function fail_retry(string $message, int $status=400): never {
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;max-width:800px;margin:40px auto;padding:20px}</style>';
    echo '<h1>AI retry failed</h1><p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>';
    exit;
}

function response_text(array $data): string {
    $out = '';
    foreach (($data['output'] ?? []) as $item) {
        foreach (($item['content'] ?? []) as $c) {
            if (isset($c['text']) && is_string($c['text'])) {
                $out .= $c['text'];
            }
        }
    }
    return trim($out);
}

$jobId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($jobId <= 0) {
    fail_retry('Missing job ID.');
}

$job = wt_job($pdo, $jobId);
if (!$job) {
    fail_retry('Job not found.', 404);
}

$apiKey = wt_env('OPENAI_API_KEY');
if (!$apiKey) {
    fail_retry('OPENAI_API_KEY is still not available.');
}

$stmt = $pdo->prepare('SELECT * FROM work_job_intake_files WHERE job_id=? ORDER BY id');
$stmt->execute([$jobId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$files) {
    fail_retry('No preserved intake files were found for this job.');
}

$base = wt_env(
    'WORKTRACKER_PRIVATE_UPLOAD_DIR',
    dirname(__DIR__, 2) . '/storage/private/job_intake'
);

$content = [];

$content[] = [
    'type' => 'input_text',
    'text' => <<<TXT
You are extracting a customer's requested work from screenshots or PDFs for Mike of All Trades.

Return JSON only in this exact structure:

{
  "organisation": "",
  "site": "",
  "items": [
    {
      "request_text": "",
      "needs_clarification": false,
      "clarification_reason": ""
    }
  ]
}

Rules:
- Preserve the customer's wording and meaning as closely as practical.
- Extract each distinct requested job/item separately.
- Do not invent missing work.
- If an item is ambiguous or only references another ticket/document, set needs_clarification=true.
- Do not duplicate the same request if it appears in overlapping screenshots.
- This customer may be a showroom/product supplier, so do not assume live plumbing or electrical work unless explicitly stated.
TXT
];

foreach ($files as $f) {
    $relative = ltrim((string)$f['relative_path'], '/');
    $path = rtrim($base, '/') . '/' . $relative;

    if (!is_file($path)) {
        continue;
    }

    $mime = (string)$f['mime_type'];
    $data = base64_encode(file_get_contents($path));

    if (str_starts_with($mime, 'image/')) {
        $content[] = [
            'type' => 'input_image',
            'image_url' => 'data:' . $mime . ';base64,' . $data
        ];
    } elseif ($mime === 'application/pdf') {
        $content[] = [
            'type' => 'input_file',
            'filename' => (string)$f['original_name'],
            'file_data' => 'data:application/pdf;base64,' . $data
        ];
    }
}

$payload = [
    'model' => wt_env('WORKTRACKER_AI_MODEL', 'gpt-5.6-luna'),
    'input' => [[
        'role' => 'user',
        'content' => $content
    ]]
];

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 120
]);

$raw = curl_exec($ch);

if ($raw === false) {
    fail_retry('OpenAI request failed: ' . curl_error($ch), 500);
}

$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($raw, true);

if ($http < 200 || $http >= 300) {
    $message = $data['error']['message'] ?? ('OpenAI returned HTTP ' . $http);
    fail_retry($message, 500);
}

$text = response_text($data);
$text = trim($text);

if (str_starts_with($text, '```')) {
    $text = preg_replace('/^```(?:json)?\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
}

$extracted = json_decode($text, true);

if (!is_array($extracted) || !isset($extracted['items']) || !is_array($extracted['items'])) {
    fail_retry('AI returned an unexpected extraction format.', 500);
}

$pdo->beginTransaction();

try {
    $pdo->prepare('DELETE FROM work_job_intake_items WHERE job_id=?')->execute([$jobId]);

    $ins = $pdo->prepare(
        'INSERT INTO work_job_intake_items
        (job_id,item_order,request_text,needs_clarification,clarification_reason)
        VALUES(?,?,?,?,?)'
    );

    $order = 10;

    foreach ($extracted['items'] as $item) {
        $request = trim((string)($item['request_text'] ?? ''));

        if ($request === '') {
            continue;
        }

        $ins->execute([
            $jobId,
            $order,
            $request,
            !empty($item['needs_clarification']) ? 1 : 0,
            trim((string)($item['clarification_reason'] ?? ''))
        ]);

        $order += 10;
    }

    $pdo->prepare(
        "UPDATE work_jobs
         SET ai_breakdown_status='complete',
             ai_breakdown_error=NULL
         WHERE id=?"
    )->execute([$jobId]);

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    fail_retry($e->getMessage(), 500);
}

header('Location: ../../admin/work/intake_review.php?id=' . $jobId . '&extracted=1');
exit;
