<?php
// includes/zoho_functions.php

define('ZOHO_ORG_ID', '7003715544');

/**
 * Get Zoho Access Token (cached would be better later, but kept simple)
 */
function getZohoAccessToken() {
    $refresh_token = '1000.c438a94c2d7b5e2e957bab5e312d4a66.b18e887b2c344971e11894d191c759c7';
    $client_id     = '1000.HG60SWRWXBK0XXE3G7UIUT2DE5ADPE';
    $client_secret = '9ec6bc3a25ddcb4f47ead3ef0d7a700e62d7b1a67c';

    $url = "https://accounts.zoho.com.au/oauth/v2/token";

    $post_data = [
        'refresh_token' => $refresh_token,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'refresh_token'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);

    return $response['access_token'] ?? null;
}

/**
 * Generic Zoho API request helper (IMPORTANT for debugging)
 */
function zohoRequest($method, $url, $payload = null) {
    $token = getZohoAccessToken();

    $ch = curl_init($url);

    $headers = [
        "Authorization: Zoho-oauthtoken $token",
        "Content-Type: application/json"
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($result, true),
        'raw'  => $result
    ];
}

/**
 * Attach a contact person to your "Web Leads" customer
 */
function addContactToWebLeads($email, $name, $phone) {

    $url = "https://www.zohoapis.com.au/invoice/v3/contacts/" .
           "127145000000499006/contactpersons?organization_id=" . ZOHO_ORG_ID;

    $payload = [
        "contact_persons" => [
            [
                "first_name" => $name,
                "email"      => $email,
                "phone"      => $phone
            ]
        ]
    ];

    $res = zohoRequest("POST", $url, $payload);

    if (!empty($res['body']['contact_persons'][0]['contact_person_id'])) {
        return $res['body']['contact_persons'][0]['contact_person_id'];
    }

    return null;
}

/**
 * Create estimate (NO unreliable send=true)
 */
function createZohoEstimate($customer_id, $service_name, $total_price, $contact_person_id = null) {

    $url = "https://www.zohoapis.com.au/invoice/v3/estimates?organization_id=" . ZOHO_ORG_ID;

    $payload = [
        "customer_id" => $customer_id,
        "line_items" => [
            [
                "name"     => $service_name,
                "rate"     => (float)$total_price,
                "quantity" => 1
            ]
        ]
    ];

    if ($contact_person_id) {
        $payload["contact_persons"] = [
            ["contact_person_id" => $contact_person_id]
        ];
    }

    return zohoRequest("POST", $url, $payload);
}

/**
 * Send estimate manually (THIS is the missing piece in your old system)
 */
function sendZohoEstimate($estimate_id, $email) {

    $url = "https://www.zohoapis.com.au/invoice/v3/estimates/$estimate_id/email?organization_id=" . ZOHO_ORG_ID;

    $payload = [
        "to_mail_ids" => [$email],
        "send_from_org_email_id" => true
    ];

    return zohoRequest("POST", $url, $payload);
}