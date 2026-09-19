<?php
declare(strict_types=1);

require_once __DIR__ . '/work_tracker.php';
require_once __DIR__ . '/work_media.php';

function wtu_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    exit($message);
}

function wtu_response_text(array $data): string
{
    $text = '';
    foreach (($data['output'] ?? []) as $output) {
        foreach (($output['content'] ?? []) as $content) {
            if (isset($content['text']) && is_string($content['text'])) {
                $text .= $content['text'];
            }
        }
    }
    return trim($text);
}

function wtu_normalise_files_array(array $files): array
{
    if (!isset($files['name'])) return [];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $rows = [];
    foreach ($names as $i => $name) {
        $rows[] = [
            'name' => (string)$name,
            'type' => (string)(is_array($files['type'] ?? null) ? ($files['type'][$i] ?? '') : ($files['type'] ?? '')),
            'tmp_name' => (string)(is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$i] ?? '') : ($files['tmp_name'] ?? '')),
            'error' => (int)(is_array($files['error'] ?? null) ? ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : ($files['error'] ?? UPLOAD_ERR_NO_FILE)),
            'size' => (int)(is_array($files['size'] ?? null) ? ($files['size'][$i] ?? 0) : ($files['size'] ?? 0)),
        ];
    }
    return $rows;
}

function wtu_safe_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'task-list-file';
    return mb_substr($name, 0, 180);
}

function wtu_store_files(PDO $pdo, int $requestId, int $jobId, array $upload): array
{
    $rows = wtu_normalise_files_array($upload);
    $usable = array_values(array_filter($rows, static fn(array $row): bool => $row['error'] !== UPLOAD_ERR_NO_FILE));
    if (count($usable) > 10) wtu_fail('Upload no more than 10 task-list images or PDFs at once.');
    $combinedSize = array_sum(array_map(static fn(array $row): int => max(0, (int)$row['size']), $usable));
    if ($combinedSize > 45 * 1024 * 1024) wtu_fail('The combined task-list upload must be no larger than 45 MB.');

    $root = dirname(__DIR__);
    $relativeDir = 'storage/private/work_task_updates/job_' . $jobId . '/request_' . $requestId;
    $absoluteDir = $root . '/' . $relativeDir;
    if ($usable && !is_dir($absoluteDir) && !mkdir($absoluteDir, 0770, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('Could not create the private task-update folder.');
    }

    $allowed = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'image/heic' => 'jpg', 'image/heif' => 'jpg',
        'image/heic-sequence' => 'jpg', 'image/heif-sequence' => 'jpg',
        'application/pdf' => 'pdf',
    ];
    $insert = $pdo->prepare('INSERT INTO work_task_update_files(request_id,job_id,original_name,relative_path,mime_type,file_size,sha256) VALUES(?,?,?,?,?,?,?)');
    $stored = [];
    foreach ($usable as $index => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('One task-list file did not upload successfully.');
        if ($file['size'] <= 0 || $file['size'] > 12 * 1024 * 1024) throw new RuntimeException('Each task-list file must be no larger than 12 MB.');
        $mime = wt_normalise_uploaded_photo_mime((string)(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']), $file['name']);
        if (!isset($allowed[$mime])) throw new RuntimeException('Task-list files must be JPG, PNG, WEBP, HEIC, HEIF or PDF.');
        $storedName = sprintf('%02d_', $index + 1) . bin2hex(random_bytes(6)) . '_' . preg_replace('/\.[^.]+$/', '', wtu_safe_name($file['name'])) . '.' . $allowed[$mime];
        $absolutePath = $absoluteDir . '/' . $storedName;
        $storedMime = $mime;
        if (wt_is_heic_photo($mime)) {
            wt_save_heic_as_task_jpeg($file['tmp_name'], $absolutePath);
            $storedMime = 'image/jpeg';
        } elseif (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new RuntimeException('A task-list file could not be saved.');
        }
        $relativePath = $relativeDir . '/' . $storedName;
        $size = (int)(filesize($absolutePath) ?: $file['size']);
        $sha = hash_file('sha256', $absolutePath) ?: null;
        $insert->execute([$requestId, $jobId, $file['name'], $relativePath, $storedMime, $size, $sha]);
        $stored[] = ['id'=>(int)$pdo->lastInsertId(), 'name'=>$file['name'], 'path'=>$absolutePath, 'mime'=>$storedMime];
    }
    return $stored;
}

function wtu_create_proposal(PDO $pdo, int $jobId, string $source, string $submittedBy, string $sourceText, array $upload): int
{
    $sourceText = trim($sourceText);
    $hasUpload = count(array_filter(wtu_normalise_files_array($upload), static fn(array $f): bool => $f['error'] !== UPLOAD_ERR_NO_FILE)) > 0;
    if ($sourceText === '' && !$hasUpload) wtu_fail('Paste an updated list or attach at least one screenshot, photo or PDF.');
    if (mb_strlen($sourceText) > 30000) wtu_fail('The pasted task list is too long.');

    $job = wt_job($pdo, $jobId);
    $tasks = wt_job_tasks($pdo, $jobId);
    $snapshot = array_map(static fn(array $t): array => [
        'id'=>(int)$t['id'], 'title'=>(string)$t['title'], 'status'=>(string)$t['status'],
        'description'=>(string)($t['description'] ?? ''), 'customer_summary'=>(string)($t['customer_summary'] ?? ''),
        'detailed_procedure'=>(string)($t['detailed_procedure'] ?? ''),
        'time_drivers'=>(string)($t['time_drivers'] ?? ''),
        'waiting_curing_notes'=>(string)($t['waiting_curing_notes'] ?? ''),
        'suggested_materials'=>(string)($t['suggested_materials'] ?? ''),
    ], $tasks);

    $q = $pdo->prepare("INSERT INTO work_task_update_requests(job_id,source,submitted_by,source_text,status,existing_tasks_snapshot_json) VALUES(?,?,?,?, 'processing', ?)");
    $q->execute([$jobId, $source, mb_substr($submittedBy, 0, 150), $sourceText !== '' ? $sourceText : null, json_encode($snapshot, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $requestId = (int)$pdo->lastInsertId();

    try {
        $stored = wtu_store_files($pdo, $requestId, $jobId, $upload);
        $key = trim((string)wt_env('OPENAI_API_KEY', ''));
        if ($key === '') throw new RuntimeException('OPENAI_API_KEY is not configured.');
        $existingJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $prompt = "You are reconciling an updated handyman job list for Mike of All Trades in Victoria, Australia.\n"
            . "Compare the newly supplied evidence against the existing tasks. Do not delete, cancel or silently overwrite anything. Group closely related steps in the same area into practical tasks, but do not collapse unrelated work.\n"
            . "Return JSON only with this exact shape: {\"summary\":\"\",\"items\":[{\"action\":\"add|update|already_covered\",\"matched_task_id\":null,\"reason\":\"\",\"task\":{\"title\":\"\",\"description\":\"\",\"customer_summary\":\"\",\"detailed_procedure\":\"\",\"time_drivers\":\"\",\"waiting_curing_notes\":\"\",\"suggested_materials\":\"\",\"ai_estimate_low\":null,\"ai_estimate_high\":null,\"ai_reasoning\":\"\"}}]}.\n"
            . "Use update only where new evidence materially improves one identified existing task; matched_task_id must be that task ID. Preserve truthful completed-work wording when the supplied list describes work already done. Use add for genuinely missing work. Use already_covered for duplicates. Do not imply Mike performed licensed electrical or plumbing work. Do not invent measurements, products, quantities or conditions.\n"
            . "Existing tasks JSON:\n" . $existingJson . "\n"
            . "Current approved requested-work text:\n" . (string)($job['customer_request_text'] ?? $job['original_scope'] ?? '') . "\n"
            . "New pasted evidence:\n" . $sourceText;
        $content = [['type'=>'input_text', 'text'=>$prompt]];
        foreach ($stored as $file) {
            $bytes = file_get_contents($file['path']);
            if (!is_string($bytes) || $bytes === '') continue;
            $b64 = base64_encode($bytes);
            $content[] = $file['mime'] === 'application/pdf'
                ? ['type'=>'input_file', 'filename'=>$file['name'], 'file_data'=>'data:application/pdf;base64,'.$b64]
                : ['type'=>'input_image', 'image_url'=>'data:'.$file['mime'].';base64,'.$b64, 'detail'=>'high'];
        }
        $payload = json_encode(['model'=>wt_env('WORKTRACKER_AI_MODEL','gpt-5.6-luna'), 'input'=>[['role'=>'user','content'=>$content]], 'max_output_tokens'=>12000], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>150, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json']]);
        $raw = curl_exec($ch); $curlError = curl_error($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw === false || $http < 200 || $http >= 300) throw new RuntimeException($curlError ?: 'OpenAI comparison failed with HTTP '.$http.'.');
        $data = json_decode((string)$raw, true);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', wtu_response_text(is_array($data) ? $data : []));
        $proposal = json_decode(trim((string)$text), true);
        if (!is_array($proposal) || !isset($proposal['items']) || !is_array($proposal['items'])) throw new RuntimeException('AI returned an unexpected task-comparison format.');
        $validIds = array_fill_keys(array_map(static fn(array $t): int => (int)$t['id'], $tasks), true);
        $cleanItems = [];
        foreach ($proposal['items'] as $item) {
            if (!is_array($item)) continue;
            $action = (string)($item['action'] ?? '');
            if (!in_array($action, ['add','update','already_covered'], true)) continue;
            $matched = (int)($item['matched_task_id'] ?? 0);
            if ($action === 'update' && !isset($validIds[$matched])) $action = 'add';
            $task = is_array($item['task'] ?? null) ? $item['task'] : [];
            $title = trim((string)($task['title'] ?? ''));
            if ($title === '' && $action !== 'already_covered') continue;
            $cleanItems[] = ['action'=>$action, 'matched_task_id'=>$matched ?: null, 'reason'=>mb_substr(trim((string)($item['reason'] ?? '')),0,1000), 'task'=>$task];
        }
        if (!$cleanItems) throw new RuntimeException('AI did not produce any usable task comparisons.');
        $proposal['items'] = $cleanItems;
        $pdo->prepare("UPDATE work_task_update_requests SET status='ready',proposal_json=?,error_message=NULL WHERE id=? AND job_id=?")
            ->execute([json_encode($proposal, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), $requestId, $jobId]);
        return $requestId;
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE work_task_update_requests SET status='failed',error_message=? WHERE id=? AND job_id=?")
            ->execute([mb_substr($e->getMessage(),0,4000), $requestId, $jobId]);
        throw $e;
    }
}

function wtu_merge_request_text(PDO $pdo, int $jobId, string $sourceText): void
{
    $sourceItems = wt_split_customer_request_items($sourceText);
    if (!$sourceItems) return;
    $q = $pdo->prepare('SELECT request_text FROM work_job_intake_items WHERE job_id=? ORDER BY item_order,id');
    $q->execute([$jobId]);
    $existing = array_map(static fn(array $r): string => trim((string)$r['request_text']), $q->fetchAll(PDO::FETCH_ASSOC));
    if (!$existing) {
        $job = wt_job($pdo, $jobId);
        $existing = wt_split_customer_request_items((string)($job['customer_request_text'] ?? $job['original_scope'] ?? ''));
    }
    $seen = [];
    foreach ($existing as $item) $seen[mb_strtolower(preg_replace('/\s+/', ' ', trim($item)))] = true;
    foreach ($sourceItems as $item) {
        $key = mb_strtolower(preg_replace('/\s+/', ' ', trim($item)));
        if ($key !== '' && !isset($seen[$key])) { $existing[] = $item; $seen[$key] = true; }
    }
    $job = wt_job($pdo, $jobId);
    $before = (string)($job['customer_request_text'] ?? $job['original_scope'] ?? '');
    $newText = wt_replace_job_intake_items($pdo, $jobId, $existing);
    $pdo->prepare('UPDATE work_jobs SET customer_request_text=?,original_scope=?,customer_request_updated_at=NOW() WHERE id=?')->execute([$newText,$newText,$jobId]);
    $pdo->prepare("INSERT INTO work_job_request_revisions(job_id,source,previous_text,new_text,note,requires_review,reviewed_at) VALUES(?,'mike',?,?,?,0,NOW())")
        ->execute([$jobId,$before,$newText,'Approved AI task reconciliation merged into the requested-work history']);
}
