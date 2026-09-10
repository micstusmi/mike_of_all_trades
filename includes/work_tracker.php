<?php
declare(strict_types=1);

/*
 * Mike of All Trades - Work Tracker MVP
 * Expects your existing /includes/db.php to provide a PDO connection in $pdo or $db.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms_broadcast.php';

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

    if ($v !== false && $v !== '') {
        return $v;
    }

    if (defined($name)) {
        $constantValue = constant($name);

        if (is_string($constantValue) && $constantValue !== '') {
            return $constantValue;
        }
    }

    return $default;
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
              THEN (CASE WHEN s.session_source='retrospective' AND s.retrospective_hours IS NOT NULL THEN s.retrospective_hours ELSE TIMESTAMPDIFF(SECOND,s.started_at,s.ended_at)/3600 END) * COALESCE(w.hourly_rate, ?)
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

function wt_initialise_job_intake(PDO $pdo, int $jobId, string $requestText, bool $sendCustomerSms=true): array {
    $job = wt_job($pdo, $jobId);
    $requestText = trim($requestText);
    if ($requestText === '') $requestText = trim((string)($job['original_scope'] ?? ''));
    if ($requestText === '') throw new InvalidArgumentException('The requested-work list is empty.');

    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("UPDATE work_jobs SET customer_request_text=?,customer_request_updated_at=NOW(),ai_breakdown_status='pending',ai_breakdown_error=NULL WHERE id=?");
        $q->execute([$requestText,$jobId]);
        $q=$pdo->prepare("INSERT INTO work_job_request_revisions(job_id,source,previous_text,new_text,note,requires_review,reviewed_at) VALUES(?,'intake',NULL,?,'Initial job request logged',0,NOW())");
        $q->execute([$jobId,$requestText]);
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $job=wt_job($pdo,$jobId);
    $smsResult=null;
    if($sendCustomerSms && !empty($job['customer_phone'])) {
        $message = 'Mike of All Trades: Your job has been logged.';

        if (!empty($job['planned_start_at'])) {
            $message .= ' I have proposed ' .
                date('D j M', strtotime($job['planned_start_at'])) .
                ' at ' .
                date('g:i a', strtotime($job['planned_start_at'])) .
                ' for the booking.';
        }

        $message .= ' View your job, schedule and progress here: ' . wt_public_url($job);

        $smsResult = wt_send_sms(
            $pdo,
            $jobId,
            (string)$job['customer_phone'],
            $message,
            'job_logged_link'
        );
    }
    return ['job'=>$job,'sms'=>$smsResult];
}

function wt_send_sms(PDO $pdo, ?int $jobId, string $phone, string $message, string $purpose='general'): array {
    require_once __DIR__ . '/sms_broadcast.php';

    $phone = wt_normalise_phone($phone);
    $message = trim($message);
    $localRef = 'WT' . ($jobId ?? 0) . '-' . bin2hex(random_bytes(4));

    $stmt = $pdo->prepare("
        INSERT INTO work_sms_messages
        (job_id,direction,phone,message,purpose,local_ref)
        VALUES(?, 'outbound', ?, ?, ?, ?)
    ");
    $stmt->execute([$jobId, $phone, $message, $purpose, $localRef]);
    $rowId = (int)$pdo->lastInsertId();

    $gateway = mot_sms_broadcast_send($phone, $message, $localRef);

    $status = !empty($gateway['ok']) ? 'accepted' : 'failed';
    $providerRef = $gateway['smsref'] ?? null;
    $raw = $gateway['response'] ?? $gateway['error'] ?? null;

    $u = $pdo->prepare("
        UPDATE work_sms_messages
        SET provider_ref=?, delivery_status=?, raw_payload=?
        WHERE id=?
    ");
    $u->execute([$providerRef, $status, $raw, $rowId]);

    try {
        $g = $pdo->prepare("
            INSERT INTO work_sms_gateway_events
            (job_id,direction,event_kind,mobile_to,message,our_ref,smsref,provider_status,provider_response)
            VALUES(?, 'outbound', ?, ?, ?, ?, ?, ?, ?)
        ");
        $g->execute([
            $jobId,
            $purpose,
            $gateway['to'] ?? $phone,
            $message,
            $localRef,
            $providerRef,
            $gateway['status'] ?? $status,
            $raw
        ]);
    } catch (Throwable $e) {
        error_log('SMS gateway audit log failed: '.$e->getMessage());
    }

    $line = !empty($gateway['ok'])
        ? ('OK:' . ($gateway['to'] ?? $phone) . ':' . ($providerRef ?? ''))
        : (string)($gateway['error'] ?? 'SMS request failed');

    return [
        'ok' => (bool)($gateway['ok'] ?? false),
        'message' => $line,
        'id' => $rowId,
        'ref' => $providerRef,
        'local_ref' => $localRef,
        'gateway_status' => $gateway['status'] ?? null,
    ];
}

function wt_task_tracked_hours(PDO $pdo, int $taskId): float {
    $q = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN ended_at IS NOT NULL THEN CASE WHEN session_source='retrospective' AND retrospective_hours IS NOT NULL THEN retrospective_hours ELSE TIMESTAMPDIFF(SECOND,started_at,ended_at)/3600 END ELSE 0 END),0) FROM work_sessions WHERE task_id=?");
    $q->execute([$taskId]);
    return round((float)$q->fetchColumn(), 2);
}

function wt_job_tasks(PDO $pdo, int $jobId, bool $customerVisibleOnly=false): array {
    $sql = "SELECT t.*, (SELECT COALESCE(SUM(CASE WHEN s.ended_at IS NOT NULL THEN CASE WHEN s.session_source='retrospective' AND s.retrospective_hours IS NOT NULL THEN s.retrospective_hours ELSE TIMESTAMPDIFF(SECOND,s.started_at,s.ended_at)/3600 END ELSE 0 END),0) FROM work_sessions s WHERE s.task_id=t.id) AS tracked_hours FROM work_tasks t WHERE t.job_id=?";
    if ($customerVisibleOnly) $sql .= " AND t.customer_visible=1";
    $sql .= " ORDER BY t.task_order,t.id";
    $q=$pdo->prepare($sql); $q->execute([$jobId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function wt_task_progress(array $tasks): array {
    $total=0.0; $done=0.0;
    foreach($tasks as $t){
        if (($t['status']??'')==='cancelled') continue;
        $low=(float)($t['mike_estimate_low'] ?? 0); $high=(float)($t['mike_estimate_high'] ?? 0);
        if($low<=0 && $high<=0){$low=(float)($t['ai_estimate_low'] ?? 0); $high=(float)($t['ai_estimate_high'] ?? 0);}
        $weight=($low>0||$high>0) ? (($low+$high)/2) : 1.0;
        $total += $weight;
        if (($t['status']??'')==='completed') $done += $weight;
        elseif (($t['status']??'')==='in_progress') {
            $tracked=(float)($t['tracked_hours']??0);
            if($weight>0) $done += min($weight,$tracked);
        }
    }
    $pct=$total>0 ? (int)round(max(0,min(100,100*$done/$total))) : 0;
    return ['percent'=>$pct,'weight_total'=>$total,'weight_done'=>$done];
}

function wt_html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
