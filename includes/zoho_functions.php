<?php

/**
 * LOAD PRIVATE CONFIG
 */
require_once __DIR__ . '/config.php';

/**
 * ---------------------------------------------------------
 * GET ZOHO ACCESS TOKEN
 * ---------------------------------------------------------
 */
function getZohoAccessToken() {

    $url = "https://accounts.zoho.com.au/oauth/v2/token";

    $post = [
        'refresh_token' => ZOHO_REFRESH_TOKEN,
        'client_id'     => ZOHO_CLIENT_ID,
        'client_secret' => ZOHO_CLIENT_SECRET,
        'grant_type'    => 'refresh_token'
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => http_build_query($post),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_SSL_VERIFYPEER  => true
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('Zoho Token CURL Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    $json = json_decode($response, true);

    if (!isset($json['access_token'])) {
        error_log('Zoho Token Error Response: ' . $response);
        return null;
    }

    return $json['access_token'];
}

/**
 * ---------------------------------------------------------
 * GENERIC ZOHO JSON REQUEST
 * ---------------------------------------------------------
 */
function zohoRequest($method, $url, $payload = null) {

    $token = getZohoAccessToken();

    if (!$token) {
        return [
            'code' => 500,
            'raw'  => 'Failed to generate Zoho access token',
            'json' => null
        ];
    }

    $ch = curl_init($url);

    $headers = [
        "Authorization: Zoho-oauthtoken {$token}",
        "Content-Type: application/json"
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $result = curl_exec($ch);

    if (curl_errno($ch)) {

        $error = curl_error($ch);

        curl_close($ch);

        return [
            'code' => 500,
            'raw'  => $error,
            'json' => null
        ];
    }

    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'code' => $http,
        'raw'  => $result,
        'json' => json_decode($result, true)
    ];
}

/**
 * ---------------------------------------------------------
 * GENERIC ZOHO MULTIPART REQUEST WITH ATTACHMENTS
 * ---------------------------------------------------------
 *
 * Used by:
 * - Email an estimate with customer-supplied source files
 * - Email a Zoho contact (manual quote request) with attachments
 *
 * Files remain in PHP's temporary upload area only for the duration
 * of the request. This function does not copy them into permanent
 * web-server storage.
 */
function zohoMultipartRequest(
    $url,
    array $payload,
    array $files = [],
    string $fileFieldName = 'attachments'
) {
    $token = getZohoAccessToken();

    if (!$token) {
        return [
            'code' => 500,
            'raw'  => 'Failed to generate Zoho access token',
            'json' => null
        ];
    }

    $boundary =
        '--------------------------'
        . bin2hex(random_bytes(12));

    $eol = "\r\n";
    $body = '';

    /*
     * Zoho's multipart APIs accept the structured request data
     * in a JSONString field alongside binary attachment parts.
     */
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="JSONString"' . $eol;
    $body .= 'Content-Type: application/json; charset=UTF-8' . $eol . $eol;
    $body .= json_encode($payload, JSON_UNESCAPED_SLASHES) . $eol;

    foreach ($files as $file) {
        $tmpPath = $file['tmp_name'] ?? '';
        $filename = basename((string)($file['name'] ?? 'attachment'));
        $mime = (string)($file['mime'] ?? 'application/octet-stream');

        if (!$tmpPath || !is_file($tmpPath)) {
            continue;
        }

        $bytes = file_get_contents($tmpPath);

        if ($bytes === false) {
            continue;
        }

        $safeFilename =
            str_replace(
                ['"', "\r", "\n"],
                ['_', '', ''],
                $filename
            );

        $body .= '--' . $boundary . $eol;
        $body .=
            'Content-Disposition: form-data; name="'
            . $fileFieldName
            . '"; filename="'
            . $safeFilename
            . '"'
            . $eol;

        $body .=
            'Content-Type: '
            . $mime
            . $eol;

        $body .=
            'Content-Transfer-Encoding: binary'
            . $eol
            . $eol;

        $body .= $bytes . $eol;
    }

    $body .= '--' . $boundary . '--' . $eol;

    $headers = [
        "Authorization: Zoho-oauthtoken {$token}",
        "X-com-zoho-invoice-organizationid: " . ZOHO_ORG_ID,
        "Content-Type: multipart/form-data; boundary={$boundary}",
        "Content-Length: " . strlen($body)
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'code' => 500,
            'raw' => $error,
            'json' => null
        ];
    }

    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $http,
        'raw' => $result,
        'json' => json_decode($result, true)
    ];
}

/**
 * ---------------------------------------------------------
 * ESTIMATE EMAIL MULTIPART WRAPPER
 * ---------------------------------------------------------
 */
function zohoEstimateEmailMultipartRequest(
    $estimate_id,
    array $payload,
    array $files = []
) {
    $url =
        "https://www.zohoapis.com.au/invoice/v3/estimates/"
        . rawurlencode((string)$estimate_id)
        . "/email?organization_id="
        . rawurlencode((string)ZOHO_ORG_ID);

    return zohoMultipartRequest(
        $url,
        $payload,
        $files,
        'attachments'
    );
}

/**
 * ---------------------------------------------------------
 * EMAIL A ZOHO CONTACT WITH OPTIONAL ATTACHMENTS
 * ---------------------------------------------------------
 *
 * This is ideal for the footer's "Ask Mike for a quote" request:
 * no formal price/estimate has been created yet, but Zoho Invoice
 * can still send Mike the customer's request and original files.
 */
function sendZohoContactEmailWithAttachments(
    $contact_id,
    array $toMailIds,
    string $subject,
    string $bodyText,
    array $attachments = []
) {
    $url =
        "https://www.zohoapis.com.au/invoice/v3/contacts/"
        . rawurlencode((string)$contact_id)
        . "/email?organization_id="
        . rawurlencode((string)ZOHO_ORG_ID);

    $payload = [
        "to_mail_ids" => array_values($toMailIds),
        "subject" => $subject,
        "body" => nl2br(
            htmlspecialchars(
                $bodyText,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
        )
    ];

    if (empty($attachments)) {
        return zohoRequest(
            "POST",
            $url,
            $payload
        );
    }

    return zohoMultipartRequest(
        $url,
        $payload,
        $attachments,
        'attachments'
    );
}

/**
 * ---------------------------------------------------------
 * ZOHO CUSTOMER SEARCH AND DUPLICATE PROTECTION
 * ---------------------------------------------------------
 */

function zohoNormaliseContactPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (str_starts_with($digits, '61') && strlen($digits) >= 11) {
        $digits = '0' . substr($digits, 2);
    }

    return $digits;
}

function zohoContactEmailValues(array $contact): array
{
    $values = [];

    if (!empty($contact['email'])) {
        $values[] = strtolower(trim((string)$contact['email']));
    }

    foreach (($contact['contact_persons'] ?? []) as $person) {
        if (!empty($person['email'])) {
            $values[] = strtolower(trim((string)$person['email']));
        }
    }

    return array_values(array_unique(array_filter($values)));
}

function zohoContactPhoneValues(array $contact): array
{
    $values = [];

    foreach (['phone', 'mobile'] as $field) {
        if (!empty($contact[$field])) {
            $values[] = zohoNormaliseContactPhone((string)$contact[$field]);
        }
    }

    foreach (($contact['contact_persons'] ?? []) as $person) {
        foreach (['phone', 'mobile'] as $field) {
            if (!empty($person[$field])) {
                $values[] = zohoNormaliseContactPhone((string)$person[$field]);
            }
        }
    }

    return array_values(array_unique(array_filter($values)));
}

function zohoListContacts(array $filters): array
{
    $url = 'https://www.zohoapis.com.au/invoice/v3/contacts?'
         . http_build_query(
             array_merge(
                 ['organization_id' => ZOHO_ORG_ID],
                 $filters
             )
         );

    $response = zohoRequest('GET', $url);

    if (
        ($response['code'] ?? 500) < 200
        || ($response['code'] ?? 500) >= 300
        || !is_array($response['json'] ?? null)
    ) {
        throw new RuntimeException(
            'Zoho customer search failed. No customer was created. '
            . mb_substr((string)($response['raw'] ?? 'Unknown Zoho response'), 0, 500)
        );
    }

    return is_array($response['json']['contacts'] ?? null)
        ? $response['json']['contacts']
        : [];
}

function findZohoCustomersByEmail(string $email): array
{
    $email = strtolower(trim($email));

    if ($email === '') {
        return [];
    }

    $contacts = zohoListContacts(['email' => $email]);

    return array_values(
        array_filter(
            $contacts,
            static fn(array $contact): bool =>
                in_array($email, zohoContactEmailValues($contact), true)
        )
    );
}

function findZohoCustomersByPhone(string $phone): array
{
    $normalised = zohoNormaliseContactPhone($phone);

    if ($normalised === '') {
        return [];
    }

    $searchDigits = strlen($normalised) > 8
        ? substr($normalised, -8)
        : $normalised;

    $contacts = zohoListContacts(['phone_contains' => $searchDigits]);

    return array_values(
        array_filter(
            $contacts,
            static fn(array $contact): bool =>
                in_array($normalised, zohoContactPhoneValues($contact), true)
        )
    );
}

function findZohoCustomerByEmail($email)
{
    $matches = findZohoCustomersByEmail((string)$email);

    return $matches[0]['contact_id'] ?? null;
}

function findZohoCustomerMatch(string $email, string $phone): ?array
{
    $matches = [];

    foreach (findZohoCustomersByEmail($email) as $contact) {
        if (!empty($contact['contact_id'])) {
            $matches[(string)$contact['contact_id']] = $contact;
        }
    }

    foreach (findZohoCustomersByPhone($phone) as $contact) {
        if (!empty($contact['contact_id'])) {
            $matches[(string)$contact['contact_id']] = $contact;
        }
    }

    if (count($matches) > 1) {
        throw new RuntimeException(
            'The supplied email and mobile match different Zoho customers. '
            . 'Please review the Zoho customer records before creating this job.'
        );
    }

    return $matches ? reset($matches) : null;
}

function getZohoCustomerById(string $contactId): array
{
    $contactId = trim($contactId);

    if ($contactId === '' || !ctype_digit($contactId)) {
        throw new InvalidArgumentException('Invalid Zoho customer selection.');
    }

    $url = 'https://www.zohoapis.com.au/invoice/v3/contacts/'
         . rawurlencode($contactId)
         . '?'
         . http_build_query(['organization_id' => ZOHO_ORG_ID]);

    $response = zohoRequest('GET', $url);
    $contact = $response['json']['contact'] ?? null;

    if (
        ($response['code'] ?? 500) < 200
        || ($response['code'] ?? 500) >= 300
        || !is_array($contact)
        || (string)($contact['contact_id'] ?? '') !== $contactId
    ) {
        throw new RuntimeException(
            'The selected Zoho customer could not be verified. '
            . 'Please search for the customer again.'
        );
    }

    return $contact;
}
/**
 * Find an existing Zoho customer by exact email or normalized phone.
 * Create one only when neither value matches an existing customer.
 */
function getOrCreateZohoCustomer($name, $email, $phone, $address)
{
    $name = trim((string)$name);
    $email = strtolower(trim((string)$email));
    $phone = trim((string)$phone);
    $address = trim((string)$address);

    if ($name === '') {
        throw new InvalidArgumentException('Customer name is required.');
    }

    $existing = findZohoCustomerMatch($email, $phone);

    if ($existing) {
        return $existing['contact_id'];
    }

    $person = [
        'first_name' => $name,
        'is_primary_contact' => true
    ];

    if ($email !== '') {
        $person['email'] = $email;
    }

    if ($phone !== '') {
        $person['phone'] = $phone;
        $person['mobile'] = $phone;
    }

    $payload = [
        'contact_name' => $name,
        'company_name' => $name,
        'contact_type' => 'customer',
        'contact_persons' => [$person]
    ];

    if ($address !== '') {
        $payload['billing_address'] = [
            'attention' => $name,
            'address' => $address
        ];
    }

    $url = 'https://www.zohoapis.com.au/invoice/v3/contacts?'
         . http_build_query(['organization_id' => ZOHO_ORG_ID]);

    $response = zohoRequest('POST', $url, $payload);
    $contactId = $response['json']['contact']['contact_id'] ?? null;

    if (!$contactId) {
        throw new RuntimeException(
            'Zoho customer creation failed: '
            . mb_substr((string)($response['raw'] ?? 'Unknown Zoho response'), 0, 500)
        );
    }

    return $contactId;
}
/**
 * ---------------------------------------------------------
 * CREATE ESTIMATE
 * ---------------------------------------------------------
 */
function createZohoEstimate($customer_id, $name, $service_description, $total) {

    $url = "https://www.zohoapis.com.au/invoice/v3/estimates"
         . "?organization_id=" . ZOHO_ORG_ID;

    $payload = [

        "customer_id" => $customer_id,

        "estimate_number" => "",

        "reference_number" => "",

        "line_items" => [
            [
                "name"           => "Project Works",
                "description"    => $service_description,
                "rate"           => (float)$total,
                "quantity"       => 1,
                "tax_id"         => "",
                "tax_name"       => "",
                "tax_percentage" => 0
            ]
        ],

        "notes" =>
            "Thank you for the opportunity to provide this quotation.\n\n"
          . "Please review the scope carefully and contact us if you require any adjustments.\n\n"
          . "Mike's ABN is not currently setup for GST so NO GST would be added.\n\n"
          . "Quote valid for 30 days.",

        "terms" =>
            "This quotation is based on the scope, information, photographs, plans and documents supplied at the time of quotation. "
          . "Only works expressly described in this quotation are included. "
          . "Changes to the scope, supplied information, site conditions, plans or specifications may result in additional charges or require a revised quotation. "
          . "Acceptance of this quotation confirms acceptance of Mike Of All Trades' Terms & Conditions available at www.mikeofalltrades.com.au/terms."
    ];

    return zohoRequest("POST", $url, $payload);
}

/**
 * ---------------------------------------------------------
 * SEND ESTIMATE EMAIL
 * USE ZOHO EMAIL TEMPLATE
 * ---------------------------------------------------------
 */
function sendZohoEstimate($estimate_id, $email) {

    $url = "https://www.zohoapis.com.au/invoice/v3/estimates/{$estimate_id}/email"
         . "?organization_id=" . ZOHO_ORG_ID;

    $payload = [

        "to_mail_ids" => [
            $email
        ],

        "cc_mail_ids" => [
            "mike@mikeofalltrades.com.au"
        ],

        "send_from_org_email_id" => true
    ];

    return zohoRequest("POST", $url, $payload);
}

/**
 * ---------------------------------------------------------
 * SEND ESTIMATE EMAIL WITH ORIGINAL CUSTOMER FILES
 * ---------------------------------------------------------
 */
function sendZohoEstimateWithAttachments(
    $estimate_id,
    $email,
    array $attachments
) {
    if (empty($attachments)) {
        return sendZohoEstimate(
            $estimate_id,
            $email
        );
    }

    $payload = [

        "to_mail_ids" => [
            $email
        ],

        "cc_mail_ids" => [
            "mike@mikeofalltrades.com.au"
        ],

        "send_from_org_email_id" => true
    ];

    return zohoEstimateEmailMultipartRequest(
        $estimate_id,
        $payload,
        $attachments
    );
}

/**
 * ---------------------------------------------------------
 * SEND BOOKING CONFIRMATION EMAIL
 * CUSTOM BOOKING-SPECIFIC EMAIL BODY
 * ---------------------------------------------------------
 */
function sendZohoBookingEstimate($estimate_id, $email) {

    $url = "https://www.zohoapis.com.au/invoice/v3/estimates/{$estimate_id}/email"
         . "?organization_id=" . ZOHO_ORG_ID;

    $payload = [

        "to_mail_ids" => [
            $email
        ],

        "cc_mail_ids" => [
            "mike@mikeofalltrades.com.au"
        ],

        "subject" =>
            "Booking Confirmation - Mike Of All Trades",

        "body" =>
            "Hi,\n\n"
          . "Thank you for making a booking with Mike Of All Trades.\n\n"
          . "This email confirms that your requested booking has been received and added to our booking system.\n\n"
          . "Please check the attached PDF document carefully to make sure your booking details, service details, date and time are correct.\n\n"
          . "If you need to edit or cancel your booking, please log back in to:\n"
          . "https://mikeofalltrades.com.au/customer/dashboard.php\n\n"
          . "If anything looks wrong or you need help, simply reply to this email.\n\n"
          . "Kind regards,\n"
          . "Mike Of All Trades",

        "send_from_org_email_id" => true
    ];

    return zohoRequest("POST", $url, $payload);
}

/** Create a draft invoice using the existing Zoho organisation and OAuth flow. */
function createZohoInvoice(array $payload) {
    $url = "https://www.zohoapis.com.au/invoice/v3/invoices?organization_id=" . rawurlencode((string)ZOHO_ORG_ID);
    return zohoRequest("POST", $url, $payload);
}

function updateZohoInvoice($invoice_id, array $payload) {
    $url = "https://www.zohoapis.com.au/invoice/v3/invoices/" . rawurlencode((string)$invoice_id) . "?organization_id=" . rawurlencode((string)ZOHO_ORG_ID);
    return zohoRequest("PUT", $url, $payload);
}

/** Fetch the saved draft so tax and totals can be checked before email. */
function getZohoInvoice($invoice_id) {
    $url = "https://www.zohoapis.com.au/invoice/v3/invoices/" . rawurlencode((string)$invoice_id) . "?organization_id=" . rawurlencode((string)ZOHO_ORG_ID);
    return zohoRequest("GET", $url, null);
}

/** Email an existing Zoho invoice, optionally with supplier receipts. */
function sendZohoInvoiceWithAttachments($invoice_id, $email, array $attachments = []) {
    $url = "https://www.zohoapis.com.au/invoice/v3/invoices/" . rawurlencode((string)$invoice_id) . "/email?organization_id=" . rawurlencode((string)ZOHO_ORG_ID);
    $payload = ["to_mail_ids"=>[$email],"cc_mail_ids"=>["michaelssmith@icloud.com"],"send_from_org_email_id"=>true];
    return $attachments ? zohoMultipartRequest($url,$payload,$attachments,'attachments') : zohoRequest("POST",$url,$payload);
}
