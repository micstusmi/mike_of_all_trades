<?php
// Shared helpers for SMS Broadcast / Sinch-style webhook payloads.

function mot_webhook_payload(): array {
    $raw = file_get_contents('php://input');
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    $data = [];
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } elseif (!empty($_POST)) {
            $data = $_POST;
        } else {
            parse_str($raw, $parsed);
            if (is_array($parsed)) $data = $parsed;
        }
    } elseif (!empty($_POST)) {
        $data = $_POST;
    } elseif (!empty($_GET)) {
        $data = $_GET;
    }

    return [
        'data' => $data,
        'raw' => $raw === false ? '' : $raw,
        'method' => strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
        'content_type' => $contentType,
    ];
}

function mot_webhook_flatten(array $data, string $prefix=''): array {
    $out = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? (string)$k : $prefix.'.'.$k;
        if (is_array($v)) {
            $out += mot_webhook_flatten($v, $key);
        } elseif (is_scalar($v) || $v === null) {
            $out[$key] = $v === null ? null : (string)$v;
        }
    }
    return $out;
}

function mot_webhook_first(array $flat, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        foreach ($flat as $k => $v) {
            $lk = strtolower($k);
            $lc = strtolower($candidate);
            if ($lk === $lc || str_ends_with($lk, '.'.$lc)) {
                $s = trim((string)$v);
                if ($s !== '') return $s;
            }
        }
    }
    return null;
}

function mot_webhook_event_name(array $flat): ?string {
    return mot_webhook_first($flat, [
        'event','event_type','type','status','delivery_status','message_status'
    ]);
}

function mot_webhook_authorised(): bool {
    if (!function_exists('wt_env')) return true;
    $expected = wt_env('SMSBROADCAST_WEBHOOK_TOKEN', null);
    if (!$expected) return true; // optional until configured

    $actual = $_SERVER['HTTP_X_MOT_WEBHOOK_TOKEN'] ?? '';
    if ($actual === '') {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $k=>$v) {
            if (strtolower((string)$k) === 'x-mot-webhook-token') {
                $actual = (string)$v;
                break;
            }
        }
    }
    return $actual !== '' && hash_equals((string)$expected, (string)$actual);
}

function mot_webhook_store_raw(array $env): string {
    $body = [
        'method' => $env['method'],
        'content_type' => $env['content_type'],
        'query' => $_GET,
        'form' => $_POST,
        'json_or_body' => $env['data'],
        'raw' => $env['raw'],
    ];
    return json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
