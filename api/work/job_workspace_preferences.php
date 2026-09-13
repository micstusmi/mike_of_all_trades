<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

header('Content-Type: application/json; charset=utf-8');

function pref_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ]);
    exit;
}

$jobId = (int)($_REQUEST['job_id'] ?? 0);

if ($jobId <= 0) {
    pref_fail('Invalid job.');
}

wt_job($pdo, $jobId);

$adminUserId = (int)($_SESSION['user_id'] ?? 0);

if ($adminUserId <= 0) {
    pref_fail('Admin session unavailable.', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $q = $pdo->prepare("
        SELECT
            section_order_json,
            open_sections_json,
            task_filter
        FROM work_admin_job_preferences
        WHERE job_id=? AND admin_user_id=?
        LIMIT 1
    ");

    $q->execute([
        $jobId,
        $adminUserId,
    ]);

    $row = $q->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'preferences' => [
            'sectionOrder' =>
                $row && $row['section_order_json']
                    ? json_decode($row['section_order_json'], true)
                    : [],
            'openSections' =>
                $row && $row['open_sections_json']
                    ? json_decode($row['open_sections_json'], true)
                    : [],
            'taskFilter' =>
                $row['task_filter'] ?? 'all',
        ],
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pref_fail('GET or POST required.', 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);

if (!is_array($data)) {
    pref_fail('Invalid JSON.');
}

$order = $data['sectionOrder'] ?? [];
$open = $data['openSections'] ?? [];
$filter = (string)($data['taskFilter'] ?? 'all');

if (!is_array($order)) {
    $order = [];
}

if (!is_array($open)) {
    $open = [];
}

$order = array_values(array_filter(
    array_map(
        static fn($v) => substr(
            preg_replace('/[^a-z0-9-]/', '', strtolower((string)$v)),
            0,
            100
        ),
        $order
    ),
    static fn($v) => $v !== ''
));

$cleanOpen = [];

foreach ($open as $key => $value) {

    $key = substr(
        preg_replace(
            '/[^a-z0-9-]/',
            '',
            strtolower((string)$key)
        ),
        0,
        100
    );

    if ($key !== '') {
        $cleanOpen[$key] = (bool)$value;
    }
}

$allowedFilters = [
    'focus',
    'active',
    'all',
    'blocked',
    'completed',
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'focus';
}

$q = $pdo->prepare("
    INSERT INTO work_admin_job_preferences
    (
        job_id,
        admin_user_id,
        section_order_json,
        open_sections_json,
        task_filter
    )
    VALUES
    (
        ?,?,?,?,?
    )
    ON DUPLICATE KEY UPDATE
        section_order_json=VALUES(section_order_json),
        open_sections_json=VALUES(open_sections_json),
        task_filter=VALUES(task_filter),
        updated_at=NOW()
");

$q->execute([
    $jobId,
    $adminUserId,
    json_encode($order),
    json_encode($cleanOpen),
    $filter,
]);

echo json_encode([
    'ok' => true,
]);
