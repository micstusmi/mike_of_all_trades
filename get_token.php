<?php
// get_token.php - RUN THIS ONCE THEN DELETE IT

// 1. Paste the NEW Grant Code you just generated
$grant_code = '1000.1f8a3df3a4e5e2a85fa34d75fbed38f4.f9e2b5e386115059dfb8367e36ace832'; 

// 2. Paste your REAL Client ID and Secret from the API Console
$client_id = '1000.HG60SWRWXBK0XXE3G7UIUT2DE5ADPE'; 
$client_secret = '9ec6bc3a25ddcb4f47ead3ef0d7a700e62d7b1a67c'; 

$url = "https://accounts.zoho.com.au/oauth/v2/token";
$post_data = [
    'code' => $grant_code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo "<h1>Zoho Response:</h1>";
echo "<pre>" . $result . "</pre>";
?>