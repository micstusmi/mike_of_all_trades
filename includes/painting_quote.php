<?php
/**
 * Painting quote packaging helpers.
 * This file converts the browser estimate payload into a professional quote package
 * that can be saved, emailed, or sent to Zoho.
 */

function painting_money($amount): string {
    return '$' . number_format((float)$amount, 0);
}

function painting_label(string $value): string {
    $labels = [
        'quick' => 'Quick Estimate',
        'detailed' => 'Detailed Estimate',
        'precise' => 'Precise Quote',
        'small' => 'Small job / touch-up',
        'one_room' => 'One room',
        'multi_room' => 'Multiple rooms',
        'whole_interior' => 'Whole interior',
        'exterior' => 'Exterior painting',
        'tiny' => 'Tiny',
        'medium' => 'Medium',
        'large' => 'Large',
        'huge' => 'Huge',
        'budget' => 'Budget refresh',
        'standard' => 'Standard repaint',
        'premium' => 'Premium finish',
        'customer' => 'Customer supplied',
        'mike' => 'Mike to supply',
        'good' => 'Good',
        'average' => 'Average',
        'rough' => 'Rough',
        'easy' => 'Easy access',
        'normal' => 'Normal access',
        'hard' => 'Difficult access',
        'scaffold' => 'May need scaffold/platform',
        'exterior_walls' => 'Exterior walls/siding/render',
        'walls' => 'Walls',
        'ceilings' => 'Ceilings',
        'skirting' => 'Skirting boards',
        'architraves' => 'Architraves',
        'doors' => 'Doors',
        'window_frames' => 'Window frames',
        'eaves' => 'Eaves',
        'fascia' => 'Fascia/barge boards',
        'gutters' => 'Gutters/downpipes',
        'pergola_deck' => 'Pergola/deck/verandah',
        'fence' => 'Fence',
        'weatherboard' => 'Weatherboard/timber',
        'render' => 'Render',
        'brick' => 'Painted brick',
        'cladding' => 'Cladding',
        'mixed' => 'Mixed / unsure',
        'some' => 'Some',
        'lots' => 'Lots',
        'none' => 'None / minor only'
    ];

    return $labels[$value] ?? ucwords(str_replace('_', ' ', $value));
}

function painting_array_labels($values): string {
    if (!is_array($values)) {
        return painting_label((string)$values);
    }
    return implode(', ', array_map(fn($v) => painting_label((string)$v), $values));
}

function painting_build_scope_of_works(array $payload): string {
    $answers = $payload['answers'] ?? [];
    $estimate = $payload['estimate'] ?? [];
    $customer = $payload['customer'] ?? [];

    $jobType = painting_label((string)($answers['job_type'] ?? 'painting'));
    $finish = painting_label((string)($answers['finish_level'] ?? 'standard'));
    $surfaces = painting_array_labels($answers['surfaces'] ?? []);
    $condition = painting_label((string)($answers['condition'] ?? 'not specified'));
    $access = painting_label((string)($answers['access'] ?? 'not specified'));
    $paintSupply = ($answers['paint_supply'] ?? 'customer') === 'mike'
        ? 'Paint and materials to be estimated/supplied by Mike where agreed.'
        : 'Customer to supply all paint and materials unless otherwise agreed in writing.';

    $parts = [];
    $parts[] = "Prepare and paint the selected {$jobType} areas.";
    if ($surfaces !== '') {
        $parts[] = "Included surfaces: {$surfaces}.";
    }
    $parts[] = "Finish level: {$finish}.";
    $parts[] = "Surface condition noted as {$condition}; access noted as {$access}.";

    if (!empty($answers['exterior_wall_m2'])) {
        $parts[] = "Approximate exterior surface area nominated by customer: {$answers['exterior_wall_m2']} m².";
    }
    if (!empty($answers['wall_area_m2'])) {
        $parts[] = "Approximate interior wall area nominated by customer: {$answers['wall_area_m2']} m².";
    }
    if (!empty($answers['floor_area_m2'])) {
        $parts[] = "Approximate internal floor area nominated by customer: {$answers['floor_area_m2']} m².";
    }
    if (!empty($answers['window_frames_count'])) {
        $parts[] = "Window frames included: approximately {$answers['window_frames_count']}.";
    }
    if (!empty($answers['doors_count'])) {
        $parts[] = "Doors included: approximately {$answers['doors_count']}.";
    }
    if (!empty($answers['linear_trim_m'])) {
        $parts[] = "Approximate exterior trim/eaves/fascia length nominated by customer: {$answers['linear_trim_m']} lineal metres.";
    }

    if (!empty($answers['repairs']) && is_array($answers['repairs']) && !in_array('none', $answers['repairs'], true)) {
        $parts[] = 'Repair/problem areas noted: ' . painting_array_labels($answers['repairs']) . '.';
    }

    $parts[] = $paintSupply;
    $parts[] = 'Estimate is subject to inspection, final measurements, access, product selection, substrate condition, colour changes and final scope confirmation.';

    if (!empty($customer['notes'])) {
        $parts[] = 'Customer notes: ' . trim((string)$customer['notes']);
    }

    return implode(' ', $parts);
}

function painting_build_line_items(array $payload): array {
    $estimate = $payload['estimate'] ?? [];
    $answers = $payload['answers'] ?? [];
    $items = [];

    foreach (($estimate['items'] ?? []) as $item) {
        $items[] = [
            'name' => (string)($item['item'] ?? 'Painting labour'),
            'description' => (string)($item['explanation'] ?? ''),
            'quantity' => (string)($item['qty'] ?? '1'),
            'rate' => (string)($item['rate'] ?? ''),
            'total' => (float)($item['total'] ?? 0)
        ];
    }

    if (($answers['paint_supply'] ?? 'customer') === 'mike' && !empty($estimate['materials'])) {
        $items[] = [
            'name' => 'Estimated paint and materials',
            'description' => 'Estimated allowance for paint and standard consumables. Final product selection and quantity to be confirmed before purchase.',
            'quantity' => 'allowance',
            'rate' => '',
            'total' => (float)$estimate['materials']
        ];
    }

    return $items;
}

function painting_build_quote_package(array $payload): array {
    $customer = $payload['customer'] ?? [];
    $estimate = $payload['estimate'] ?? [];
    $answers = $payload['answers'] ?? [];

    $reference = 'PAINT-' . date('Ymd-His') . '-' . random_int(100, 999);
    $scope = painting_build_scope_of_works($payload);
    $items = painting_build_line_items($payload);

    return [
        'reference' => $reference,
        'created_at' => date('c'),
        'customer' => [
            'name' => trim((string)($customer['name'] ?? '')),
            'phone' => trim((string)($customer['phone'] ?? '')),
            'email' => trim((string)($customer['email'] ?? '')),
            'address' => trim((string)($customer['address'] ?? '')),
            'notes' => trim((string)($customer['notes'] ?? '')),
        ],
        'quote_mode' => $payload['mode'] ?? ($answers['quote_mode'] ?? 'manual'),
        'answers' => $answers,
        'estimate' => [
            'labour_low' => (float)($estimate['low'] ?? 0),
            'labour_high' => (float)($estimate['high'] ?? 0),
            'labour_midpoint' => (float)($estimate['labour'] ?? 0),
            'materials' => (float)($estimate['materials'] ?? 0),
            'total_midpoint' => (float)($estimate['total'] ?? 0),
            'accuracy' => (float)($estimate['accuracy'] ?? 0),
        ],
        'scope_of_works' => $scope,
        'line_items' => $items,
        'zoho_subject' => 'Painting Estimate - ' . painting_label((string)($answers['job_type'] ?? 'Painting')),
        'zoho_notes' => $scope . "\n\nEstimated labour range: " . painting_money($estimate['low'] ?? 0) . ' - ' . painting_money($estimate['high'] ?? 0) . "\nEstimated quote accuracy: " . ($estimate['accuracy'] ?? 0) . "%"
    ];
}
