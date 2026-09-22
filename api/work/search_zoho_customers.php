<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/zoho_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function zohoSearchFail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message
    ]);
    exit;
}

function zohoSearchOriginalEmail(array $contact): string
{
    if (!empty($contact['email'])) {
        return trim((string)$contact['email']);
    }

    foreach (($contact['contact_persons'] ?? []) as $person) {
        if (!empty($person['email'])) {
            return trim((string)$person['email']);
        }
    }

    return '';
}

function zohoSearchOriginalPhone(array $contact): string
{
    foreach (['mobile', 'phone'] as $field) {
        if (!empty($contact[$field])) {
            return trim((string)$contact[$field]);
        }
    }

    foreach (($contact['contact_persons'] ?? []) as $person) {
        foreach (['mobile', 'phone'] as $field) {
            if (!empty($person[$field])) {
                return trim((string)$person[$field]);
            }
        }
    }

    return '';
}

function zohoSearchAddress(array $contact): string
{
    $billing = is_array($contact['billing_address'] ?? null)
        ? $contact['billing_address']
        : [];

    $parts = [];

    foreach (['address', 'street2', 'city', 'state', 'zip'] as $field) {
        $value = trim((string)($billing[$field] ?? ''));

        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    return implode(', ', $parts);
}

$query = trim((string)($_GET['q'] ?? ''));

if (mb_strlen($query) < 2) {
    echo json_encode([
        'ok' => true,
        'customers' => []
    ]);
    exit;
}

try {
    if (filter_var($query, FILTER_VALIDATE_EMAIL)) {
        $contacts = zohoListContacts(['email' => strtolower($query)]);
    } elseif (preg_match('/^[\d\s()+-]+$/', $query)) {
        $digits = preg_replace('/\D+/', '', $query) ?? '';

        if (strlen($digits) < 4) {
            $contacts = [];
        } else {
            $contacts = zohoListContacts([
                'phone_contains' => substr($digits, -8)
            ]);
        }
    } else {
        $contacts = zohoListContacts([
            'search_text' => $query
        ]);
    }

    $customers = [];
    $seen = [];

    foreach ($contacts as $contact) {
        $contactId = trim((string)($contact['contact_id'] ?? ''));

        if ($contactId === '' || isset($seen[$contactId])) {
            continue;
        }

        $seen[$contactId] = true;

        $customers[] = [
            'contact_id' => $contactId,
            'name' => trim((string)(
                $contact['contact_name']
                ?? $contact['company_name']
                ?? ''
            )),
            'email' => zohoSearchOriginalEmail($contact),
            'phone' => zohoSearchOriginalPhone($contact),
            'address' => zohoSearchAddress($contact)
        ];

        if (count($customers) >= 10) {
            break;
        }
    }

    echo json_encode([
        'ok' => true,
        'customers' => $customers
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Zoho customer search error: ' . $error->getMessage());

    zohoSearchFail(
        'Zoho customer search is temporarily unavailable. '
        . 'No customer information was changed.',
        502
    );
}