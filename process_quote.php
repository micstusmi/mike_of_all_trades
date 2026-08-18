<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/zoho_functions.php';

const MANUAL_MAX_ATTACHMENTS = 10;
const MANUAL_MAX_FILE_BYTES = 10 * 1024 * 1024;
const MANUAL_MAX_TOTAL_BYTES = 22 * 1024 * 1024;
const MANUAL_IMAGE_MAX_EDGE = 1800;
const MANUAL_IMAGE_JPEG_QUALITY = 82;

function jsonFail(string $message): void
{
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

function normaliseFiles(array $fileBag): array
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

function validateManualZohoAttachments(array $files): array
{
    $files = array_values(array_filter(
        $files,
        fn($file) =>
            ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE
    ));

    if (count($files) > MANUAL_MAX_ATTACHMENTS) {
        throw new RuntimeException(
            'Please upload no more than 10 attachments.'
        );
    }

    $validated = [];
    $totalBytes = 0;

    foreach ($files as $file) {

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'One of the uploaded files could not be received.'
            );
        }

        $size = (int)($file['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException(
                'One of the uploaded files is empty.'
            );
        }

        if ($size > MANUAL_MAX_FILE_BYTES) {
            throw new RuntimeException(
                'Each attachment must be 10 MB or smaller.'
            );
        }

        $tmpPath = $file['tmp_name'] ?? '';

        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException(
                'One of the uploaded files could not be verified.'
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
                'Attachments must be JPG, PNG, WEBP or PDF files.'
            );
        }

        $totalBytes += $size;

        if ($totalBytes > MANUAL_MAX_TOTAL_BYTES) {
            throw new RuntimeException(
                'The combined attachments are too large. Please keep the total below 22 MB.'
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

function getManualQuoteRecipient(): string
{
    $candidates = [
        'MIKE_CONTACT_EMAIL',
        'BUSINESS_EMAIL',
        'CONTACT_EMAIL',
        'ADMIN_EMAIL'
    ];

    foreach ($candidates as $constant) {
        if (defined($constant)) {
            $value = trim(
                (string)constant($constant)
            );

            if (
                filter_var(
                    $value,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                return $value;
            }
        }
    }

    /*
     * Existing business address used elsewhere in the site.
     */
    return 'mike@mikeofalltrades.com.au';
}

/**
 * Tiny manual-request duplicate guard.
 * No customer photos/PDFs are stored in this directory.
 */
function manualRequestDuplicateKey(
    string $name,
    string $email,
    string $service,
    string $message,
    array $attachments
): string {
    $parts = [];

    foreach ($attachments as $attachment) {
        $parts[] =
            ($attachment['name'] ?? '') . ':' .
            ($attachment['size'] ?? 0);
    }

    return hash(
        'sha256',
        implode('|', [
            strtolower($name),
            strtolower($email),
            $service,
            $message,
            implode(',', $parts)
        ])
    );
}

function manualRequestAlreadySent(
    string $key
): bool {
    $dir = __DIR__ . '/quote_submission_locks';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = $dir . '/manual_' . $key . '.json';

    if (!file_exists($path)) {
        return false;
    }

    $data = json_decode(
        file_get_contents($path),
        true
    );

    return
        is_array($data) &&
        !empty($data['success']) &&
        (time() - (int)($data['timestamp'] ?? 0)) < 600;
}

function markManualRequestSent(
    string $key
): void {
    $dir = __DIR__ . '/quote_submission_locks';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(
        $dir . '/manual_' . $key . '.json',
        json_encode([
            'success' => true,
            'timestamp' => time()
        ])
    );
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request');
    }

    if (!empty($_POST['website'] ?? '') || !empty($_POST['website_url'] ?? '')) {
        throw new Exception('Spam detected');
    }

    /*
     * =========================================================
     * FOOTER MANUAL CONTACT / MANUAL QUOTE REQUEST
     * =========================================================
     *
     * This is intentionally handled separately from the formal
     * Zoho estimate flow below because, at this point, Mike has
     * not yet reviewed or priced the job.
     */

    $footerEnquiryType = trim($_POST['footer_enquiry_type'] ?? '');

    if (in_array($footerEnquiryType, ['quote', 'contact'], true)) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $service = trim($_POST['service'] ?? 'General Inquiry');
        $messageText = trim($_POST['message'] ?? '');
        $mathAnswer = trim((string)($_POST['math_answer'] ?? ''));

        if ($name === '' || strlen($name) < 2) {
            throw new Exception('Name is required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid email is required');
        }

        if ($messageText === '') {
            throw new Exception('Please describe what you need.');
        }

        if ($mathAnswer !== '10') {
            throw new Exception('Spam check answer is incorrect.');
        }

        // Basic rate limit by IP address.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateDir = __DIR__ . '/quote_rate_limits';

        if (!is_dir($rateDir)) {
            mkdir($rateDir, 0755, true);
        }

        $rateFile = $rateDir . '/manual_' . md5($ip) . '.json';
        $now = time();
        $windowSeconds = 3600;
        $maxRequests = 5;
        $history = [];

        if (file_exists($rateFile)) {
            $history = json_decode(file_get_contents($rateFile), true);

            if (!is_array($history)) {
                $history = [];
            }
        }

        $history = array_filter(
            $history,
            fn($timestamp) => ($now - $timestamp) < $windowSeconds
        );

        if (count($history) >= $maxRequests) {
            throw new Exception('Too many requests. Please try again later.');
        }

        $history[] = $now;
        file_put_contents($rateFile, json_encode(array_values($history)));

        $attachments = [];

        if (
            $footerEnquiryType === 'quote' &&
            isset($_FILES['attachments'])
        ) {
            $attachments =
                validateManualZohoAttachments(
                    normaliseFiles(
                        $_FILES['attachments']
                    )
                );
        }

        /*
         * Create/find the customer's Zoho contact first.
         * No estimate is created yet because Mike has not priced the job.
         */
        $customerId =
            getOrCreateZohoCustomer(
                $name,
                $email,
                '',
                ''
            );

        if (!$customerId) {
            throw new Exception(
                'Could not create/find the customer in Zoho.'
            );
        }

        $recipient =
            getManualQuoteRecipient();

        $subject =
            $footerEnquiryType === 'quote'
                ? 'Manual quote request - ' . $name
                : 'Website contact enquiry - ' . $name;

        $body =
            "New website request\n\n";

        $body .=
            "Type: " .
            (
                $footerEnquiryType === 'quote'
                    ? 'Manual quote request'
                    : 'General contact'
            ) .
            "\n";

        $body .= "Name: {$name}\n";
        $body .= "Customer email: {$email}\n";
        $body .= "Service: {$service}\n";
        $body .= "Attachments: " . count($attachments) . "\n\n";
        $body .= "Message:\n{$messageText}\n";

        if (!empty($attachments)) {
            $body .= "\nSupplied files:\n";

            foreach ($attachments as $attachment) {
                $body .=
                    "- " .
                    $attachment['name'] .
                    "\n";
            }
        }

        $duplicateKey =
            manualRequestDuplicateKey(
                $name,
                $email,
                $service,
                $messageText,
                $attachments
            );

        if (
            manualRequestAlreadySent(
                $duplicateKey
            )
        ) {
            echo json_encode([
                'success' => true,
                'manual_request' => true,
                'duplicate_prevented' => true,
                'message' =>
                    'This request has already been sent to Mike.'
            ]);
            exit;
        }

        /*
         * Zoho Invoice sends the request to Mike and carries
         * the original customer files as email attachments.
         * The web server does not retain permanent copies.
         */
        $send =
            sendZohoContactEmailWithAttachments(
                $customerId,
                [$recipient],
                $subject,
                $body,
                $attachments
            );

        if (($send['code'] ?? 0) >= 400) {
            error_log(
                'Zoho manual request email failed: ' .
                ($send['raw'] ?? 'Unknown Zoho error')
            );

            throw new Exception(
                'Your request could not be sent to Mike. Please try again.'
            );
        }

        markManualRequestSent(
            $duplicateKey
        );

        echo json_encode([
            'success' => true,
            'manual_request' => true,
            'duplicate_prevented' => false,
            'attachment_count' => count($attachments),
            'message' =>
                $footerEnquiryType === 'quote'
                    ? 'Your quote request has been sent to Mike.'
                    : 'Your message has been sent to Mike.'
        ]);
        exit;
    }

    /*
     * =========================================================
     * EXISTING FORMAL ZOHO ESTIMATE FLOW
     * =========================================================
     */

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $service     = trim($_POST['service'] ?? 'General Works');
    $description = trim($_POST['description'] ?? '');
    $total       = (float)($_POST['total'] ?? 0);

    if ($name === '' || strlen($name) < 2) {
        throw new Exception('Name is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required');
    }

    if ($phone === '' || strlen($phone) < 8) {
        throw new Exception('Valid phone number is required');
    }

    if ($address === '' || strlen($address) < 3) {
        throw new Exception('Address or suburb is required');
    }

    if ($service === '') {
        throw new Exception('Service is required');
    }

    if ($total <= 0) {
        throw new Exception('Quote total must be greater than zero');
    }

    if ($total < 50) {
        throw new Exception('Quote total is too low');
    }

    // Basic rate limit by IP address.
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateDir = __DIR__ . '/quote_rate_limits';

    if (!is_dir($rateDir)) {
        mkdir($rateDir, 0755, true);
    }

    $rateFile = $rateDir . '/' . md5($ip) . '.json';
    $now = time();
    $windowSeconds = 3600;
    $maxRequests = 5;

    $history = [];

    if (file_exists($rateFile)) {
        $history = json_decode(file_get_contents($rateFile), true);

        if (!is_array($history)) {
            $history = [];
        }
    }

    $history = array_filter($history, function ($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });

    if (count($history) >= $maxRequests) {
        throw new Exception('Too many quote requests. Please try again later.');
    }

    $history[] = $now;
    file_put_contents($rateFile, json_encode(array_values($history)));

    /**
     * 1. CUSTOMER
     */
    $customer_id = getOrCreateZohoCustomer($name, $email, $phone, $address);

    if (!$customer_id) {
        throw new Exception('Failed to create/find customer');
    }

    /**
     * 2. CREATE ESTIMATE
     */
    $estimate = createZohoEstimate(
        $customer_id,
        $name,
        $service . ' - ' . $description,
        $total
    );

    if (($estimate['code'] ?? 0) >= 400) {
        throw new Exception('Estimate failed: ' . $estimate['raw']);
    }

    $estimate_id = $estimate['json']['estimate']['estimate_id'] ?? null;

    if (!$estimate_id) {
        throw new Exception('No estimate ID returned');
    }

    /**
     * 3. SEND EMAIL
     */
    $send = sendZohoEstimate($estimate_id, $email);

    $email_sent = true;
    $email_warning = '';

    if (($send['code'] ?? 0) >= 400) {
        $email_sent = false;
        $email_warning = $send['raw'] ?? 'Zoho email failed.';
        error_log('Zoho estimate created but email failed: ' . $email_warning);
    }

    echo json_encode([
        'success' => true,
        'estimate_id' => $estimate_id,
        'email_sent' => $email_sent,
        'email_warning' => $email_warning,
        'message' => $email_sent
            ? 'Quote created and emailed successfully.'
            : 'Quote created in Zoho, but the email was not sent.'
    ]);

} catch (Throwable $e) {
    error_log('process_quote.php error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
