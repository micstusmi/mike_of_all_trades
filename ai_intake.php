<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

$job = trim($_POST['job'] ?? '');
$history = trim($_POST['history'] ?? '');

if (!$job) {
    echo json_encode([
        'success' => false,
        'message' => 'Please type a message first.'
    ]);
    exit;
}

$payload = [
    "model" => "gpt-4.1-mini",
    "messages" => [
        [
            "role" => "system",
            "content" => "You are a simple guided website assistant for Mike Of All Trades in Victoria, Australia. Keep responses very short, friendly and practical. Do not overwhelm the customer. Do not act like a full tradesperson estimator unless the customer specifically asks for advice or timing. Return JSON only."
        ],
        [
            "role" => "user",
            "content" => "Customer message: {$job}

Conversation so far:
{$history}

Return JSON only with:
intent
understood_job
reply
next_step_options

Intent must be one of:
job_quote
availability
general_advice
multi_task_bundle
correction
human_help

Rules:
- Keep the reply short.
- If this is the first job description, acknowledge it and ask what they would like to do next.
- Do not immediately ask quantities, measurements, materials, colours, or technical questions unless the customer specifically asks for quote/advice/timing.
- Always make the customer feel in control.
- If the customer asks about availability, use intent: availability and ask whether they want upcoming day, week, or month.
- If the customer describes many small jobs, use intent: multi_task_bundle and suggest half-day/full-day/contact Mike options.
- If the customer is correcting you, acknowledge the correction and continue.
- Always include practical next-step options.
- Always include Ask Mike to contact me.

For normal job messages, use options:
Get a quote
Make a booking
See availability
Get advice
Send this chat to Mike
Correct / redirect the AI

For availability messages, use options:
Upcoming day
Upcoming week
Upcoming month
Send this chat to Mike

For many-small-jobs messages, use options:
Book a half day
Book a full day
See availability
Send this chat to Mike"
        ]
    ],
    "temperature" => 0.2
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode([
        'success' => false,
        'message' => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

$data = json_decode($response, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'OpenAI returned invalid JSON.',
        'debug' => $response
    ]);
    exit;
}

if (isset($data['error'])) {
    echo json_encode([
        'success' => false,
        'message' => $data['error']['message'] ?? 'OpenAI API error.',
        'debug' => $data
    ]);
    exit;
}

$content = $data['choices'][0]['message']['content'] ?? null;

if (!$content) {
    echo json_encode([
        'success' => false,
        'message' => 'OpenAI response did not contain message content.',
        'debug' => $data
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'raw' => $content
]);