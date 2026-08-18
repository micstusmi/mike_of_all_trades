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

function rotateManualJpegFromExif($image, string $tmpPath)
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

function prepareManualAttachment(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One of the uploaded files could not be received.');
    }

    if (($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('One of the uploaded files is empty.');
    }

    if (($file['size'] ?? 0) > MANUAL_MAX_FILE_BYTES) {
        throw new RuntimeException('Each attachment must be 10 MB or smaller.');
    }

    $tmp = $file['tmp_name'] ?? '';

    if (!$tmp || !is_uploaded_file($tmp)) {
        throw new RuntimeException('One of the uploaded files could not be verified.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    $allowed = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf'
    ];

    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Attachments must be JPG, PNG, WEBP or PDF files.');
    }

    $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string)($file['name'] ?? 'attachment')));

    if ($mime === 'application/pdf') {
        $bytes = file_get_contents($tmp);

        if ($bytes === false) {
            throw new RuntimeException('An uploaded PDF could not be read.');
        }

        return [
            'name' => $safeName ?: 'document.pdf',
            'mime' => 'application/pdf',
            'bytes' => $bytes
        ];
    }

    $raw = file_get_contents($tmp);

    if ($raw === false) {
        throw new RuntimeException('An uploaded image could not be read.');
    }

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $source = @imagecreatefromstring($raw);

        if ($source !== false) {
            if ($mime === 'image/jpeg') {
                $rotated = rotateManualJpegFromExif($source, $tmp);

                if ($rotated !== $source) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $longEdge = max($width, $height);

            if ($longEdge > MANUAL_IMAGE_MAX_EDGE) {
                $scale = MANUAL_IMAGE_MAX_EDGE / $longEdge;
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
            imagejpeg($source, null, MANUAL_IMAGE_JPEG_QUALITY);
            $jpeg = ob_get_clean();
            imagedestroy($source);

            if ($jpeg !== false && $jpeg !== '') {
                $base = pathinfo($safeName, PATHINFO_FILENAME);
                $safeName = ($base ?: 'photo') . '.jpg';

                return [
                    'name' => $safeName,
                    'mime' => 'image/jpeg',
                    'bytes' => $jpeg
                ];
            }
        }
    }

    return [
        'name' => $safeName ?: 'photo',
        'mime' => $mime,
        'bytes' => $raw
    ];
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
            $value = trim((string)constant($constant));

            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }
    }

    throw new RuntimeException(
        'Manual quote email is not configured yet. Add a valid MIKE_CONTACT_EMAIL constant to includes/config.php.'
    );
}

function sendManualRequestEmail(
    string $recipient,
    string $subject,
    string $body,
    string $replyTo,
    array $attachments
): bool {
    $boundary = '=_MOT_' . bin2hex(random_bytes(12));

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: Mike Of All Trades Website <' . $recipient . '>';
    $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $message = '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body . "\r\n";

    foreach ($attachments as $attachment) {
        $encoded = chunk_split(base64_encode($attachment['bytes']));
        $filename = addcslashes($attachment['name'], '"\\');

        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $filename . '"' . "\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
        $message .= $encoded . "\r\n";
    }

    $message .= '--' . $boundary . "--\r\n";

    return mail(
        $recipient,
        $subject,
        $message,
        implode("\r\n", $headers)
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
            $incoming = normaliseFiles($_FILES['attachments']);

            $incoming = array_values(array_filter(
                $incoming,
                fn($file) => ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ));

            if (count($incoming) > MANUAL_MAX_ATTACHMENTS) {
                throw new Exception('Please upload no more than 10 attachments.');
            }

            $totalBytes = 0;

            foreach ($incoming as $file) {
                $prepared = prepareManualAttachment($file);
                $totalBytes += strlen($prepared['bytes']);

                if ($totalBytes > MANUAL_MAX_TOTAL_BYTES) {
                    throw new Exception(
                        'The combined attachments are too large to email safely. Please remove some files or use smaller PDFs.'
                    );
                }

                $attachments[] = $prepared;
            }
        }

        $recipient = getManualQuoteRecipient();

        $subject = $footerEnquiryType === 'quote'
            ? 'Manual quote request - ' . $name
            : 'Website contact enquiry - ' . $name;

        $body = "New website request\n\n";
        $body .= "Type: " . ($footerEnquiryType === 'quote' ? 'Manual quote request' : 'General contact') . "\n";
        $body .= "Name: {$name}\n";
        $body .= "Email: {$email}\n";
        $body .= "Service: {$service}\n";
        $body .= "Attachments: " . count($attachments) . "\n\n";
        $body .= "Message:\n{$messageText}\n";

        $sent = sendManualRequestEmail(
            $recipient,
            $subject,
            $body,
            $email,
            $attachments
        );

        if (!$sent) {
            throw new Exception(
                'Your request could not be emailed. Please try again or contact Mike another way.'
            );
        }

        echo json_encode([
            'success' => true,
            'manual_request' => true,
            'message' => $footerEnquiryType === 'quote'
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
