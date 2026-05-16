<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

$job = trim($_POST['job'] ?? '');
$history = trim($_POST['history'] ?? '');

if (!$job) {
    echo json_encode([
        'success' => false,
        'message' => 'Please describe the job.'
    ]);
    exit;
}

$payload = [
    "model" => "gpt-4.1-mini",
    "messages" => [
        [
            "role" => "system",
            "content" => "You are an AI intake assistant for Mike Of All Trades in Victoria, Australia. Keep responses simple, friendly and conversational. Do not overwhelm the customer. Ask only one short follow-up question at a time. Invite corrections. Do not assume materials are required. Do not assume the process is final. Return JSON only."
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
assumptions
next_step_options

Intent must be one of:
job_quote
availability
general_advice
multi_task_bundle
correction
human_help

Rules:
- If the customer asks about availability, calendar, when Mike is free, or booking times, use intent: availability.
- If intent is availability, ask whether they want to see availability for the upcoming day, week, or month.
- If the customer describes many small tasks, odd jobs, multiple rooms, lots of little handyman items, or says they are unsure how to list everything, use intent: multi_task_bundle.
- If intent is multi_task_bundle, do not ask them to detail every task. Suggest half-day, full-day, or Mike contacting them first.
- If the customer corrects your understanding, says the process is different, or says they already have materials, use intent: correction.
- If the customer seems unsure, frustrated, or wants Mike personally, use intent: human_help.
- If the customer describes one clear job, use intent: job_quote.
- Ask one helpful follow-up question only.
- Keep the reply short and friendly.
- Always include an option called: Ask Mike to contact me.
- For normal job_quote, include options: Looks right, I need to correct something, I already have materials, Get a quote, Make a booking, Ask Mike to contact me.
- For availability, include options: Upcoming day, Upcoming week, Upcoming month, Ask Mike to contact me.
- For multi_task_bundle, include options: Book a half day, Book a full day, Ask Mike to contact me, Check Mike's availability."
        ]
    ],
    "temperature" => 0.45
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
$content = $data['choices'][0]['message']['content'] ?? '';

echo json_encode([
    'success' => true,
    'raw' => $content
]);