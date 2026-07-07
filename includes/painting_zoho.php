<?php
// Load the private site config early so ZOHO_LIVE_PAINTING_QUOTES is available.
// Your existing zoho_functions.php also loads config.php, but the live-mode
// check happens before zoho_functions.php is required, so config.php must be
// loaded here as well.
$paintingPrivateConfig = __DIR__ . '/config.php';
if (file_exists($paintingPrivateConfig)) {
    require_once $paintingPrivateConfig;
}

/**
 * Painting Zoho bridge.
 *
 * This connects the painting quote builder to your existing Zoho helper functions:
 * - getOrCreateZohoCustomer($name, $email, $phone, $address)
 * - createZohoEstimate($customer_id, $name, $service_description, $total)
 * - sendZohoEstimate($estimate_id, $email)
 *
 * Keep ZOHO_LIVE_PAINTING_QUOTES false until you are ready to create real Zoho estimates.
 */

function painting_zoho_live_enabled(): bool {
    // Safety switch. Change to true after local testing.
    if (defined('ZOHO_LIVE_PAINTING_QUOTES')) {
        return (bool) ZOHO_LIVE_PAINTING_QUOTES;
    }

    return false;
}

function painting_save_quote_request(array $quotePackage): string {
    $dir = dirname(__DIR__) . '/painting_quote_requests';

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $file = $dir . '/' . $quotePackage['reference'] . '.json';

    file_put_contents(
        $file,
        json_encode($quotePackage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    return $file;
}

function painting_build_zoho_description(array $quotePackage): string {
    $lines = [];

    $lines[] = 'PAINTING QUOTE REQUEST';
    $lines[] = 'Reference: ' . ($quotePackage['reference'] ?? '');
    $lines[] = '';
    $lines[] = 'SCOPE OF WORKS';
    $lines[] = $quotePackage['scope_of_works'] ?? '';
    $lines[] = '';
    $lines[] = 'ITEMISED ESTIMATE BREAKDOWN';

    foreach (($quotePackage['line_items'] ?? []) as $item) {
        $name = $item['name'] ?? 'Painting item';
        $qty = $item['quantity'] ?? '';
        $rate = $item['rate'] ?? '';
        $total = isset($item['total']) ? '$' . number_format((float)$item['total'], 0) : '$0';
        $description = trim((string)($item['description'] ?? ''));

        $line = '- ' . $name;

        if ($qty !== '') {
            $line .= ' | Qty: ' . $qty;
        }

        if ($rate !== '') {
            $line .= ' | Rate: ' . $rate;
        }

        $line .= ' | Total: ' . $total;
        $lines[] = $line;

        if ($description !== '') {
            $lines[] = '  ' . $description;
        }
    }

    $estimate = $quotePackage['estimate'] ?? [];

    $lines[] = '';
    $lines[] = 'ESTIMATE SUMMARY';
    $lines[] = 'Estimated labour range: $' . number_format((float)($estimate['labour_low'] ?? 0), 0)
        . ' - $' . number_format((float)($estimate['labour_high'] ?? 0), 0);
    $lines[] = 'Estimated labour midpoint: $' . number_format((float)($estimate['labour_midpoint'] ?? 0), 0);
    $lines[] = 'Estimated paint/materials: $' . number_format((float)($estimate['materials'] ?? 0), 0);
    $lines[] = 'Estimated total midpoint: $' . number_format((float)($estimate['total_midpoint'] ?? 0), 0);
    $lines[] = 'Estimated quote accuracy: ' . number_format((float)($estimate['accuracy'] ?? 0), 0) . '%';
    $lines[] = '';
    $lines[] = 'IMPORTANT NOTES';
    $lines[] = 'This quotation is based on customer-supplied information from the online painting quote builder.';
    $lines[] = 'Final price may change after inspection, final measurements, access, substrate condition, colour changes and final scope confirmation.';

    return implode("\n", $lines);
}

function painting_create_zoho_estimate_live(array $quotePackage): ?array {
    if (!painting_zoho_live_enabled()) {
        return null;
    }

    $zohoFunctions = __DIR__ . '/zoho_functions.php';

    if (!file_exists($zohoFunctions)) {
        return [
            'mode' => 'zoho_error',
            'message' => 'Zoho live mode is enabled, but includes/zoho_functions.php was not found.'
        ];
    }

    require_once $zohoFunctions;

    $customer = $quotePackage['customer'] ?? [];
    $estimate = $quotePackage['estimate'] ?? [];

    $name = trim((string)($customer['name'] ?? ''));
    $email = trim((string)($customer['email'] ?? ''));
    $phone = trim((string)($customer['phone'] ?? ''));
    $address = trim((string)($customer['address'] ?? ''));

    if ($name === '' || $email === '' || $phone === '') {
        return [
            'mode' => 'zoho_error',
            'message' => 'Missing customer name, phone or email.'
        ];
    }

    if (!function_exists('getOrCreateZohoCustomer') || !function_exists('createZohoEstimate')) {
        return [
            'mode' => 'zoho_error',
            'message' => 'Required Zoho helper functions were not found in zoho_functions.php.'
        ];
    }

    $customerId = getOrCreateZohoCustomer($name, $email, $phone, $address);

    if (!$customerId) {
        return [
            'mode' => 'zoho_error',
            'message' => 'Could not create or find Zoho customer.'
        ];
    }

    $description = painting_build_zoho_description($quotePackage);
    $total = (float)($estimate['total_midpoint'] ?? $estimate['labour_midpoint'] ?? 0);

    $estimateResponse = createZohoEstimate($customerId, $name, $description, $total);

    $estimateId = $estimateResponse['json']['estimate']['estimate_id']
        ?? $estimateResponse['json']['estimate_id']
        ?? null;

    if (!$estimateId) {
        return [
            'mode' => 'zoho_error',
            'message' => 'Zoho customer was created/found, but estimate creation failed.',
            'zoho_response' => $estimateResponse
        ];
    }

    $emailResponse = null;

    if (function_exists('sendZohoEstimate')) {
        $emailResponse = sendZohoEstimate($estimateId, $email);
    }

    return [
        'mode' => 'live_zoho',
        'message' => 'Zoho estimate created successfully.',
        'customer_id' => $customerId,
        'estimate_id' => $estimateId,
        'estimate_response' => $estimateResponse,
        'email_response' => $emailResponse
    ];
}

function painting_create_zoho_estimate(array $quotePackage): array {
    $savedFile = painting_save_quote_request($quotePackage);

    $live = painting_create_zoho_estimate_live($quotePackage);

    if (is_array($live)) {
        $live['saved_file'] = $savedFile;
        return $live;
    }

    return [
        'mode' => 'pending_zoho_setup',
        'message' => 'Quote request saved locally. Live Zoho estimate creation is switched off.',
        'saved_file' => $savedFile
    ];
}
