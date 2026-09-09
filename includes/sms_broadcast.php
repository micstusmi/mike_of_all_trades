<?php
require_once __DIR__ . '/config.php';
// V8.5A — SMS Broadcast provider helper.
// Credentials stay in includes/config.php or environment variables.
// Official HTTP endpoint: https://www.smsbroadcast.com.au/api-adv.php

function mot_sms_cfg(string $name, ?string $default=null): ?string {
    $v = getenv($name);
    if ($v !== false && $v !== '') return $v;
    if (defined($name)) {
        $c = constant($name);
        if (is_bool($c)) return $c ? '1' : '0';
        if ($c !== null && $c !== '') return (string)$c;
    }
    return $default;
}

function mot_sms_enabled(): bool {
    return in_array(strtolower((string)mot_sms_cfg('SMSBROADCAST_ENABLED','0')), ['1','true','yes','on'], true);
}

function mot_sms_normalise_au_mobile(string $mobile): string {
    $n = preg_replace('/\D+/', '', $mobile);
    if (str_starts_with($n, '61') && strlen($n) === 11) return $n;
    if (str_starts_with($n, '04') && strlen($n) === 10) return '61'.substr($n,1);
    if (str_starts_with($n, '4') && strlen($n) === 9) return '61'.$n;
    return $n;
}

function mot_sms_ref(string $prefix='mot'): string {
    // SMS Broadcast ref max 20 chars.
    return substr($prefix.'-'.date('ymdHis').'-'.bin2hex(random_bytes(2)), 0, 20);
}

function mot_sms_broadcast_send(string $to, string $message, ?string $ref=null): array {
    $username = mot_sms_cfg('SMSBROADCAST_USERNAME');
    $password = mot_sms_cfg('SMSBROADCAST_PASSWORD');
    $from     = mot_sms_cfg('SMSBROADCAST_FROM','');
    $enabled  = mot_sms_enabled();

    $to = mot_sms_normalise_au_mobile($to);
    $message = trim($message);
    $ref = $ref ?: mot_sms_ref('mot');

    $base = [
        'ok' => false,
        'enabled' => $enabled,
        'to' => $to,
        'ref' => $ref,
        'smsref' => null,
        'status' => null,
        'response' => null,
        'error' => null,
    ];

    if (!$enabled) {
        $base['error'] = 'SMSBROADCAST_ENABLED is off.';
        return $base;
    }
    if (!$username || !$password) {
        $base['error'] = 'SMS Broadcast username/password are not configured.';
        return $base;
    }
    if ($to === '' || strlen($to) < 10) {
        $base['error'] = 'Destination mobile number is invalid.';
        return $base;
    }
    if ($message === '') {
        $base['error'] = 'SMS message is empty.';
        return $base;
    }
    if (!function_exists('curl_init')) {
        $base['error'] = 'PHP cURL is not available.';
        return $base;
    }

    $payload = [
        'username' => $username,
        'password' => $password,
        'to'       => $to,
        'from'     => $from,
        'message'  => $message,
        'ref'      => $ref,
        'maxsplit' => 5,
    ];

    $ch = curl_init('https://www.smsbroadcast.com.au/api-adv.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $base['response'] = $response === false ? null : trim((string)$response);

    if ($response === false) {
        $base['error'] = 'cURL error: '.$curlError;
        return $base;
    }
    if ($httpCode >= 400) {
        $base['error'] = 'SMS gateway HTTP '.$httpCode;
        return $base;
    }

    // Typical response line: OK:<mobile>:<reference>
    $line = trim(strtok((string)$response, "\r\n"));
    $parts = explode(':', $line, 3);
    $type = strtoupper($parts[0] ?? '');

    if ($type === 'OK') {
        $base['ok'] = true;
        $base['status'] = 'accepted';
        // Documentation calls the third field a reference; retain it as provider ref/smsref.
        $base['smsref'] = $parts[2] ?? null;
        return $base;
    }
    if ($type === 'BAD') {
        $base['status'] = 'rejected';
        $base['error'] = $parts[2] ?? 'SMS Broadcast rejected the message.';
        return $base;
    }
    if ($type === 'ERROR') {
        $base['status'] = 'error';
        $base['error'] = $parts[1] ?? 'SMS Broadcast returned an error.';
        return $base;
    }

    $base['status'] = 'unknown_response';
    $base['error'] = 'Unexpected SMS Broadcast response.';
    return $base;
}
