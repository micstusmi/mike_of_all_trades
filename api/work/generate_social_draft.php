<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

header('Content-Type: application/json; charset=utf-8');

function social_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function social_public_area(array $job): string
{
    $site=trim((string)($job['site_name']??''));if($site!=='')return mb_substr($site,0,80);
    $address=trim((string)($job['job_address']??''));
    if(preg_match("/\\b(?:Street|St|Road|Rd|Avenue|Ave|Drive|Dr|Court|Ct|Crescent|Cres|Lane|Ln|Place|Pl|Way|Highway|Hwy)\\s+([A-Za-z][A-Za-z\\s'-]{2,})\\s+\\d{4}\\b/i",$address,$m))return trim($m[1]);
    if(preg_match("/\\b([A-Za-z][A-Za-z'-]*(?:\\s+[A-Za-z][A-Za-z'-]*)?)\\s+\\d{4}\\b/",$address,$m))return trim($m[1]);
    return '';
}

function social_visual_data_url(string $path,string $mime): ?string
{
    if(!extension_loaded('gd'))return null;$source=wt_load_gd_image($path,$mime);if(!$source)return null;$source=wt_apply_jpeg_orientation($source,$path,$mime);
    $w=imagesx($source);$h=imagesy($source);if($w<1||$h<1)return null;$scale=min(1,512/max($w,$h));$ow=max(1,(int)round($w*$scale));$oh=max(1,(int)round($h*$scale));
    $canvas=imagecreatetruecolor($ow,$oh);$white=imagecolorallocate($canvas,255,255,255);imagefilledrectangle($canvas,0,0,$ow,$oh,$white);imagecopyresampled($canvas,$source,0,0,0,0,$ow,$oh,$w,$h);
    ob_start();imagejpeg($canvas,null,68);$bytes=ob_get_clean();imagedestroy($canvas);if(is_resource($source)||is_object($source))@imagedestroy($source);
    return is_string($bytes)&&$bytes!==''?'data:image/jpeg;base64,'.base64_encode($bytes):null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    social_fail('POST required.', 405);
}

$jobId = (int)($_POST['job_id'] ?? 0);
if ($jobId <= 0) {
    social_fail('Invalid job.');
}

$platform = (string)($_POST['platform'] ?? 'general');
if (!in_array($platform, ['general', 'instagram', 'tiktok', 'facebook'], true)) {
    $platform = 'general';
}

$job = wt_job($pdo, $jobId);
$apiKey = wt_env('OPENAI_API_KEY');
if (!$apiKey) {
    social_fail('OPENAI_API_KEY is not configured. The page is ready, but AI drafting needs the server API key.', 500);
}

$tasks = wt_job_tasks($pdo, $jobId, false);

$photoStmt = $pdo->prepare("
    SELECT p.id,p.photo_type,p.note,p.created_at,p.relative_path,p.mime_type,p.file_deleted_at,p.social_relative_path,t.title AS task_title
    FROM work_task_photos p
    LEFT JOIN work_tasks t ON t.id=p.task_id
    WHERE p.job_id=?
    ORDER BY p.created_at DESC,p.id DESC
    LIMIT 24
");
$photoStmt->execute([$jobId]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

$taskLines = [];
foreach ($tasks as $task) {
    $taskLines[] = [
        'title' => (string)($task['title'] ?? ''),
        'status' => (string)($task['status'] ?? ''),
        'summary' => (string)($task['customer_summary'] ?? $task['description'] ?? ''),
        'notes' => (string)($task['waiting_curing_notes'] ?? ''),
    ];
}

$photoLines = [];
foreach ($photos as $photo) {
    $photoLines[] = [
        'id' => (int)$photo['id'],
        'stage' => wt_photo_stage_label((string)$photo['photo_type']),
        'task' => (string)($photo['task_title'] ?? ''),
        'note' => (string)($photo['note'] ?? ''),
        'has_branded_copy' => !empty($photo['social_relative_path']),
        'created_at' => (string)($photo['created_at'] ?? ''),
    ];
}

$context = [
    'business' => 'Mike Of All Trades, handyman and property maintenance in Victoria, Australia',
    'draft_date' => date('Y-m-d'),
    'public_location_area' => social_public_area($job),
    'customer_request' => (string)($job['customer_request_text'] ?? $job['original_scope'] ?? ''),
    'current_scope' => (string)($job['current_scope'] ?? ''),
    'tasks' => $taskLines,
    'available_photos' => $photoLines,
];

$prompt = <<<TXT
Create a {$platform} social media post draft for Mike Of All Trades based on this real job record.

Return JSON only with this exact shape:
{
  "title": "...",
  "caption": "...",
  "short_caption": "...",
  "hashtags": ["..."],
  "photo_plan": "...",
  "selected_photo_ids": [1,2,3]
}

Rules:
- Do not include the customer's name, phone, exact address, private access details, or anything that could embarrass the customer.
- Do not claim licensed plumbing/electrical/building work unless the job text clearly says a licensed contractor performed it.
- Visually inspect the attached real job photos as well as the written job record. Use visible interior/exterior context, room or area type, colours, surfaces, cosmetic condition, preparation and progression to make the writing specific and interesting.
- Treat structural, safety, moisture, causation, electrical and plumbing interpretations as uncertain unless explicitly supported by the written record. Do not diagnose hidden defects from an image.
- Use real job photos only. Do not imply AI-generated work images or fake before/after scenes.
- Make the title a strong, truthful curiosity-gap hook. It should feel irresistible rather than like an administrative job label.
- Make the caption emotionally engaging and story-driven: open with tension, surprise or a homeowner pain point; reveal the care, judgement and transformation; finish with relief, satisfaction, a useful takeaway, or a natural question/call to action.
- Sell the value of Mike's work without sounding like a generic advertisement. Show why the preparation, patience, problem-solving and finish matter to a homeowner.
- Use vivid, varied and specific adjectives naturally. Vary emotional angles between curiosity, concern, anticipation, relief, trust, pride and satisfaction. Do not reuse the same hook or adjective pattern across every draft.
- Avoid beige labels and filler such as "progress update", "general job photos", "work completed", "quality workmanship" or "another day on the tools" unless transformed into a genuinely compelling line.
- Click-worthy must remain truthful: never manufacture danger, disasters, huge savings, customer reactions, deadlines or dramatic results that the records do not support.
- Mention before/in-progress/after photos only if the available photo list supports it.
- selected_photo_ids must use only IDs from available_photos.
- For Instagram: favour a polished carousel caption, square/portrait framing, and local hashtags.
- For TikTok: favour a punchy slideshow/reel hook, portrait framing, and a job-journey tone that can invite viewer advice.
- For Facebook: favour clearer homeowner explanation, local trust, and mixed photo orientation.
- hashtags should be Australian/local handyman/property-maintenance style.

Job context JSON:
TXT;

$prompt .= "\n" . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$content=[['type'=>'input_text','text'=>$prompt]];
foreach($photos as $photo){
    if(!empty($photo['file_deleted_at'])||empty($photo['relative_path']))continue;
    $path=wt_task_photo_path((string)$photo['relative_path']);if(!is_file($path))continue;
    $content[]=['type'=>'input_text','text'=>'The next image is photo_id '.(int)$photo['id'].'; stage '.wt_photo_stage_label((string)$photo['photo_type']).'; task '.trim((string)($photo['task_title']??'')).'; note '.trim((string)($photo['note']??'')).'.'];
    $dataUrl=social_visual_data_url($path,(string)$photo['mime_type']);if($dataUrl!==null)$content[]=['type'=>'input_image','image_url'=>$dataUrl,'detail'=>'low'];
}

$model = wt_env('WORKTRACKER_SOCIAL_AI_MODEL', wt_env('WORKTRACKER_AI_MODEL', 'gpt-5.6-luna'));
$payload = json_encode([
    'model' => $model,
    'input' => [['role'=>'user','content'=>$content]],
    'max_output_tokens' => 2500,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
]);

$raw = curl_exec($ch);
$err = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false || $status < 200 || $status >= 300) {
    social_fail($err ?: ('OpenAI request failed with HTTP ' . $status), 502);
}

$data = json_decode((string)$raw, true);
$text = '';
foreach (($data['output'] ?? []) as $out) {
    foreach (($out['content'] ?? []) as $content) {
        if (isset($content['text'])) {
            $text .= $content['text'];
        }
    }
}

$text = trim($text);
$text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
$draft = json_decode((string)$text, true);

if (!is_array($draft)) {
    social_fail('AI returned an unexpected social draft format.', 502);
}

$title = trim((string)($draft['title'] ?? ''));
$caption = trim((string)($draft['caption'] ?? ''));
if ($title === '' || $caption === '') {
    social_fail('AI draft was missing a title or caption.', 502);
}

$hashtags = $draft['hashtags'] ?? [];
if (is_array($hashtags)) {
    $hashtags = implode(' ', array_map(static fn($tag) => str_starts_with((string)$tag, '#') ? (string)$tag : '#' . preg_replace('/\s+/', '', (string)$tag), $hashtags));
} else {
    $hashtags = (string)$hashtags;
}

$selected = $draft['selected_photo_ids'] ?? [];
if (is_array($selected)) {
    $selected = implode(',', array_map('intval', $selected));
} else {
    $selected = preg_replace('/[^0-9,]+/', '', (string)$selected) ?? '';
}

$insert = $pdo->prepare("
    INSERT INTO work_social_drafts
    (job_id,draft_date,platform,tone,title,caption,short_caption,hashtags,photo_plan,selected_photo_ids,ai_model,raw_ai)
    VALUES(?,CURDATE(),?,'platform_social',?,?,?,?,?,?,?,?)
");
$insert->execute([
    $jobId,
    $platform,
    mb_substr($title, 0, 255),
    $caption,
    trim((string)($draft['short_caption'] ?? '')) ?: null,
    trim((string)$hashtags) ?: null,
    trim((string)($draft['photo_plan'] ?? '')) ?: null,
    trim((string)$selected) ?: null,
    $model,
    $text,
]);

echo json_encode([
    'ok' => true,
    'id' => (int)$pdo->lastInsertId(),
    'draft' => [
        'title' => $title,
        'caption' => $caption,
        'short_caption' => trim((string)($draft['short_caption'] ?? '')),
        'hashtags' => trim((string)$hashtags),
        'photo_plan' => trim((string)($draft['photo_plan'] ?? '')),
        'selected_photo_ids' => trim((string)$selected),
    ],
]);
