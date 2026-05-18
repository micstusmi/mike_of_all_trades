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
            "content" => "You are an AI intake assistant for Mike Of All Trades in Victoria, Australia. Keep replies short, friendly and practical. Ask one useful follow-up question at a time. Help gather enough detail for a quote or booking. Do not overwhelm the customer. Avoid repeatedly saying thanks, got it, or thanks for reaching out. Return JSON only."
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
- If the customer describes a job, ask the most useful next question.
- Ask only one short follow-up question at a time.
- Useful questions may include quantity, photos, measurements, suburb/location, access, urgency, or whether the customer already has materials.
- For painting jobs, it is useful to ask whether the customer already has the paint/materials or wants Mike to supply them.
- For repair/handyman jobs, it is useful to ask what condition the item is in, whether parts are available, and whether photos can be provided.
- If the customer asks about availability, use intent: availability and ask whether they want upcoming day, week, or month.
- If the customer describes many small jobs, use intent: multi_task_bundle and suggest half-day, full-day, or Mike contacting them first.
- If the customer sounds frustrated, confused, or wants Mike personally, use intent: human_help.
- Do not repeatedly say thanks, got it, or thanks for reaching out.
- Keep replies natural and avoid sounding robotic.
- Always include useful next-step options.
- Always include Send this chat to Mike.
- Never say that a quote has been sent, emailed, prepared, booked, confirmed, or rescheduled unless the website backend has actually completed that action.
- If the customer asks to send, email, prepare, or confirm a Zoho quote from this chat, say: “I can move these details into the quote form for you, but the quote is only sent after you press Send Quote on the next page.”
- Do not pretend that Mike has been notified, called, emailed, or booked unless the backend has confirmed it.

Use options like:
Get a quote
Make a booking
See availability
Send this chat to Mike
Correct / redirect the AI"
        ]
    ],
    "temperature" => 0.35
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