<?php
require_once __DIR__ . '/../includes/work_tracker.php';

date_default_timezone_set('Australia/Melbourne');

$tomorrow = date('Y-m-d', strtotime('+1 day'));

$onlyJobId = null;

if (PHP_SAPI === 'cli' && !empty($argv)) {
    foreach ($argv as $arg) {
        if (preg_match('/^--job=(\\d+)$/', $arg, $m)) {
            $onlyJobId = (int)$m[1];
        }
    }
}

$sql = "
    SELECT *
    FROM work_jobs
    WHERE planned_start_at IS NOT NULL
      AND DATE(planned_start_at)=?
      AND agreement_signed_at IS NOT NULL
      AND confirmation_requested_at IS NULL
      AND customer_confirmation_status='not_requested'
      AND customer_phone IS NOT NULL
      AND customer_phone <> ''
      AND status NOT IN ('completed','cancelled')
";

$params = [$tomorrow];

if ($onlyJobId !== null) {
    $sql .= " AND id=? ";
    $params[] = $onlyJobId;
}

$sql .= " ORDER BY planned_start_at ASC ";

$q = $pdo->prepare($sql);
$q->execute($params);

$jobs = $q->fetchAll(PDO::FETCH_ASSOC);

if (!$jobs) {
    echo "No day-before confirmations due for {$tomorrow}.\n";
    exit(0);
}

foreach ($jobs as $job) {
    $jobId = (int)$job['id'];

    try {
        $name = trim((string)($job['customer_name'] ?? ''));
        $firstName = $name !== ''
            ? preg_split('/\s+/', $name)[0]
            : '';

        $startTs = strtotime($job['planned_start_at']);
        $time = date('g:i a', $startTs);

        $greeting = $firstName !== ''
            ? "Hi {$firstName}, "
            : "Hi, ";

        $message =
            $greeting .
            "just confirming I'm scheduled for tomorrow at {$time}. " .
            "Please reply YES to confirm. If you need to change anything, reply with the details. " .
            "View your schedule, parking/access details here: " .
            wt_public_url($job) .
            " - Mike of All Trades";

        $result = wt_send_sms(
            $pdo,
            $jobId,
            (string)$job['customer_phone'],
            $message,
            'day_before_confirmation'
        );

        if (!empty($result['ok'])) {
            $u = $pdo->prepare("
                UPDATE work_jobs
                SET customer_confirmation_status='awaiting',
                    confirmation_requested_at=NOW()
                WHERE id=?
                  AND confirmation_requested_at IS NULL
            ");
            $u->execute([$jobId]);

            echo "Sent confirmation request for Job #{$jobId}.\n";
        } else {
            echo "FAILED Job #{$jobId}: " .
                ($result['message'] ?? 'SMS send failed') .
                "\n";
        }

    } catch (Throwable $e) {
        echo "ERROR Job #{$jobId}: {$e->getMessage()}\n";
        error_log(
            'Day-before confirmation failed for Job #' .
            $jobId .
            ': ' .
            $e->getMessage()
        );
    }
}
