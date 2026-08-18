<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

$job = trim($_POST['job'] ?? '');
$history = trim($_POST['history'] ?? '');

/*
 * Photo upload limits.
 * The browser also limits selection to 10, but the server must enforce it too.
 */
const MAX_AI_ATTACHMENTS = 10;
const MAX_AI_ATTACHMENT_BYTES = 10 * 1024 * 1024; // 10 MB each
const AI_IMAGE_MAX_EDGE = 1600;
const AI_IMAGE_JPEG_QUALITY = 82;

function failJson(string $message, $debug = null): void
{
    $response = [
        'success' => false,
        'message' => $message
    ];

    if ($debug !== null) {
        $response['debug'] = $debug;
    }

    echo json_encode($response);
    exit;
}

function normaliseUploadedFiles(array $fileBag): array
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
            'size' => $fileBag['size'][$i] ?? 0,
        ];
    }

    return $files;
}

function rotateJpegFromExif($image, string $tmpPath)
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($tmpPath);
    $orientation = (int)($exif['Orientation'] ?? 1);

    if ($orientation === 3) {
        return imagerotate($image, 180, 0);
    }

    if ($orientation === 6) {
        return imagerotate($image, -90, 0);
    }

    if ($orientation === 8) {
        return imagerotate($image, 90, 0);
    }

    return $image;
}

function uploadedImageToDataUrl(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One of the uploaded photos could not be received.');
    }

    if (($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('One of the uploaded photos is empty.');
    }

    if (($file['size'] ?? 0) > MAX_AI_ATTACHMENT_BYTES) {
        throw new RuntimeException('Each photo must be 10 MB or smaller.');
    }

    $tmpPath = $file['tmp_name'] ?? '';

    if (!$tmpPath || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('The uploaded photo could not be verified.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);

    $allowed = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Photos must be JPG, PNG or WEBP files.');
    }

    $raw = file_get_contents($tmpPath);

    if ($raw === false) {
        throw new RuntimeException('The uploaded photo could not be read.');
    }

    /*
     * If GD is available, resize large phone photos before they are sent to OpenAI.
     * If GD is not installed, safely fall back to the validated original image.
     */
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $source = @imagecreatefromstring($raw);

        if ($source !== false) {
            if ($mime === 'image/jpeg') {
                $rotated = rotateJpegFromExif($source, $tmpPath);

                if ($rotated !== $source) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $longEdge = max($width, $height);

            if ($longEdge > AI_IMAGE_MAX_EDGE) {
                $scale = AI_IMAGE_MAX_EDGE / $longEdge;
                $newWidth = max(1, (int)round($width * $scale));
                $newHeight = max(1, (int)round($height * $scale));

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefill($resized, 0, 0, $white);

                imagecopyresampled(
                    $resized,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $newWidth,
                    $newHeight,
                    $width,
                    $height
                );

                imagedestroy($source);
                $source = $resized;
            }

            ob_start();
            imagejpeg($source, null, AI_IMAGE_JPEG_QUALITY);
            $jpegBytes = ob_get_clean();
            imagedestroy($source);

            if ($jpegBytes !== false && $jpegBytes !== '') {
                return 'data:image/jpeg;base64,' . base64_encode($jpegBytes);
            }
        }
    }

    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

$uploadedFiles = isset($_FILES['attachments'])
    ? normaliseUploadedFiles($_FILES['attachments'])
    : [];

$uploadedFiles = array_values(array_filter(
    $uploadedFiles,
    fn($file) => ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
));

if (count($uploadedFiles) > MAX_AI_ATTACHMENTS) {
    failJson('Please upload no more than 10 photos or PDF files at a time.');
}

if (!$job && count($uploadedFiles) === 0) {
    failJson('Please type a message or add at least one photo or PDF first.');
}

$attachmentContent = [];

try {
    foreach ($uploadedFiles as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('One of the uploaded attachments could not be received.');
        }

        if (($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('One of the uploaded attachments is empty.');
        }

        if (($file['size'] ?? 0) > MAX_AI_ATTACHMENT_BYTES) {
            throw new RuntimeException('Each photo or PDF must be 10 MB or smaller.');
        }

        $tmpPath = $file['tmp_name'] ?? '';

        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('One of the uploaded attachments could not be verified.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        if ($mime === 'application/pdf') {
            $raw = file_get_contents($tmpPath);

            if ($raw === false) {
                throw new RuntimeException('The uploaded PDF could not be read.');
            }

            $safeName = basename((string)($file['name'] ?? 'document.pdf'));
            if (!str_ends_with(strtolower($safeName), '.pdf')) {
                $safeName .= '.pdf';
            }

            $attachmentContent[] = [
                'type' => 'input_file',
                'filename' => $safeName,
                'file_data' => 'data:application/pdf;base64,' . base64_encode($raw)
            ];
            continue;
        }

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $attachmentContent[] = [
                'type' => 'input_image',
                'image_url' => uploadedImageToDataUrl($file),
                'detail' => 'auto'
            ];
            continue;
        }

        throw new RuntimeException('Attachments must be JPG, PNG, WEBP or PDF files.');
    }
} catch (Throwable $e) {
    failJson($e->getMessage());
}

$userPrompt = "Customer message: {$job}\n\nConversation so far:\n{$history}\n\n" . <<<'PROMPT'
Return JSON only with:
intent
understood_job
reply
next_step_options
estimated_hours
estimated_price
service
suburb
quote_ready

Intent must be one of:
job_quote
booking
availability
general_advice
multi_task_bundle
correction
human_help

Rules:
- If the customer describes a job, ask the most useful next question.
- Ask one useful follow-up question only if it is genuinely needed.
- Useful questions may include quantity, photos, measurements, suburb/location, access, urgency, or whether the customer already has materials.
- Customers may attach photos and PDF plans/documents. Carefully inspect attached photos and read relevant PDF content to help understand and estimate the work.
- Do not ask the customer to describe information that is already clearly visible in an attached photo or clearly stated/dimensioned in an attached PDF.
- PDFs may contain plans, dimensions, scopes, specifications or reports. Use dimensions explicitly shown in a PDF when relevant, but do not invent dimensions or assume a drawing is to scale unless the document establishes that.
- Focus on the pages and information relevant to the customer's requested work; do not overwhelm them with an unnecessary summary of the whole document.
- Photos can be misleading about scale. Never assume the physical size of a hole, crack, damaged area or object unless there is a reliable scale reference in the image.
- For holes or damaged areas in plaster, if the approximate dimensions are not already known and size would materially affect the quote, ask for the approximate size. Natural comparisons are acceptable, for example: coin-size, fist-size, shoe-size, dinner-plate-size, torso-size, or approximate width x height in centimetres.
- If there are multiple plaster holes, ask for the approximate size and quantity of the holes if that information is not clear from the photos or conversation.
- If practical, suggest that the customer include a tape measure, ruler, common object, hand or shoe in a follow-up photo to provide scale, but do not require another photo if a simple stated measurement is enough.
- Do not claim exact dimensions, hidden damage, structural condition, electrical safety, plumbing compliance, asbestos status, moisture source, or other facts that cannot be reliably established from an image.
- If a photo suggests licensed electrical, plumbing, gas, structural or other regulated work may be required, make that limitation clear and keep Mike's role within lawful handyman/property-maintenance work.
- For painting jobs, it is useful to ask whether the customer already has the paint/materials or wants Mike to supply them.
- For repair/handyman jobs, it is useful to ask what condition the item is in, whether parts are available, and whether photos can be provided when no useful photos have already been attached.
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
- For simple standard jobs, do not keep asking for more details once enough basic information is available.
- If the customer asks for a quote now, produce an indicative estimate using reasonable assumptions.
- Clearly list the assumptions used.
- Include this disclaimer: Estimated pricing and timeframes are a guide only. Final pricing may vary depending on materials, access, existing conditions, and any unexpected issues discovered during the job.
- If the customer sounds frustrated or says “just give me the quote”, stop asking questions and provide the best indicative estimate possible.
- For small standard jobs like fitting a supplied deadlock, replacing a handle, hanging a picture, basic patching, or simple handyman tasks, it is okay to estimate based on typical labour without asking every detail.
- Never say the quote has been sent unless the backend confirms it.
- When ready, tell the customer they can press “Review quote form” to review and send the formal quote.
- If the customer says yes after being offered the quote form, do not repeat the offer. Tell them to press the “Review quote form” button below.
- If the customer is asking for a quote, don't mention availability unless the customer specifically asks or implies that they want to know the availability.
- When enough information exists to estimate a simple quote, set quote_ready to true.
- If quote_ready is true, include estimated_hours and estimated_price.
- estimated_hours and estimated_price must be plain numeric values only. Do not put words, units, dollar signs, commas, or ranges in those fields.
- If the customer-facing reply uses a range, use a sensible representative midpoint in estimated_hours and estimated_price. Example: 1.5-2 hours becomes 1.75, and $180-$220 becomes 200.
- If quote_ready is false and a meaningful estimate is not yet available, use 0 for estimated_hours and estimated_price.
- For simple standard residential jobs, make reasonable assumptions instead of endlessly asking questions.
- If the customer asks for a quote now, provide an indicative quote using the details available.
- If missing details would only slightly affect price, estimate anyway and list assumptions.
- Stop asking unnecessary follow-up questions once the job is clear enough.
- Act like an experienced estimator, not a cautious support chatbot.
- If the customer sounds frustrated, give the best estimate possible and move them toward quote review.
- REALISTIC TOTAL JOB TIME: Never estimate labour based only on the obvious hands-on installation, repair, painting, assembly or fixing time. Estimate the realistic total time Mike is likely to devote to completing the job.
- Where relevant, allow reasonable time for reviewing supplied photos/plans, preparing for the job, selecting and loading special tools/equipment, obtaining job-specific consumables or materials, Bunnings/supplier stops, travel-related preparation, finding parking, walking from the vehicle to the work area, locating the site or contact person, gaining access, orientating at the property, inspecting and reassessing the proposed work in person, checking measurements and substrates, setup and protection, actual hands-on work, reasonable minor complications, testing/checking the completed work, cleaning up, packing up, and returning tools/equipment to the vehicle.
- For commercial sites, shopping centres, construction sites, unfamiliar properties, jobs involving plans, multiple installation locations, special access, ladders, uncertain substrates, loading/unloading, inductions, restricted parking or customer/site-contact coordination, allow a larger setup/access/site-assessment contingency than for a simple residential task.
- When plans, drawings or construction documents are supplied, allow time for Mike to review the relevant information and verify real site conditions/set-out before commencing. Drawings do not eliminate site-assessment time.
- Do not assume every aspect of a job will go perfectly. Where there is genuine uncertainty that could materially change labour, prefer a realistic range rather than an unrealistically optimistic best-case estimate.
- Do not automatically add a fixed number of hours to every job. Use judgement according to job size, complexity, access, location, preparation, equipment and likely uncertainty.
- For very small and straightforward jobs, keep incidental allowances proportionate; do not make a tiny task expensive simply because generic setup items exist.
- If special materials, fixings or consumables may be needed but are not confirmed, either include a reasonable allowance/assumption or clearly state that supply/collection may change the final price.
- When estimating hours for the customer, the estimated_hours field should reflect the realistic total job allowance Mike is likely to devote to the job, not merely hands-on tool time.
- If painting over old paint is involved, assume that there is a percentage of preparation time needing to be added to the job including setup time, sanding, masking, possible damage, rot, weathering etc that could require minor repairs such as wood putty, spot painting undercoat, clean-up time, etc. and if the colour is changing then there is sometimes 2-3 coats of paint required to completely cover the old colour to stop the old colour from shining through and sometimes there are complications when painting acrylic paint over the top of old enamel paint.
- When painting is involved you need to understand that undercoat takes 2-4 hours to dry before re-coating and same with top coat/s and same with plaster patch ups and same with wood putty patches so sometimes the job can't be done all in one site visit and sometimes the job needs multiple site visits if it is a small project.
- If the customer says they want to book, lock in, reserve, schedule, proceed with a booking, or “just book it in”, use intent: booking.
- Once booking intent is clear, do not mention quote forms, formal quotes, or Review quote form unless the customer asks for a quote again.
- For booking intent, confirm the job summary, suburb, and duration estimate.
- If the customer has NOT already agreed to book, ask one clear booking confirmation question.
- If the customer HAS already agreed to book, do not ask again. Tell them to press “Book Mike in with these chat details” below.
- If the customer says yes after being asked whether to reserve/book/lock in a booking, do not ask again. Use intent: booking and tell them to press “Book Mike in with these chat details” below.
- If the conversation is clearly about booking, do not set intent to job_quote just because a price, time estimate, or quote-like wording appears.
- If the customer says yes, yes please, okay, yep, sure, or sounds like they are agreeing after being asked whether to reserve/book/lock in a booking, do not ask the same question again.
- Instead use intent: booking and say: “Great — please press ‘Book Mike in with these chat details’ below so we can move this into the booking calendar.”
- Never discuss the hourly rate with the customer because the formulas can vary between different jobs depending on how many hours on site, driving times, individual customer's discounts, etc.

Use options like:
Get a quote
Make a booking
See availability
Send this chat to Mike
Correct / redirect the AI
PROMPT;

$userContent = [
    [
        'type' => 'input_text',
        'text' => $userPrompt
    ]
];

foreach ($attachmentContent as $attachmentPart) {
    $userContent[] = $attachmentPart;
}

$quoteResponseSchema = [
    'type' => 'object',
    'properties' => [
        'intent' => [
            'type' => 'string',
            'enum' => [
                'job_quote',
                'booking',
                'availability',
                'general_advice',
                'multi_task_bundle',
                'correction',
                'human_help'
            ]
        ],
        'understood_job' => ['type' => 'string'],
        'reply' => ['type' => 'string'],
        'next_step_options' => [
            'type' => 'array',
            'items' => ['type' => 'string']
        ],
        'estimated_hours' => [
            'type' => 'number',
            'minimum' => 0
        ],
        'estimated_price' => [
            'type' => 'number',
            'minimum' => 0
        ],
        'service' => ['type' => 'string'],
        'suburb' => ['type' => 'string'],
        'quote_ready' => ['type' => 'boolean']
    ],
    'required' => [
        'intent',
        'understood_job',
        'reply',
        'next_step_options',
        'estimated_hours',
        'estimated_price',
        'service',
        'suburb',
        'quote_ready'
    ],
    'additionalProperties' => false
];

$payload = [
    'model' => 'gpt-4.1-mini',
    'instructions' => 'You are an AI intake assistant and experienced practical estimator for Mike Of All Trades in Victoria, Australia. Keep replies short, friendly and practical. Ask one useful follow-up question at a time. Help gather enough detail for a quote or booking. Use attached job photos and PDF plans/documents when available. Do not infer physical scale from a photo unless a reliable size reference is visible or stated. Use dimensions explicitly stated in PDFs when relevant, but do not invent measurements. Estimate realistic total job time rather than only hands-on tool time, including proportionate preparation, access, setup, site reassessment, cleanup and pack-up where relevant. IMPORTANT: estimated_hours and estimated_price must always be plain numeric values with no words, currency symbols, ranges or units. If your reply gives a range, use a sensible representative midpoint for estimated_hours and estimated_price. Example: reply may say 1.5 to 2 hours and $180 to $220, while estimated_hours should be 1.75 and estimated_price should be 200. If the quote is not ready yet, use 0 for those numeric fields. Do not overwhelm the customer. Avoid repeatedly saying thanks, got it, or thanks for reaching out. Return JSON only.',
    'input' => [
        [
            'role' => 'user',
            'content' => $userContent
        ]
    ],
    'text' => [
        'format' => [
            'type' => 'json_schema',
            'name' => 'mike_of_all_trades_quote_intake',
            'strict' => true,
            'schema' => $quoteResponseSchema
        ]
    ],
    'temperature' => 0.35
];

$ch = curl_init('https://api.openai.com/v1/responses');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    failJson(curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if (!$data) {
    failJson('OpenAI returned invalid JSON.', $response);
}

if (isset($data['error'])) {
    failJson(
        $data['error']['message'] ?? 'OpenAI API error.',
        $data
    );
}

if ($httpCode < 200 || $httpCode >= 300) {
    failJson('OpenAI request failed.', $data);
}

$content = null;

foreach (($data['output'] ?? []) as $outputItem) {
    if (($outputItem['type'] ?? '') !== 'message') {
        continue;
    }

    foreach (($outputItem['content'] ?? []) as $contentItem) {
        if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
            $content = $contentItem['text'];
            break 2;
        }
    }
}

if (!$content) {
    failJson('OpenAI response did not contain message content.', $data);
}

$parsedContent = json_decode($content, true);

if (!is_array($parsedContent)) {
    failJson(
        'OpenAI returned an unexpected structured response.',
        $content
    );
}

echo json_encode([
    'success' => true,
    'raw' => json_encode(
        $parsedContent,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ),
    'parsed' => $parsedContent
]);
