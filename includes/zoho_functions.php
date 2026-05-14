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
 * GENERIC ZOHO REQUEST
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
 * FIND CUSTOMER BY EMAIL
 * PREVENT DUPLICATES
 * ---------------------------------------------------------
 */
function findZohoCustomerByEmail($email) {

    $url = "https://www.zohoapis.com.au/invoice/v3/contacts"
         . "?organization_id=" . ZOHO_ORG_ID
         . "&email=" . urlencode($email);

    $res = zohoRequest("GET", $url);

    return $res['json']['contacts'][0]['contact_id'] ?? null;
}

/**
 * ---------------------------------------------------------
 * GET OR CREATE CUSTOMER
 * ---------------------------------------------------------
 */
function getOrCreateZohoCustomer($name, $email, $phone, $address) {

    // Try existing first
    $existing = findZohoCustomerByEmail($email);

    if ($existing) {
        return $existing;
    }

    // Create new customer
    $url = "https://www.zohoapis.com.au/invoice/v3/contacts"
         . "?organization_id=" . ZOHO_ORG_ID;

    $payload = [

        "contact_name" => $name,

        "company_name" => $name,

        "contact_type" => "customer",

        "contact_persons" => [
            [
                "first_name" => $name,
                "email"      => $email,
                "phone"      => $phone,
                "is_primary_contact" => true
            ]
        ],

        "billing_address" => [
            "attention" => $name,
            "address"   => $address
        ]
    ];

    $res = zohoRequest("POST", $url, $payload);

    return $res['json']['contact']['contact_id'] ?? null;
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
                "name"        => "Project Works",
                "description" => $service_description,
                "rate"        => (float)$total,
                "quantity"    => 1
            ]
        ],

        "notes" =>
            "Thank you for the opportunity to provide this quotation.\n\n"
          . "Please review the scope carefully and contact us if you require any adjustments.\n\n"
          . "Quote valid for 30 days.",

        "terms" =>
            "Acceptance of this quotation constitutes approval to proceed with the described works."
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

        "send_from_org_email_id" => true
    ];

    return zohoRequest("POST", $url, $payload);
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