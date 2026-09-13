<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

$job = trim($_POST['job'] ?? '');
$history = trim($_POST['history'] ?? '');

/*
 * Photo upload limits.
 * The browser also limits selection to 10, but the server must enforce it too.
 */
const MAX_AI_ATTACHMENTS = 15;
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

function iniBytes(string $value): int
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $last = strtolower(substr($value, -1));
    $number = (float)$value;

    switch ($last) {
        case 'g':
            $number *= 1024;
            // no break
        case 'm':
            $number *= 1024;
            // no break
        case 'k':
            $number *= 1024;
    }

    return (int)$number;
}

/*
 * When PHP's post_max_size is exceeded, PHP can discard BOTH
 * $_POST and $_FILES before this script gets them. Detect that
 * situation and return a useful error instead of pretending the
 * customer did not type a message or attach files.
 */
$contentLength =
    (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

$postMaxBytes =
    iniBytes(
        (string)ini_get('post_max_size')
    );

if (
    $contentLength > 0 &&
    $postMaxBytes > 0 &&
    $contentLength > $postMaxBytes
) {
    failJson(
        'The selected files are too large to upload together. '
        . 'Please try again with fewer files, or smaller photos/PDFs.'
    );
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
    failJson('Please upload no more than 15 photos or PDF files at a time.');
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
structured_estimate

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
- CUSTOMER-FACING ESTIMATE SAFETY: Do not state labour hours, days of work, site-visit count, or a dollar estimate in the conversational reply before the backend-verified quote has been produced. When quote_ready is true, keep the reply concise and say the estimate is ready for review, then direct the customer to “Review quote form”. The structured numeric fields are for the backend, not for the chat bubble.
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
- STRUCTURED ESTIMATE: When quote_ready is true, build structured_estimate before deciding the final hours. Break the work into separate practical tasks, then break each task into the labour components that genuinely apply. Use component categories such as assessment_planning, procurement_logistics, unload_setup, preparation, hands_on_work, cleanup_handover, pack_load, and uncertainty_allowance.
- Each component must have low_hours, likely_hours and high_hours. These are TOTAL labour hours for that component, not rates or calendar duration. Keep low <= likely <= high.
- Do not create fake components merely to make a quote look detailed. Include only work that is reasonably connected to the described job.
- Do not hide repeated mobilisation inside hands_on_work. If the job is likely to need multiple attendances, represent repeated setup, making-safe/cleanup and pack/load labour explicitly where appropriate.
- For larger multi-day jobs, Mike may deliberately attend on alternating days so he can continue servicing smaller handyman customers. Distinguish labour hours from elapsed calendar duration. A job can contain 150 labour hours while taking substantially more than 150/8 calendar working days to complete.
- Long work sessions can reduce the number of mobilisations. Do not assume every 8 labour hours equals a separate site visit. Estimate expected_site_visits separately.
- PROCUREMENT: Treat sourcing and collection as real labour where relevant. Consider product research, checking suitability, checking stock, driving, parking, locating products, checkout, loading, returning to site, and reasonable repeat procurement as work. Do not assume every receipt is a separate trip.
- For complex renovation/property-maintenance work with many unrelated scopes, allow for staged procurement and a realistic chance that an advertised low-stock item is unavailable and requires an alternate supplier/store. Express this as reasonable labour allowance, not as a claim that a problem will definitely occur.
- Keep reusable tools/equipment conceptually separate from customer materials. Do not simply charge the customer the purchase price of Mike's reusable tools.
- structured_estimate.project_components contains shared project-level labour that must NOT also be duplicated inside every task, such as an overall walkthrough, broad planning, combined supplier run, final whole-project handover, or other genuinely shared overhead.
- structured_estimate.total_low_hours, total_likely_hours and total_high_hours are model cross-checks only. The website backend will independently sum task components plus project components and overwrite the final estimated_hours with the verified likely-hours total.
- If quote_ready is false, return empty structured_estimate task/component arrays and zero totals.
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

$hourComponentSchema = [
    'type' => 'object',
    'properties' => [
        'category' => [
            'type' => 'string',
            'enum' => [
                'assessment_planning',
                'procurement_logistics',
                'unload_setup',
                'preparation',
                'hands_on_work',
                'cleanup_handover',
                'pack_load',
                'uncertainty_allowance',
                'other'
            ]
        ],
        'label' => ['type' => 'string'],
        'explanation' => ['type' => 'string'],
        'low_hours' => ['type' => 'number', 'minimum' => 0],
        'likely_hours' => ['type' => 'number', 'minimum' => 0],
        'high_hours' => ['type' => 'number', 'minimum' => 0]
    ],
    'required' => [
        'category',
        'label',
        'explanation',
        'low_hours',
        'likely_hours',
        'high_hours'
    ],
    'additionalProperties' => false
];

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
        'estimated_hours' => ['type' => 'number', 'minimum' => 0],
        'estimated_price' => ['type' => 'number', 'minimum' => 0],
        'service' => ['type' => 'string'],
        'suburb' => ['type' => 'string'],
        'quote_ready' => ['type' => 'boolean'],
        'structured_estimate' => [
            'type' => 'object',
            'properties' => [
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'scope' => ['type' => 'string'],
                            'quantity' => ['type' => 'number', 'minimum' => 0],
                            'unit' => ['type' => 'string'],
                            'assumptions' => [
                                'type' => 'array',
                                'items' => ['type' => 'string']
                            ],
                            'components' => [
                                'type' => 'array',
                                'items' => $hourComponentSchema
                            ]
                        ],
                        'required' => [
                            'title',
                            'scope',
                            'quantity',
                            'unit',
                            'assumptions',
                            'components'
                        ],
                        'additionalProperties' => false
                    ]
                ],
                'project_components' => [
                    'type' => 'array',
                    'items' => $hourComponentSchema
                ],
                'procurement_complexity' => [
                    'type' => 'string',
                    'enum' => ['none', 'low', 'moderate', 'high', 'very_high']
                ],
                'expected_site_visits' => ['type' => 'integer', 'minimum' => 0],
                'scheduling_pattern' => ['type' => 'string'],
                'elapsed_duration_low_days' => ['type' => 'number', 'minimum' => 0],
                'elapsed_duration_high_days' => ['type' => 'number', 'minimum' => 0],
                'total_low_hours' => ['type' => 'number', 'minimum' => 0],
                'total_likely_hours' => ['type' => 'number', 'minimum' => 0],
                'total_high_hours' => ['type' => 'number', 'minimum' => 0]
            ],
            'required' => [
                'tasks',
                'project_components',
                'procurement_complexity',
                'expected_site_visits',
                'scheduling_pattern',
                'elapsed_duration_low_days',
                'elapsed_duration_high_days',
                'total_low_hours',
                'total_likely_hours',
                'total_high_hours'
            ],
            'additionalProperties' => false
        ]
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
        'quote_ready',
        'structured_estimate'
    ],
    'additionalProperties' => false
];


$estimatorResponseSchema = [
    'type' => 'object',
    'properties' => [
        'understood_job' => ['type' => 'string'],
        'estimated_price' => ['type' => 'number', 'minimum' => 0],
        'structured_estimate' => [
            'type' => 'object',
            'properties' => [
                'tasks' => [
                    'type' => 'array',
                    'items' => $taskSchema
                ],
                'project_components' => [
                    'type' => 'array',
                    'items' => $hourComponentSchema
                ],
                'procurement_complexity' => [
                    'type' => 'string',
                    'enum' => ['none', 'low', 'moderate', 'high', 'very_high']
                ],
                'expected_site_visits' => ['type' => 'integer', 'minimum' => 0],
                'scheduling_pattern' => ['type' => 'string'],
                'elapsed_duration_low_days' => ['type' => 'number', 'minimum' => 0],
                'elapsed_duration_high_days' => ['type' => 'number', 'minimum' => 0],
                'total_low_hours' => ['type' => 'number', 'minimum' => 0],
                'total_likely_hours' => ['type' => 'number', 'minimum' => 0],
                'total_high_hours' => ['type' => 'number', 'minimum' => 0]
            ],
            'required' => [
                'tasks',
                'project_components',
                'procurement_complexity',
                'expected_site_visits',
                'scheduling_pattern',
                'elapsed_duration_low_days',
                'elapsed_duration_high_days',
                'total_low_hours',
                'total_likely_hours',
                'total_high_hours'
            ],
            'additionalProperties' => false
        ]
    ],
    'required' => [
        'understood_job',
        'estimated_price',
        'structured_estimate'
    ],
    'additionalProperties' => false
];

$aiModel = getenv('AI_INTAKE_MODEL');
if (!is_string($aiModel) || trim($aiModel) === '') {
    $aiModel = 'gpt-5.6-luna';
}

$payload = [
    'model' => trim($aiModel),
    'reasoning' => [
        'effort' => 'low'
    ],
    'instructions' => 'You are an AI intake assistant and experienced practical estimator for Mike Of All Trades in Victoria, Australia. Keep customer replies short, friendly and practical, but perform detailed estimating internally. For quote-ready work, decompose the job into realistic task components and project-level shared labour before giving a total. Account for assessment, procurement/logistics, repeated mobilisation when appropriate, setup, preparation, hands-on work, cleanup/handover, pack/load and reasonable uncertainty without double-counting shared overhead. Distinguish labour hours from elapsed calendar duration. estimated_hours and estimated_price must remain numeric only. The backend will verify structured labour arithmetic. Return JSON only.',
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
    ]
];

$ch = curl_init('https://api.openai.com/v1/responses');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 120
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

/*
 * V3 two-pass architecture:
 * - Fast/low-cost model handles the natural-language intake conversation.
 * - Only when the intake says the job is quote-ready do we run a dedicated
 *   high-reasoning estimator over the SAME full conversation and attachments.
 * - The backend still owns the arithmetic sanity checks after this pass.
 */
if (!empty($parsedContent['quote_ready'])) {
    $estimatorModel = getenv('AI_ESTIMATOR_MODEL');
    if (!is_string($estimatorModel) || trim($estimatorModel) === '') {
        $estimatorModel = 'gpt-5.6-sol';
    }

    $estimatorReasoning = strtolower(trim((string)(getenv('AI_ESTIMATOR_REASONING') ?: 'high')));
    if (!in_array($estimatorReasoning, ['none', 'low', 'medium', 'high', 'xhigh', 'max'], true)) {
        $estimatorReasoning = 'high';
    }

    $estimatorInstructions = <<<'ESTIMATOR'
You are the dedicated second-pass estimator for Mike Of All Trades in Victoria, Australia. The intake conversation has already established that the customer wants an indicative quote. Your job is NOT to chat with the customer. Your job is to convert the complete conversation, stated measurements, photos and PDF evidence into a realistic structured labour plan.

Estimate Mike's REAL business labour consumed, not idealised trade-production time. Break each scope item into practical components including assessment/planning, procurement/logistics where applicable, unload/setup, preparation, hands-on work, cleanup/handover, pack/load and reasonable uncertainty. Put truly shared project labour in project_components so it is not duplicated in every task.

Important estimating behaviour:
- Use explicit customer measurements when supplied. Do not invent missing measurements from photos.
- Existing-property renovation and maintenance work must include preparation, access, protection, checking existing conditions, minor adjustment and cleanup that genuinely apply.
- Painting estimates must account for preparation, masking/protection, cutting-in, rolling/brushing, likely multiple coats where appropriate, between-stage handling, and the fact that drying/curing can require return visits. Drying time itself is not labour.
- Fascia, window-frame, deck and exterior work must include access/repositioning and preparation appropriate to the stated scope.
- Procurement is labour when Mike is supplying materials. Multi-scope jobs may require staged collection, stock checking, loading and occasional repeat supplier visits. Do not equate receipts with trips.
- Site visits are substantial attendances, not automatic 8-hour blocks. Long work sessions can reduce visit count, but do not compress a drying-dependent, multi-scope project into an implausibly tiny number of visits.
- Distinguish labour hours from elapsed calendar duration.
- Prefer a realistic low/likely/high range. The likely figure should represent a sensible working allowance, not an optimistic best case.
- Do not inflate hours merely to hit a target. Every component must be defensible from the scope and evidence.
- Carpet work that the customer says will be handled and quoted separately by a carpet specialist should not be included in Mike's labour or price, except for any explicit Mike coordination work requested.
- estimated_price is the indicative total for Mike's quoted scope only. Respect any instruction that a third-party item is to be shown separately or excluded from Mike's tally.

Return only the required structured JSON.
ESTIMATOR;

    $estimatorUserContent = [
        [
            'type' => 'input_text',
            'text' => "Latest customer message: {$job}\n\nComplete conversation so far:\n{$history}"
        ]
    ];
    foreach ($attachmentContent as $attachmentPart) {
        $estimatorUserContent[] = $attachmentPart;
    }

    $estimatorPayload = [
        'model' => trim($estimatorModel),
        'reasoning' => [
            'effort' => $estimatorReasoning
        ],
        'instructions' => $estimatorInstructions,
        'input' => [
            [
                'role' => 'user',
                'content' => $estimatorUserContent
            ]
        ],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'mike_of_all_trades_dedicated_estimator',
                'strict' => true,
                'schema' => $estimatorResponseSchema
            ]
        ]
    ];

    try {
        $estimatorCh = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($estimatorCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENAI_API_KEY
            ],
            CURLOPT_POSTFIELDS => json_encode($estimatorPayload),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 180
        ]);

        $estimatorResponse = curl_exec($estimatorCh);
        if ($estimatorResponse === false) {
            throw new RuntimeException('Dedicated estimator request failed: ' . curl_error($estimatorCh));
        }

        $estimatorHttpCode = curl_getinfo($estimatorCh, CURLINFO_HTTP_CODE);
        curl_close($estimatorCh);

        $estimatorData = json_decode($estimatorResponse, true);
        if (!is_array($estimatorData)) {
            throw new RuntimeException('Dedicated estimator returned invalid JSON.');
        }
        if (isset($estimatorData['error'])) {
            throw new RuntimeException((string)($estimatorData['error']['message'] ?? 'Dedicated estimator API error.'));
        }
        if ($estimatorHttpCode < 200 || $estimatorHttpCode >= 300) {
            throw new RuntimeException('Dedicated estimator request returned HTTP ' . $estimatorHttpCode . '.');
        }

        $estimatorContent = null;
        foreach (($estimatorData['output'] ?? []) as $outputItem) {
            if (($outputItem['type'] ?? '') !== 'message') continue;
            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                    $estimatorContent = $contentItem['text'];
                    break 2;
                }
            }
        }

        if (!$estimatorContent) {
            throw new RuntimeException('Dedicated estimator response contained no structured message content.');
        }

        $estimatorParsed = json_decode($estimatorContent, true);
        if (!is_array($estimatorParsed) || !is_array($estimatorParsed['structured_estimate'] ?? null)) {
            throw new RuntimeException('Dedicated estimator returned an unexpected structured response.');
        }

        $parsedContent['structured_estimate'] = $estimatorParsed['structured_estimate'];
        if (is_numeric($estimatorParsed['estimated_price'] ?? null)) {
            $parsedContent['estimated_price'] = max(0.0, (float)$estimatorParsed['estimated_price']);
        }
        if (trim((string)($estimatorParsed['understood_job'] ?? '')) !== '') {
            $parsedContent['understood_job'] = trim((string)$estimatorParsed['understood_job']);
        }
        $parsedContent['estimator_model'] = trim($estimatorModel);
        $parsedContent['estimator_reasoning'] = $estimatorReasoning;
        $parsedContent['estimator_second_pass_used'] = true;
    } catch (Throwable $e) {
        error_log('Dedicated AI quote estimator fallback: ' . $e->getMessage());
        $parsedContent['estimator_model'] = trim($estimatorModel);
        $parsedContent['estimator_reasoning'] = $estimatorReasoning;
        $parsedContent['estimator_second_pass_used'] = false;
    }

    /*
     * Do not expose a model-authored hour/day/price guess in the chat bubble.
     * The customer sees numbers only on the quote-review page after backend
     * verification and benchmark normalisation have completed.
     */
    $parsedContent['reply'] = 'I have enough information to prepare an indicative estimate. Please press “Review quote form” below to review the detailed labour and pricing allowance before sending anything.';
}

function normaliseEstimateHours($value): float {
    if (!is_numeric($value)) {
        return 0.0;
    }

    return max(0.0, round((float)$value, 2));
}

function taskBenchmarkFloor(string $title, string $scope, float $quantity, string $unit): ?array {
    $text = strtolower(trim($title . ' ' . $scope));
    $unitText = strtolower(trim($unit));

    $hasAny = static function (array $needles) use ($text): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($text, $needle) !== false) return true;
        }
        return false;
    };

    $isLinearMetres =
        $quantity > 0 &&
        (
            strpos($unitText, 'lm') !== false ||
            strpos($unitText, 'linear') !== false ||
            strpos($unitText, 'metre') !== false ||
            $unitText === 'm'
        );

    $isSquareMetres =
        $quantity > 0 &&
        (
            strpos($unitText, 'm2') !== false ||
            strpos($unitText, 'm²') !== false ||
            strpos($unitText, 'sqm') !== false ||
            strpos($unitText, 'square') !== false
        );

    if ($hasAny(['roof tile', 'roof tiles', 'tiled roof']) && $hasAny(['clean', 'wash', 'pressure'])) {
        return ['low' => 8.0, 'likely' => 10.0, 'high' => 12.0, 'basis' => 'roof-cleaning benchmark'];
    }

    if ($hasAny(['gutter']) && $hasAny(['clean', 'clear'])) {
        return ['low' => 2.0, 'likely' => 3.0, 'high' => 4.0, 'basis' => 'gutter-cleaning benchmark'];
    }

    if ($hasAny(['fascia']) && $hasAny(['replace', 'replacement', 'renew'])) {
        if ($isLinearMetres) {
            return [
                'low' => round($quantity * 0.40, 2),
                'likely' => round($quantity * 0.60, 2),
                'high' => round($quantity * 0.80, 2),
                'basis' => 'fascia replacement benchmark by stated linear metres'
            ];
        }
        return ['low' => 16.0, 'likely' => 24.0, 'high' => 32.0, 'basis' => 'fascia replacement benchmark'];
    }

    if ($hasAny(['weatherboard']) && $hasAny(['paint', 'repaint', 'coat'])) {
        return ['low' => 16.0, 'likely' => 24.0, 'high' => 32.0, 'basis' => 'weatherboard preparation/repaint benchmark'];
    }

    if ($hasAny(['vegetation', 'overgrown', 'growth']) && $hasAny(['remove', 'clear', 'cut', 'trim'])) {
        return ['low' => 4.0, 'likely' => 8.0, 'high' => 12.0, 'basis' => 'vegetation-removal benchmark'];
    }

    if ($hasAny(['deck']) && $hasAny(['sand', 'varnish', 'recoat', 'coat', 'oil'])) {
        if ($isSquareMetres) {
            $fullSand = $hasAny(['sand', 'sanding']);
            $rates = $fullSand
                ? ['low' => 1.00, 'likely' => 1.30, 'high' => 1.60]
                : ['low' => 0.60, 'likely' => 0.80, 'high' => 1.00];
            return [
                'low' => round($quantity * $rates['low'], 2),
                'likely' => round($quantity * $rates['likely'], 2),
                'high' => round($quantity * $rates['high'], 2),
                'basis' => 'deck preparation/recoat benchmark by stated square metres'
            ];
        }
        return ['low' => 12.0, 'likely' => 20.0, 'high' => 32.0, 'basis' => 'deck preparation/recoat benchmark'];
    }

    if (
        $hasAny(['internal wall', 'interior wall', 'inside wall']) &&
        $hasAny(['paint', 'repaint', 'coat'])
    ) {
        if ($isSquareMetres) {
            return [
                'low' => round($quantity * 0.11, 2),
                'likely' => round($quantity * 0.14, 2),
                'high' => round($quantity * 0.17, 2),
                'basis' => 'internal wall repaint benchmark by stated paintable surface area'
            ];
        }
        return ['low' => 32.0, 'likely' => 40.0, 'high' => 48.0, 'basis' => 'internal wall repaint benchmark'];
    }

    if ($hasAny(['window frame', 'window frames']) && $hasAny(['paint', 'repaint', 'coat'])) {
        return ['low' => 16.0, 'likely' => 28.0, 'high' => 40.0, 'basis' => 'window-frame preparation/repaint benchmark'];
    }

    return null;
}

function normaliseStructuredEstimate(array $estimate, bool $quoteReady): array {
    $tasks = [];
    $projectComponents = [];
    $sum = ['low' => 0.0, 'likely' => 0.0, 'high' => 0.0];
    $categoryTotals = [];
    $modelLikelyBeforeBackendFloors = 0.0;

    $normaliseComponent = static function (array $component): array {
        $low = normaliseEstimateHours($component['low_hours'] ?? 0);
        $likely = normaliseEstimateHours($component['likely_hours'] ?? 0);
        $high = normaliseEstimateHours($component['high_hours'] ?? 0);

        if ($likely < $low) $likely = $low;
        if ($high < $likely) $high = $likely;

        return [
            'category' => (string)($component['category'] ?? 'other'),
            'label' => trim((string)($component['label'] ?? '')),
            'explanation' => trim((string)($component['explanation'] ?? '')),
            'low_hours' => $low,
            'likely_hours' => $likely,
            'high_hours' => $high
        ];
    };

    $addComponentToTotals = static function (array $component) use (&$sum, &$categoryTotals): void {
        $sum['low'] += (float)$component['low_hours'];
        $sum['likely'] += (float)$component['likely_hours'];
        $sum['high'] += (float)$component['high_hours'];
        $category = (string)($component['category'] ?? 'other');
        if (!isset($categoryTotals[$category])) {
            $categoryTotals[$category] = ['low' => 0.0, 'likely' => 0.0, 'high' => 0.0];
        }
        $categoryTotals[$category]['low'] += (float)$component['low_hours'];
        $categoryTotals[$category]['likely'] += (float)$component['likely_hours'];
        $categoryTotals[$category]['high'] += (float)$component['high_hours'];
    };

    if ($quoteReady) {
        foreach (($estimate['tasks'] ?? []) as $task) {
            if (!is_array($task)) continue;

            $title = trim((string)($task['title'] ?? 'Task'));
            $scope = trim((string)($task['scope'] ?? ''));
            $quantity = normaliseEstimateHours($task['quantity'] ?? 0);
            $unit = trim((string)($task['unit'] ?? ''));
            $components = [];
            $taskTotals = ['low' => 0.0, 'likely' => 0.0, 'high' => 0.0];

            foreach (($task['components'] ?? []) as $component) {
                if (!is_array($component)) continue;
                $normalised = $normaliseComponent($component);
                $components[] = $normalised;
                $taskTotals['low'] += $normalised['low_hours'];
                $taskTotals['likely'] += $normalised['likely_hours'];
                $taskTotals['high'] += $normalised['high_hours'];
            }

            $modelLikelyBeforeBackendFloors += $taskTotals['likely'];

            $benchmark = taskBenchmarkFloor($title, $scope, $quantity, $unit);
            if ($benchmark !== null) {
                $targetLow = max($taskTotals['low'], (float)$benchmark['low']);
                $targetLikely = max($taskTotals['likely'], (float)$benchmark['likely'], $targetLow);
                $targetHigh = max($taskTotals['high'], (float)$benchmark['high'], $targetLikely);

                $adjustLow = max(0.0, round($targetLow - $taskTotals['low'], 2));
                $adjustLikely = max($adjustLow, round($targetLikely - $taskTotals['likely'], 2));
                $adjustHigh = max($adjustLikely, round($targetHigh - $taskTotals['high'], 2));

                $adjustment = [
                    'category' => 'other',
                    'label' => 'Practical productivity allowance',
                    'explanation' => 'Backend sanity check using ' . $benchmark['basis'] . ' so the quoted task is not based on an unrealistically optimistic best-case labour rate.',
                    'low_hours' => $adjustLow,
                    'likely_hours' => $adjustLikely,
                    'high_hours' => $adjustHigh
                ];

                if ($adjustLow > 0 || $adjustLikely > 0 || $adjustHigh > 0) {
                    $components[] = $adjustment;
                    $taskTotals['low'] += $adjustLow;
                    $taskTotals['likely'] += $adjustLikely;
                    $taskTotals['high'] += $adjustHigh;
                }
            }

            foreach ($components as $component) {
                $addComponentToTotals($component);
            }

            $tasks[] = [
                'title' => $title,
                'scope' => $scope,
                'quantity' => $quantity,
                'unit' => $unit,
                'assumptions' => array_values(array_filter(array_map(
                    static fn($v) => trim((string)$v),
                    is_array($task['assumptions'] ?? null) ? $task['assumptions'] : []
                ))),
                'components' => $components,
                'backend_task_low_hours' => round($taskTotals['low'], 2),
                'backend_task_likely_hours' => round($taskTotals['likely'], 2),
                'backend_task_high_hours' => round($taskTotals['high'], 2)
            ];
        }

        foreach (($estimate['project_components'] ?? []) as $component) {
            if (!is_array($component)) continue;
            $normalised = $normaliseComponent($component);
            $projectComponents[] = $normalised;
            $modelLikelyBeforeBackendFloors += $normalised['likely_hours'];
            $addComponentToTotals($normalised);
        }
    }

    $complexityOrder = ['none' => 0, 'low' => 1, 'moderate' => 2, 'high' => 3, 'very_high' => 4];
    $requestedComplexity = (string)($estimate['procurement_complexity'] ?? 'none');
    if (!array_key_exists($requestedComplexity, $complexityOrder)) $requestedComplexity = 'none';

    $taskCount = count($tasks);
    $minimumComplexity = $taskCount >= 8 ? 'very_high' : ($taskCount >= 5 ? 'high' : ($taskCount >= 3 ? 'moderate' : 'low'));
    $procurementComplexity = !$quoteReady
        ? 'none'
        : (
            $complexityOrder[$minimumComplexity] > $complexityOrder[$requestedComplexity]
                ? $minimumComplexity
                : $requestedComplexity
        );

    $procurementFloors = [
        'none' => ['low' => 0.0, 'likely' => 0.0, 'high' => 0.0],
        'low' => ['low' => 0.75, 'likely' => 1.5, 'high' => 2.5],
        'moderate' => ['low' => 1.5, 'likely' => 3.0, 'high' => 5.0],
        'high' => ['low' => 3.0, 'likely' => 6.0, 'high' => 10.0],
        'very_high' => ['low' => 5.0, 'likely' => 10.0, 'high' => 16.0]
    ];

    if ($quoteReady) {
        $existingProcurement = $categoryTotals['procurement_logistics'] ?? ['low' => 0.0, 'likely' => 0.0, 'high' => 0.0];
        $floor = $procurementFloors[$procurementComplexity];
        $targetLow = max($existingProcurement['low'], $floor['low']);
        $targetLikely = max($existingProcurement['likely'], $floor['likely'], $targetLow);
        $targetHigh = max($existingProcurement['high'], $floor['high'], $targetLikely);
        $procLow = max(0.0, round($targetLow - $existingProcurement['low'], 2));
        $procLikely = max($procLow, round($targetLikely - $existingProcurement['likely'], 2));
        $procHigh = max($procLikely, round($targetHigh - $existingProcurement['high'], 2));
        $procurementAdjustment = [
            'category' => 'procurement_logistics',
            'label' => 'Project procurement / supplier logistics allowance',
            'explanation' => 'Allows for realistic sourcing, stock checking, collection, loading and reasonable staged or repeat procurement for a multi-scope existing-property job.',
            'low_hours' => $procLow,
            'likely_hours' => $procLikely,
            'high_hours' => $procHigh
        ];
        if ($procurementAdjustment['low_hours'] > 0 || $procurementAdjustment['likely_hours'] > 0 || $procurementAdjustment['high_hours'] > 0) {
            $projectComponents[] = $procurementAdjustment;
            $addComponentToTotals($procurementAdjustment);
        }

        $derivedVisits = 1;
        if ($sum['likely'] > 12) {
            $derivedVisits = max(2, (int)ceil($sum['likely'] / 14.0));
        }
        $modelVisits = max(0, (int)($estimate['expected_site_visits'] ?? 0));
        $expectedSiteVisits = max($modelVisits, $derivedVisits);

        if ($expectedSiteVisits > 1) {
            $repeatVisits = $expectedSiteVisits - 1;
            $visitAdjustment = [
                'category' => 'other',
                'label' => 'Repeated site attendance / mobilisation allowance',
                'explanation' => 'Backend allowance for repeated arrival, shared setup, making-safe, end-of-session cleanup and pack/load across a deliberately intermittent multi-visit project. Task-specific setup remains separate.',
                'low_hours' => round($repeatVisits * 0.35, 2),
                'likely_hours' => round($repeatVisits * 0.50, 2),
                'high_hours' => round($repeatVisits * 0.75, 2)
            ];
            $projectComponents[] = $visitAdjustment;
            $addComponentToTotals($visitAdjustment);
        }
    } else {
        $expectedSiteVisits = 0;
    }

    $elapsedLow = normaliseEstimateHours($estimate['elapsed_duration_low_days'] ?? 0);
    $elapsedHigh = normaliseEstimateHours($estimate['elapsed_duration_high_days'] ?? 0);
    $schedulingPattern = trim((string)($estimate['scheduling_pattern'] ?? ''));

    if ($quoteReady && $sum['likely'] >= 60 && $expectedSiteVisits > 1) {
        $elapsedLow = max($elapsedLow, round($expectedSiteVisits * 2.0, 1));
        $elapsedHigh = max($elapsedHigh, round($expectedSiteVisits * 3.0, 1), $elapsedLow);
        $schedulingPattern = 'Generally intermittent / alternate-day attendance for a larger project, with substantial work sessions where practical while preserving capacity for smaller existing-customer jobs. Weather, drying/curing, access and sequencing can alter the exact pattern.';
    }

    return [
        'tasks' => $tasks,
        'project_components' => $projectComponents,
        'procurement_complexity' => $procurementComplexity,
        'expected_site_visits' => $expectedSiteVisits,
        'scheduling_pattern' => $schedulingPattern,
        'elapsed_duration_low_days' => $elapsedLow,
        'elapsed_duration_high_days' => $elapsedHigh,
        'total_low_hours' => round($sum['low'], 2),
        'total_likely_hours' => round($sum['likely'], 2),
        'total_high_hours' => round($sum['high'], 2),
        'model_likely_hours_before_backend_floors' => round($modelLikelyBeforeBackendFloors, 2),
        'backend_verified' => true,
        'backend_benchmarks_version' => 'v3'
    ];
}

$quoteReady = !empty($parsedContent['quote_ready']);
$structuredEstimate = normaliseStructuredEstimate(
    is_array($parsedContent['structured_estimate'] ?? null)
        ? $parsedContent['structured_estimate']
        : [],
    $quoteReady
);

$parsedContent['structured_estimate'] = $structuredEstimate;

if ($quoteReady && $structuredEstimate['total_likely_hours'] > 0) {
    $verifiedHours = (float)$structuredEstimate['total_likely_hours'];
    $modelHoursBeforeFloors = (float)($structuredEstimate['model_likely_hours_before_backend_floors'] ?? 0);
    $modelPrice = is_numeric($parsedContent['estimated_price'] ?? null)
        ? max(0.0, (float)$parsedContent['estimated_price'])
        : 0.0;

    $parsedContent['estimated_hours'] = $verifiedHours;

    /*
     * Keep the AI's existing pricing logic/discount assumptions, but scale
     * the price in proportion to the backend-verified labour total. This
     * avoids silently inventing a universal hourly rate while preventing
     * the old price from remaining attached to a much larger verified job.
     */
    if ($modelPrice > 0 && $modelHoursBeforeFloors > 0) {
        $scaledPrice = $modelPrice * ($verifiedHours / $modelHoursBeforeFloors);
        $parsedContent['estimated_price'] = max(50.0, round($scaledPrice / 50.0) * 50.0);
    }
}

if (!$quoteReady) {
    $parsedContent['estimated_hours'] = 0;
    $parsedContent['estimated_price'] = 0;
}

echo json_encode([
    'success' => true,
    'raw' => json_encode(
        $parsedContent,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ),
    'parsed' => $parsedContent
]);
