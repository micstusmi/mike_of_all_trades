<?php
declare(strict_types=1);

/*
 * Mike of All Trades - Work Tracker MVP
 * Expects your existing /includes/db.php to provide a PDO connection in $pdo or $db.
 */
require_once __DIR__ . '/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($db) && $db instanceof PDO) {
        $pdo = $db;
    } else {
        throw new RuntimeException('Work Tracker could not find a PDO connection. Edit includes/work_tracker.php to match includes/db.php.');
    }
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

date_default_timezone_set('Australia/Melbourne');

function wt_env(string $name, ?string $default = null): ?string {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
}

function wt_base_url(): string {
    $configured = wt_env('WORKTRACKER_BASE_URL');
    if ($configured) return rtrim($configured, '/');

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Local XAMPP installation
    if ($host === 'localhost' || str_starts_with($host, 'localhost:')) {
        return $scheme . '://' . $host . '/mike_of_all_trades';
    }

    // Live website
    return $scheme . '://' . $host;
}

function wt_money(float $amount): string {
    return '$' . number_format($amount, 2);
}

function wt_normalise_phone(string $phone): string {
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($phone, '04')) return '61' . substr($phone, 1);
    if (str_starts_with($phone, '614')) return $phone;
    return $phone;
}

function wt_job(PDO $pdo, int $jobId): array {
    $s = $pdo->prepare("SELECT * FROM work_jobs WHERE id=?");
    $s->execute([$jobId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Job not found.');
    return $row;
}

function wt_job_by_token(PDO $pdo, string $token): array {
    $s = $pdo->prepare("SELECT * FROM work_jobs WHERE public_token=?");
    $s->execute([$token]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Job not found.');
    return $row;
}

function wt_totals(PDO $pdo, int $jobId): array {
    $job = wt_job($pdo, $jobId);

    $sql = "SELECT COALESCE(SUM(
              CASE WHEN s.billable=1 AND s.ended_at IS NOT NULL
              THEN TIMESTAMPDIFF(SECOND,s.started_at,s.ended_at)/3600 * COALESCE(w.hourly_rate, ?)
              ELSE 0 END),0)
            FROM work_sessions s
            LEFT JOIN work_workers w ON w.id=s.worker_id
            WHERE s.job_id=?";
    $q = $pdo->prepare($sql);
    $q->execute([(float)($job['agreed_hourly_rate'] ?? 0), $jobId]);
    $sessionLabour = (float)$q->fetchColumn();

    $q = $pdo->prepare("SELECT COALESCE(SUM(cost),0) FROM work_materials WHERE job_id=? AND paid_by='mike'");
    $q->execute([$jobId]);
    $materials = (float)$q->fetchColumn();

    $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM work_payments WHERE job_id=?");
    $q->execute([$jobId]);
    $paymentsTable = (float)$q->fetchColumn();

    $labour = (float)$job['work_already_value'] + $sessionLabour;
    $materialsTotal = (float)$job['materials_already_value'] + $materials;
    $payments = (float)$job['payments_received'] + $paymentsTable;
    $total = $labour + $materialsTotal;

    return [
        'labour' => $labour,
        'materials' => $materialsTotal,
        'total' => $total,
        'payments' => $payments,
        'outstanding' => max(0, $total - $payments),
    ];
}

function wt_public_url(array $job): string {
    return wt_base_url() . '/work/job.php?t=' . urlencode($job['public_token']);
}

function wt_send_sms(PDO $pdo, ?int $jobId, string $phone, string $message, string $purpose='general'): array {
    $username = wt_env('SMSBROADCAST_USERNAME');
    $password = wt_env('SMSBROADCAST_PASSWORD');
    $from = wt_env('SMSBROADCAST_FROM');

    $localRef = 'WT' . ($jobId ?? 0) . '-' . bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT INTO work_sms_messages(job_id,direction,phone,message,purpose,local_ref) VALUES(?,?,?,?,?,?)");
    $stmt->execute([$jobId, 'outbound', $phone, $message, $purpose, $localRef]);
    $rowId = (int)$pdo->lastInsertId();

    if (!$username || !$password || !$from) {
        return ['ok'=>false, 'message'=>'SMS credentials not configured; message saved but not sent.', 'id'=>$rowId];
    }

    $payload = http_build_query([
        'username'=>$username,
        'password'=>$password,
        'to'=>wt_normalise_phone($phone),
        'from'=>$from,
        'message'=>$message,
        'ref'=>$localRef
    ]);

    $ch = curl_init('https://www.smsbroadcast.com.au/api-adv.php');
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_CONNECTTIMEOUT=>10,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) return ['ok'=>false,'message'=>$error ?: 'SMS request failed','id'=>$rowId];

    $line = trim(explode("\n", trim($response))[0] ?? '');
    $parts = explode(':', $line, 3);
    $ok = (($parts[0] ?? '') === 'OK');
    $providerRef = $ok ? trim($parts[2] ?? '') : null;
    $status = $ok ? 'accepted' : 'failed';

    $u = $pdo->prepare("UPDATE work_sms_messages SET provider_ref=?, delivery_status=?, raw_payload=? WHERE id=?");
    $u->execute([$providerRef, $status, $response, $rowId]);

    return ['ok'=>$ok,'message'=>$line,'id'=>$rowId,'ref'=>$providerRef];
}

function wt_html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
