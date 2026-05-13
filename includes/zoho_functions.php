<?php
// includes/zoho_functions.php

function getZohoAccessToken() {
    // USE THE NEW REFRESH TOKEN GENERATED WITH THE 'ADPE' CLIENT ID
    $refresh_token = '1000.c438a94c2d7b5e2e957bab5e312d4a66.b18e887b2c344971e11894d191c759c7'; 
    $client_id     = '1000.HG60SWRWXBK0XXE3G7UIUT2DE5ADPE';
    $client_secret = '9ec6bc3a25ddcb4f47ead3ef0d7a700e62d7b1a67c'; 

    $url = "https://accounts.zoho.com.au/oauth/v2/token";
    $post_data = [
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $result = curl_exec($ch);
    $response = json_decode($result, true);
    curl_close($ch);

    return $response['access_token'] ?? null;
}

// This helper function ensures the Guest gets the email properly
function addContactToWebLeads($email, $name, $phone) {
    $access_token = getZohoAccessToken();
    $organization_id = '7003715544';
    $master_id = '127145000000499006'; // YOUR WEB LEADS ID

    $url = "https://www.zohoapis.com.au/invoice/v3/customers/$master_id/contactpersons?organization_id=" . $organization_id;

    $data = [
        "first_name" => $name,
        "email" => $email,
        "phone" => $phone
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Zoho-oauthtoken ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $response = json_decode($result, true);
    curl_close($ch);

    return $response['contact_person']['contact_person_id'] ?? null;
}

function createZohoEstimate($customer_email, $service_name, $total_price, $customer_id, $contact_person_id = null) {
    $access_token = getZohoAccessToken();
    $organization_id = '7003715544'; 

    $url = "https://www.zohoapis.com.au/invoice/v3/estimates?organization_id=" . $organization_id . "&send=true";

    $estimate_data = [
        "customer_id" => $customer_id,
        "line_items" => [
            [ "name" => $service_name, "rate" => $total_price, "quantity" => 1 ]
        ]
    ];

    // If we have a specific contact ID, we tell Zoho to send it to them
    if ($contact_person_id) {
        $estimate_data["contact_persons"] = [$contact_person_id];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Zoho-oauthtoken ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($estimate_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}