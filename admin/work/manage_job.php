<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_approvals.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Invalid job ID.');
}

try {
    $job = wt_job($pdo, $id);
} catch (Throwable $e) {
    http_response_code(404);
    die('Admin job not found.');
}

/*
 * Opening a job should affect recent-open ordering without pretending
 * that the actual job record was edited.
 *
 * Explicitly assigning updated_at to itself prevents an automatic
 * ON UPDATE timestamp from changing on databases configured that way.
 */
$touchJob = $pdo->prepare("
    UPDATE work_jobs
    SET
        last_opened_at = NOW(),
        updated_at = updated_at
    WHERE id = ?
");
$touchJob->execute([$id]);

$tot = wt_totals($pdo, $id);
$tasks = wt_job_tasks($pdo,$id);
$taskProgress = wt_task_progress($tasks);
$activeTasks = array_values(array_filter($tasks, fn($t)=>!in_array($t['status'],['completed','cancelled'],true)));
$taskById=[]; foreach($tasks as $t)$taskById[(int)$t['id']]=$t;

/*
 * One-time result from Save + SMS actions.
 */
$taskSmsFlash = $_SESSION['wt_sms_flash'] ?? null;
unset($_SESSION['wt_sms_flash']);


$approvalRequests = wt_job_approval_requests(
    $pdo,
    (int)$id
);

$approvalSummary = wt_job_approval_summary(
    $pdo,
    (int)$id
);

$approvalFlash =
    $_SESSION['wt_approval_flash']
    ?? null;

unset(
    $_SESSION['wt_approval_flash']
);

$changeStmt=$pdo->prepare("SELECT * FROM work_task_change_requests WHERE job_id=? ORDER BY created_at DESC,id DESC");
$changeStmt->execute([$id]);
$taskChangeRequests=$changeStmt->fetchAll(PDO::FETCH_ASSOC);
$changesByTask=[]; foreach($taskChangeRequests as $cr){$changesByTask[(int)$cr['task_id']][]=$cr;}
$pendingChangeCount=count(array_filter($taskChangeRequests,fn($cr)=>$cr['status']==='awaiting_review'));
$revStmt=$pdo->prepare("SELECT * FROM work_job_request_revisions WHERE job_id=? ORDER BY created_at DESC,id DESC LIMIT 20");$revStmt->execute([$id]);$requestRevisions=$revStmt->fetchAll(PDO::FETCH_ASSOC);$pendingRequestRevisions=array_values(array_filter($requestRevisions,fn($r)=>!empty($r['requires_review'])&&empty($r['reviewed_at'])));$customerRequest=(string)($job['customer_request_text']??$job['original_scope']??'');
$jobSourceLabels=['website'=>'Website','ai_website'=>'AI website quote','website_booking'=>'Website booking','phone'=>'Phone call','sms'=>'SMS','whatsapp'=>'WhatsApp','messenger'=>'Messenger','signal'=>'Signal','airtasker'=>'Airtasker','email'=>'Email','friend_family'=>'Friend / family','word_of_mouth'=>'Word of mouth / referral','repeat_customer'=>'Repeat customer','other'=>'Other'];

$workers = $pdo->prepare("SELECT * FROM work_workers WHERE job_id=? AND active=1 ORDER BY id");
$workers->execute([$id]);
$workers = $workers->fetchAll(PDO::FETCH_ASSOC);

$sessions = $pdo->prepare("
    SELECT s.*, w.worker_name, w.hourly_rate
    FROM work_sessions s
    LEFT JOIN work_workers w ON w.id=s.worker_id
    WHERE s.job_id=?
    ORDER BY s.started_at DESC
    LIMIT 40
");
$sessions->execute([$id]);
$sessions = $sessions->fetchAll(PDO::FETCH_ASSOC);

/*
 * V8.7: collect non-chargeable breaks belonging to each session.
 * An open break means the activity is currently paused.
 */
$breakStmt = $pdo->prepare("
    SELECT b.*
    FROM work_session_breaks b
    INNER JOIN work_sessions s ON s.id = b.session_id
    WHERE s.job_id = ?
    ORDER BY b.started_at
");
$breakStmt->execute([$id]);

$sessionBreakSeconds = [];
$activeBreaks = [];
$utcZone = new DateTimeZone('UTC');
$utcNow = new DateTimeImmutable('now', $utcZone);

foreach ($breakStmt->fetchAll(PDO::FETCH_ASSOC) as $breakRow) {
    $breakStart = (
        new DateTimeImmutable($breakRow['started_at'], $utcZone)
    )->getTimestamp();

    $breakEnd = !empty($breakRow['ended_at'])
        ? (
            new DateTimeImmutable($breakRow['ended_at'], $utcZone)
        )->getTimestamp()
        : $utcNow->getTimestamp();

    $breakSeconds = max(0, $breakEnd - $breakStart);
    $breakSessionId = (int)$breakRow['session_id'];

    $sessionBreakSeconds[$breakSessionId] =
        ($sessionBreakSeconds[$breakSessionId] ?? 0) + $breakSeconds;

    if (empty($breakRow['ended_at'])) {
        $activeBreaks[$breakSessionId] = $breakRow;
    }
}

foreach ($sessions as &$sessionRow) {
    $sessionId = (int)$sessionRow['id'];

    $sessionRow['break_seconds'] =
        $sessionBreakSeconds[$sessionId] ?? 0;

    $sessionRow['active_break'] =
        $activeBreaks[$sessionId] ?? null;
}
unset($sessionRow);

$runningSessions = array_values(array_filter($sessions, fn($s) => empty($s['ended_at'])));
$runningWorkerKeys = [];
foreach ($runningSessions as $rs) {
    $runningWorkerKeys[$rs['worker_id'] === null ? 'mike' : 'worker_'.$rs['worker_id']] = true;
}

$quickSession = $runningSessions[0] ?? null;
$quickActiveAction = 'idle';
if ($quickSession) {
    if (!empty($quickSession['active_break'])) {
        $quickActiveAction = (($quickSession['active_break']['reason'] ?? '') === 'meal')
            ? 'meal_break'
            : 'coffee_break';
    } elseif (($quickSession['travel_type'] ?? '') === 'to_customer') {
        $quickActiveAction = 'travel_site';
    } elseif (($quickSession['category'] ?? '') === 'procurement') {
        $quickActiveAction = 'supplier_out';
    } elseif (
        stripos((string)($quickSession['location_detail'] ?? ''), 'Returning from supplier') !== false
        || stripos((string)($quickSession['notes'] ?? ''), 'Returning from supplier') !== false
    ) {
        $quickActiveAction = 'supplier_return';
    } elseif (
        stripos((string)($quickSession['location_detail'] ?? ''), 'Leaving customer site') !== false
        || stripos((string)($quickSession['notes'] ?? ''), 'Leaving customer site') !== false
    ) {
        $quickActiveAction = 'leave_site';
    } elseif (($quickSession['category'] ?? '') === 'onsite') {
        $quickActiveAction = 'work';
    }
}

$quickActionFlash = $_SESSION['wt_quick_action_flash'] ?? null;
unset($_SESSION['wt_quick_action_flash']);

$locationLabels = [
    'onsite' => 'On site',
    'bunnings' => 'Bunnings',
    'supplier' => 'Another supplier / store',
    'travel_job' => 'Travelling for this job',
    'workshop_home' => 'Workshop / home preparation',
    'offsite_planning' => 'Off-site planning / admin for this job',
    'other' => 'Other',
];

$stopReasonLabels = [
    'lunch' => 'Lunch',
    'coffee_break' => 'Coffee / short break',
    'other_job_errand' => 'Errand for a different job',
    'other_customer_call' => 'Phone call / admin for another customer',
    'home_for_night' => 'Going home for the night',
    'personal_errand' => 'Personal errand',
    'customer_emergency' => 'Helping another customer with an emergency',
    'supplier_delay' => 'Supplier / material delay',
    'awaiting_customer' => 'Waiting for customer decision / access',
    'job_related_travel' => 'Changing location / travelling for this job',
    'completed_activity' => 'Current activity completed',
    'other' => 'Other',
];

$customerUpdateModeLabels = [
    'full_transparency' => 'Full transparency — SMS every Start / Stop',
    'important_only' => 'Important activity changes only',
    'daily_only' => 'Daily summaries only',
    'none' => 'No automatic SMS updates',
];


$sms = $pdo->prepare("SELECT * FROM work_sms_messages WHERE job_id=? ORDER BY id DESC LIMIT 30");
$sms->execute([$id]);
$smsMessages = $sms->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');

$planStmt = $pdo->prepare("SELECT * FROM work_daily_plans WHERE job_id=? AND plan_date=? LIMIT 1");
$planStmt->execute([$id, $today]);
$todayPlan = $planStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$freeStmt = $pdo->prepare("SELECT * FROM work_complimentary_items WHERE job_id=? ORDER BY created_at DESC, id DESC");
$freeStmt->execute([$id]);
$complimentaryItems = $freeStmt->fetchAll(PDO::FETCH_ASSOC);

$complimentaryTotal = 0.0;

$noChargeTotals = [
    'goodwill' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'total' => 0.0,
    ],
    'rectification' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'total' => 0.0,
    ],
    'other' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'total' => 0.0,
    ],
    'unclassified' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'total' => 0.0,
    ],
];

foreach ($complimentaryItems as $ci) {
    $reason = (string)($ci['no_charge_reason'] ?? 'unclassified');

    if (!isset($noChargeTotals[$reason])) {
        $reason = 'unclassified';
    }

    $hours = (float)($ci['labour_hours'] ?? 0);
    $labour = (float)($ci['labour_value'] ?? 0);
    $materialsValue = (float)($ci['material_value'] ?? 0);
    $legacyTotal = (float)($ci['estimated_value'] ?? 0);

    $allocated = $labour + $materialsValue;
    $unallocated = max(0, $legacyTotal - $allocated);

    $rowTotal = $allocated + $unallocated;

    $noChargeTotals[$reason]['hours'] += $hours;
    $noChargeTotals[$reason]['labour'] += $labour;
    $noChargeTotals[$reason]['materials'] += $materialsValue;
    $noChargeTotals[$reason]['unallocated'] += $unallocated;
    $noChargeTotals[$reason]['total'] += $rowTotal;

    $complimentaryTotal += $rowTotal;
}

$noChargeGrand = [
    'hours' => 0.0,
    'labour' => 0.0,
    'materials' => 0.0,
    'unallocated' => 0.0,
    'total' => 0.0,
];

foreach ($noChargeTotals as $bucket) {
    foreach ($noChargeGrand as $key => $unused) {
        $noChargeGrand[$key] += (float)$bucket[$key];
    }
}

$timeBreakdown = [
    'onsite' => 0,
    'supplier' => 0,
    'travel' => 0,
    'offsite' => 0,
    'other' => 0,
];

foreach ($sessions as $s) {
    if (empty($s['started_at'])) continue;
    $utc = new DateTimeZone('UTC');
    $startTs = (new DateTimeImmutable($s['started_at'], $utc))->getTimestamp();
    $endTs = !empty($s['ended_at'])
        ? (new DateTimeImmutable($s['ended_at'], $utc))->getTimestamp()
        : (new DateTimeImmutable('now', $utc))->getTimestamp();
    $secs = max(
        0,
        $endTs - $startTs - (int)($s['break_seconds'] ?? 0)
    );

    $loc = $s['start_location'] ?? '';
    $cat = $s['category'] ?? '';

    if ($loc === 'onsite') {
        $bucket = 'onsite';
    } elseif (in_array($loc, ['bunnings','supplier'], true)) {
        $bucket = 'supplier';
    } elseif ($loc === 'travel_job' || $cat === 'travel') {
        $bucket = 'travel';
    } elseif (in_array($loc, ['workshop_home','offsite_planning'], true)) {
        $bucket = 'offsite';
    } else {
        $bucket = 'other';
    }
    $timeBreakdown[$bucket] += $secs;
}

function wt_melbourne_time(?string $utc): string {
    if (!$utc) return '';

    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $dt = $dt->setTimezone(new DateTimeZone('Australia/Melbourne'));
        return $dt->format('D j M Y, g:i:s a');
    } catch (Throwable $e) {
        return $utc;
    }
}

function wt_duration_hm(int $seconds): string {
    $hours = intdiv($seconds, 3600);
    $mins = intdiv($seconds % 3600, 60);
    if ($hours > 0) return $hours.' hr '.str_pad((string)$mins, 2, '0', STR_PAD_LEFT).' min';
    if ($mins > 0) return $mins.' min';
    return '< 1 min';
}

$url = wt_public_url($job);

$pricingLabels = [
    'fixed_price' => 'Fixed-price quote',
    'estimate' => 'Estimate / approximate budget',
    'hourly' => 'Hourly rate',
    'no_price' => 'No specific price was agreed',
    'unspecified' => 'Not specified',
];

$variationLabels = [
    'fixed_amount' => 'Fixed additional amount',
    'hourly' => 'Hourly for varied work',
    'estimate' => 'Estimated range for varied work',
    'not_applicable' => 'Not applicable',
];

$pricingLabel = $pricingLabels[$job['original_pricing_type'] ?? 'unspecified'] ?? 'Not specified';
$variationRequired = (($job['original_pricing_type'] ?? '') === 'fixed_price' && !empty($job['variation_required']));
$variationLabel = $variationLabels[$job['variation_pricing_method'] ?? 'not_applicable'] ?? 'Not applicable';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage job — <?=wt_html($job['customer_name'])?></title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;margin:0;color:#17202a}
.wrap{max-width:980px;margin:auto;padding:15px}
.card{background:#fff;padding:18px;border-radius:14px;margin:12px 0;box-shadow:0 2px 10px #0001}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.metric{background:#f2f4f5;padding:12px;border-radius:10px}
.big{font-size:24px;font-weight:850}
.btn{display:inline-block;border:0;border-radius:10px;padding:13px 16px;font-weight:800;color:#fff;background:#17202a;text-decoration:none;margin:4px}
.start{background:#087f23}.stop{background:#b42318}.sms{background:#145ea8}
.warn{background:#fff3cd;border:1px solid #e5c35a}
.info{background:#eef6ff;border:1px solid #a7c8eb}
.variation{background:#fff4ef;border:2px solid #e58a60}
.row{display:flex;gap:8px;flex-wrap:wrap}
input,select,textarea{padding:10px;border:1px solid #ccd1d5;border-radius:8px;font:inherit}
textarea{width:100%;box-sizing:border-box}
.small{font-size:13px;color:#5b6570}
.tag{display:inline-block;padding:5px 9px;border-radius:999px;background:#17202a;color:#fff;font-size:12px;font-weight:800}
.status-good{color:#087f23;font-weight:800}.status-warn{color:#b42318;font-weight:800}
.running-card{background:#eaf8ee;border:2px solid #1f9d45}
.running-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.running-title{font-size:22px;font-weight:900;color:#087f23}
.timer{font-size:28px;font-weight:900;font-variant-numeric:tabular-nums}
.stop-panel{background:#fff7f5;border:1px solid #efb4aa;padding:12px;border-radius:10px;margin-top:12px}
.notice-good{background:#eaf8ee;border:1px solid #9dd8ad}
.notice-warn{background:#fff3cd;border:1px solid #e5c35a}
.field{display:flex;flex-direction:column;gap:5px;min-width:180px;flex:1}
.field label{font-size:12px;font-weight:800;color:#4b5560}
.wide{min-width:260px;flex:2}
.session-row{border-top:1px solid #eee;padding:12px 0}
.session-meta{font-size:14px;color:#4f5b66;line-height:1.5}
.source-badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:900;margin-left:6px}.source-live{background:#e7f5ec;color:#087f23}.source-retro{background:#fff3cd;color:#795d00}.travel-card{background:#eef6ff;border:2px solid #7fb2df}.retro-card{background:#fffaf0;border:2px solid #e1c66f}.task-card{border:1px solid #dce3e8}.task-completed{background:#effaf2}.task-blocked{background:#fff5e6}.task-cancelled{opacity:.65}.progressbar{height:16px;background:#e7ebee;border-radius:999px;overflow:hidden}.progressfill{height:100%;background:#087f23}.task-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.task-grid .field{min-width:0}@media(max-width:700px){.task-grid{grid-template-columns:1fr}}.checkline{display:flex;align-items:center;gap:8px}.checkline input{width:auto}.task-detail-box{background:#f7f9fa;border:1px solid #dfe5e9;border-radius:10px;padding:12px;margin:10px 0}.task-detail-box summary{cursor:pointer;font-weight:850}.task-detail-box textarea{min-height:95px}.task-materials{background:#f2f8ee;border-color:#cfe0c5}.task-change-admin{border:2px solid #9fc4e5;background:#f1f8fe;border-radius:11px;padding:12px;margin:11px 0}.task-change-admin.pending{border-color:#ddb74c;background:#fff9df}.task-change-admin .meta{font-size:12px;color:#64727d}.task-change-admin textarea{min-height:70px}.change-pill{display:inline-block;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:900;background:#e7edf2}.change-pill.awaiting_review{background:#fff0bf;color:#715600}.change-pill.accepted,.change-pill.amended{background:#dff3e5;color:#126d2d}.change-pill.declined{background:#f7dfdf;color:#8b2525}.change-pill.question_sent{background:#e3effa;color:#205d91}
.live-change-alert{position:sticky;top:8px;z-index:999;background:#fff3cd;border:3px solid #d39b00;border-radius:12px;padding:13px 15px;margin:10px 0;box-shadow:0 5px 18px #0002;display:none}.live-change-alert.show{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.live-change-alert strong{font-size:17px}.live-alert-actions{display:flex;gap:8px;flex-wrap:wrap}.notify-btn{background:#6b4f00}.pending-bell{display:inline-block;background:#b42318;color:#fff;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900}
.intake-card{background:#eef8ff;border:2px solid #8ebfe7}.ai-status{display:inline-block;padding:5px 9px;border-radius:999px;background:#e8edf1;font-size:12px;font-weight:900}.ai-status.complete{background:#dff3e5;color:#126d2d}.ai-status.failed{background:#f7dfdf;color:#8b2525}.ai-status.running,.ai-status.pending{background:#fff0bf;color:#715600}.request-revision{background:#fff9df;border:1px solid #dfbd58;border-radius:10px;padding:11px;margin-top:10px}
@media(max-width:700px){.grid{grid-template-columns:1fr}}

.plan-card{background:#eef6ff;border:2px solid #a7c8eb}
.plan-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.plan-stat{background:#fff;border:1px solid #d8e3eb;border-radius:10px;padding:11px}
.plan-stat b{display:block;font-size:12px;color:#596874;margin-bottom:3px}
.plan-big{font-size:18px;font-weight:900}
.timebreak-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
.timebreak-item{background:#f7f9fa;border:1px solid #e0e6ea;padding:10px;border-radius:9px;text-align:center}
.free-card{background:#f3fbef;border:1px solid #b9dda9}
.free-total{font-size:24px;font-weight:900;color:#2b6a1f}
@media(max-width:800px){.plan-grid,.timebreak-grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.plan-grid,.timebreak-grid{grid-template-columns:1fr}}
.quick-panel{position:sticky;top:0;z-index:900;background:#f4f6f8;border-bottom:1px solid #d7dee4;padding-top:8px;margin:0 -15px 14px;padding-left:15px;padding-right:15px}
.quick-card{border:2px solid #cbd7df;background:#fff}
.quick-current{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;background:#17202a;color:#fff;border-radius:10px;padding:12px 14px;margin-bottom:12px}
.quick-current strong{font-size:18px}.quick-current .timer{font-size:22px;color:#fff}
.quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.quick-btn,.quick-link{min-height:78px;width:100%;border:2px solid #c8d2d9;background:#fff;color:#17202a;border-radius:10px;padding:8px 6px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;font-weight:900;text-align:center;text-decoration:none;cursor:pointer}
.quick-btn svg,.quick-link svg{width:36px;height:36px;stroke:currentColor;stroke-width:2.4;fill:none;stroke-linecap:round;stroke-linejoin:round}
.quick-btn span,.quick-link span{font-size:14px;line-height:1.1}
.quick-btn.active{background:#087f23;color:#fff;border-color:#087f23}
.quick-btn.pause-active{background:#b76e00;color:#fff;border-color:#b76e00}
.quick-btn.danger{border-color:#d99a9a}.quick-btn.danger.active{background:#b42318;border-color:#b42318}
.quick-link.shortcut{background:#eef6ff;border-color:#9fc4e5}
.quick-select-row{display:grid;grid-template-columns:1fr;gap:8px;margin:10px 0 12px}
.quick-flash{border-radius:10px;padding:10px 12px;margin:10px 0;font-weight:850}
.quick-flash.ok{background:#e7f6ec;border:1px solid #9dd8ad;color:#126d2d}.quick-flash.bad{background:#fde8e8;border:1px solid #d99a9a;color:#8b2525}
@media(max-width:760px){.quick-grid{grid-template-columns:repeat(3,1fr)}.quick-btn,.quick-link{min-height:86px}.quick-btn svg,.quick-link svg{width:40px;height:40px}.quick-btn span,.quick-link span{font-size:15px}}
@media(max-width:480px){.quick-grid{grid-template-columns:repeat(2,1fr)}}
</style>
<link rel="stylesheet" href="assets/workspace_v8_3.css?v=1">
<link rel="stylesheet" href="assets/task_photos_inline_v8_4c.css?v=1">

<link rel="stylesheet" href="assets/workspace_v8_8.css">

</head>
<body>

<?php
$smsFlash = $_SESSION['work_sms_flash'] ?? null;
unset($_SESSION['work_sms_flash']);

/* V8.7 COMPLETE SMS RESULT TOAST */
$latestOutboundSms = null;

foreach ($smsMessages as $smsCandidate) {
    if (
        strtolower((string)($smsCandidate['direction'] ?? 'outbound'))
        === 'outbound'
    ) {
        $latestOutboundSms = $smsCandidate;
        break;
    }
}

$latestOutboundSmsId =
    (int)($latestOutboundSms['id'] ?? 0);

$latestOutboundSmsStatus =
    trim((string)(
        $latestOutboundSms['provider_status']
        ?? $latestOutboundSms['status']
        ?? ''
    ));

$latestOutboundSmsPurpose =
    trim((string)($latestOutboundSms['purpose'] ?? ''));

$latestOutboundSmsMessage =
    trim((string)($latestOutboundSms['message'] ?? ''));
?>
<?php if(is_array($smsFlash)):?>
<div id="sms-dispatch-toast" style="
position:fixed;
top:18px;
right:18px;
z-index:10000;
width:min(430px,calc(100vw - 36px));
background:<?=$smsFlash['ok']?'#e7f6ec':'#fde8e8'?>;
border:2px solid <?=$smsFlash['ok']?'#268447':'#b42318'?>;
border-radius:12px;
padding:15px 17px;
box-shadow:0 8px 28px #0003;
">
    <div style="display:flex;justify-content:space-between;gap:12px">
        <strong style="font-size:17px">
            <?=$smsFlash['ok']?'✓ SMS dispatched successfully':'✕ SMS dispatch failed'?>
        </strong>
        <button type="button" onclick="document.getElementById('sms-dispatch-toast').remove()" style="border:0;background:transparent;font-size:20px;cursor:pointer">×</button>
    </div>

    <?php if(!empty($smsFlash['purpose'])):?><div class="small" style="margin-top:6px"><b>Type:</b> <?=wt_html($smsFlash['purpose'])?></div><?php endif;?>
    <?php if(!empty($smsFlash['time'])):?><div class="small"><b>Time:</b> <?=wt_html($smsFlash['time'])?></div><?php endif;?>
    <?php if(!empty($smsFlash['status'])):?><div class="small"><b>Gateway:</b> <?=wt_html($smsFlash['status'])?></div><?php endif;?>

    <?php if(!empty($smsFlash['message'])):?>
    <div style="margin-top:9px;padding:9px;background:#fff;border-radius:8px;font-size:13px;white-space:pre-wrap"><?=wt_html($smsFlash['message'])?></div>
    <?php endif;?>

    <?php if($smsFlash['ok']):?>
    <div class="small" style="margin-top:8px">Accepted for dispatch. Handset delivery may be confirmed separately by the delivery-status service.</div>
    <?php endif;?>
</div>
<?php endif;?>
<div class="wrap">

<p>
    <a href="index.php">← All jobs</a>
    · <a href="task_photos.php?id=<?=$id?>">Task photos</a>
    · <a href="social_drafts.php?id=<?=$id?>">Social drafts</a>
</p>
<h1>Manage Job #<?=$id?> — <?=wt_html($job['customer_name'])?></h1>
<p><?=wt_html($job['job_address'])?></p>

<?php
$quickLabels = [
    'idle' => 'No timer running',
    'travel_site' => 'Travelling to site',
    'arrive_site' => 'Arrived / on site',
    'work' => 'Working',
    'leave_site' => 'Leaving site',
    'supplier_out' => 'Going to supplier',
    'supplier_return' => 'Returning from supplier',
    'coffee_break' => 'Coffee break',
    'meal_break' => 'Meal break',
];
$quickCurrentLabel = $quickLabels[$quickActiveAction] ?? 'Activity running';
$quickTaskTitle = '';
if ($quickSession && !empty($quickSession['task_id']) && isset($taskById[(int)$quickSession['task_id']])) {
    $quickTaskTitle = (string)$taskById[(int)$quickSession['task_id']]['title'];
}
?>
<section class="quick-panel" id="quick-actions">
<div class="card quick-card">
    <div class="quick-current">
        <div>
            <strong><?=wt_html($quickCurrentLabel)?></strong>
            <div class="small" style="color:#dbe5ec">
                <?= $quickTaskTitle !== '' ? wt_html($quickTaskTitle) : 'Choose a shortcut below' ?>
            </div>
        </div>
        <?php if($quickSession):?>
        <div
            class="timer live-timer"
            data-start="<?=wt_html($quickSession['started_at'])?>"
            data-break-seconds="<?=(int)($quickSession['break_seconds'] ?? 0)?>"
            data-paused="<?=!empty($quickSession['active_break']) ? '1' : '0'?>"
        >00:00:00</div>
        <?php else:?>
        <div class="timer">00:00:00</div>
        <?php endif;?>
    </div>

    <?php if(is_array($quickActionFlash)):?>
        <div class="quick-flash <?=$quickActionFlash['ok'] ? 'ok' : 'bad'?>">
            <?=wt_html((string)$quickActionFlash['message'])?>
        </div>
    <?php endif;?>

    <form method="post" action="../../api/work/quick_action.php">
        <input type="hidden" name="job_id" value="<?=$id?>">
        <div class="quick-select-row">
            <label class="field">
                <span>Task for the next activity</span>
                <select name="quick_task_id">
                    <option value="0">General / not task-specific</option>
                    <?php foreach($tasks as $qt):?>
                        <?php if(($qt['status'] ?? '') !== 'cancelled'):?>
                            <option value="<?=(int)$qt['id']?>" <?=$quickSession && (int)($quickSession['task_id'] ?? 0)===(int)$qt['id']?'selected':''?>>
                                <?=wt_html($qt['title'])?>
                            </option>
                        <?php endif;?>
                    <?php endforeach;?>
                </select>
            </label>
            <label class="checkline">
                <input type="checkbox" name="quick_charge_travel" value="1">
                Charge travel time for CBD / north-of-Yarra / special toll or parking jobs
            </label>
        </div>

        <div class="quick-grid">
            <button class="quick-btn<?=$quickActiveAction==='travel_site'?' active':''?>" type="submit" name="quick_action" value="travel_site" title="Start travelling to the customer site">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M5 30h23M7 24l5-9h14l5 9M10 30a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM26 30a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM35 24v14h8V24M32 27l7-7 7 7"/></svg>
                <span>Travel to site</span>
            </button>
            <button class="quick-btn<?=$quickActiveAction==='work' || $quickActiveAction==='arrive_site'?' active':''?>" type="submit" name="quick_action" value="arrive_site" title="Arrive on site and start work">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 25l16-14 16 14M13 23v17h22V23M20 40V28h8v12M34 12l7 7M33 15l-3-3 4-4 3 3Z"/></svg>
                <span>Arrived</span>
            </button>
            <button class="quick-btn<?=$quickActiveAction==='work'?' active':''?>" type="submit" name="quick_action" value="work" title="Start or change to on-site work">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M17 10l21 21-7 7L10 17M15 23 9 29l10 10 6-6M31 7l10 10"/></svg>
                <span>Work</span>
            </button>
            <button class="quick-btn<?=$quickActiveAction==='leave_site'?' active':''?>" type="submit" name="quick_action" value="leave_site" title="Leave the customer site">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M5 22l14-12 14 12M10 20v16h18V20M31 32h10M37 26l6 6-6 6M18 36V26h6v10"/></svg>
                <span>Leave site</span>
            </button>
            <button class="quick-btn<?=$quickActiveAction==='supplier_out'?' active':''?>" type="submit" name="quick_action" value="supplier_out" title="Travel to Bunnings or another supplier">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M6 31h19M8 25l4-8h11l5 8M10 31a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM25 31a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM34 39V16h9v23M34 23h9M34 30h9"/></svg>
                <span>Go supplier</span>
            </button>
            <button class="quick-btn<?=$quickActiveAction==='supplier_return'?' active':''?>" type="submit" name="quick_action" value="supplier_return" title="Return from Bunnings or supplier">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M6 39V16h10v23M6 23h10M6 30h10M23 31h17M29 25l-6 6 6 6M28 31a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM41 31a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
                <span>Return</span>
            </button>
            <a class="quick-link shortcut" href="task_photos.php?id=<?=$id?>#bulk-upload" title="Upload job photos without changing the timer">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 17h9l3-5h8l3 5h9v22H8ZM24 34a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z"/></svg>
                <span>Add photos</span>
            </a>
            <a class="quick-link shortcut" href="materials.php?id=<?=$id?>#receipts" title="Add a receipt or material without changing the timer">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M14 6h20v36l-5-3-5 3-5-3-5 3ZM19 17h10M19 24h10M19 31h7"/></svg>
                <span>Add receipt</span>
            </a>
            <button class="quick-btn<?=$quickActiveAction==='coffee_break'?' pause-active':''?>" type="submit" name="quick_action" value="coffee_break" title="Start a non-chargeable coffee or short break">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M13 19h21v11a9 9 0 0 1-9 9h-3a9 9 0 0 1-9-9ZM34 22h4a4 4 0 0 1 0 8h-4M16 10c2 2-2 4 0 6M24 10c2 2-2 4 0 6M32 10c2 2-2 4 0 6"/></svg>
                <span>Coffee break</span>
            </button>
            <button class="quick-btn<?=$quickActiveAction==='meal_break'?' pause-active':''?>" type="submit" name="quick_action" value="meal_break" title="Start a non-chargeable meal break">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 40a14 14 0 1 0 0-28 14 14 0 0 0 0 28ZM9 8v14M5 8v8M13 8v8M38 8v32M34 20h8"/></svg>
                <span>Meal break</span>
            </button>
            <button class="quick-btn" type="button" data-quick-open="drying" title="Open the drying or curing form without starting overlapping labour">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M15 7h18M15 41h18M17 7c0 10 14 10 14 17S17 31 17 41M31 7c0 10-14 10-14 17s14 7 14 17M21 17h6M21 32h6"/></svg>
                <span>Drying/curing</span>
            </button>
            <button class="quick-btn danger" type="submit" name="quick_action" value="finish_activity" title="Finish the current activity">
                <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 42a18 18 0 1 0 0-36 18 18 0 0 0 0 36ZM15 25l6 6 13-14"/></svg>
                <span>Finish</span>
            </button>
        </div>
    </form>
</div>
</section>

<div class="card" id="customer-details">
<h2>Customer details</h2>

<?php if(!empty($_GET['customer_saved'])):?>
<div class="notice-good" style="padding:10px;border-radius:9px;margin-bottom:12px">
Customer details saved.
</div>
<?php endif;?>

<form method="post" action="../../api/work/update_customer_details.php">
<input type="hidden" name="job_id" value="<?=$id?>">

<div class="row">
  <div class="field">
    <label>Customer name</label>
    <input
      type="text"
      name="customer_name"
      required
      value="<?=wt_html((string)($job['customer_name']??''))?>"
    >
  </div>

  <div class="field">
    <label>Mobile / phone</label>
    <input
      type="tel"
      name="customer_phone"
      value="<?=wt_html((string)($job['customer_phone']??''))?>"
      placeholder="e.g. 0467 123 456"
    >
    <span class="small">Spaces and Australian 04 numbers are automatically normalised for SMS.</span>
  </div>

  <div class="field">
    <label>Email</label>
    <input
      type="email"
      name="customer_email"
      value="<?=wt_html((string)($job['customer_email']??''))?>"
    >
  </div>
</div>

<button class="btn" type="submit">SAVE CUSTOMER DETAILS</button>
</form>
</div>

<div class="card" id="schedule-access">
<h2>Schedule, parking &amp; access</h2>

<?php if(!empty($_GET['schedule_saved'])):?>
<div class="notice-good" style="padding:10px;border-radius:9px;margin-bottom:12px">
Schedule and site instructions saved.
</div>
<?php endif;?>

<form method="post" action="../../api/work/update_schedule.php">
<input type="hidden" name="job_id" value="<?=$id?>">

<div class="row">
  <div class="field">
    <label>Planned start</label>
    <input
      type="datetime-local"
      name="planned_start_at"
      value="<?=!empty($job['planned_start_at']) ? wt_html(date('Y-m-d\TH:i',strtotime($job['planned_start_at']))) : ''?>"
    >
  </div>

  <div class="field">
    <label>Expected finish</label>
    <input
      type="datetime-local"
      name="planned_finish_at"
      value="<?=!empty($job['planned_finish_at']) ? wt_html(date('Y-m-d\TH:i',strtotime($job['planned_finish_at']))) : ''?>"
    >
  </div>
</div>

<div class="row" style="margin-top:10px">
  <div class="field wide">
    <label>Parking instructions</label>
    <textarea name="parking_notes" rows="3" placeholder="e.g. Park in driveway beside garage"><?=wt_html((string)($job['parking_notes']??''))?></textarea>
  </div>

  <div class="field wide">
    <label>Access / arrival instructions</label>
    <textarea name="access_notes" rows="3" placeholder="e.g. Side gate unlocked, call on arrival, reception on level 2"><?=wt_html((string)($job['access_notes']??''))?></textarea>
  </div>
</div>

<p class="small">
Customer day-before confirmation:
<b><?=wt_html(ucwords(str_replace('_',' ',(string)($job['customer_confirmation_status']??'not_requested'))))?></b>
<?php if(!empty($job['confirmation_requested_at'])):?>
 · requested <?=wt_html($job['confirmation_requested_at'])?>
<?php endif;?>
<?php if(!empty($job['confirmation_received_at'])):?>
 · response <?=wt_html($job['confirmation_received_at'])?>
<?php endif;?>
</p>

<button class="btn" type="submit">SAVE SCHEDULE &amp; SITE INFO</button>
</form>
</div>

<p><a class="btn" href="task_photos.php?id=<?=$id?>">📷 TASK BEFORE / AFTER PHOTOS</a></p>

<div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
  <div><strong>🔔 Customer change alerts</strong><div class="small">Live admin alerts are active. Enable Chrome notifications so changes can also appear as desktop alerts.</div></div>
  <button type="button" class="btn notify-btn" id="enableBrowserAlerts">ENABLE CHROME ALERTS</button>
</div>
<div id="liveChangeAlert" class="live-change-alert <?= $pendingChangeCount ? 'show' : '' ?>" role="alert" aria-live="assertive">
  <div><strong>🔔 Customer change awaiting review</strong><div id="liveChangeText" class="small"><?= $pendingChangeCount ? $pendingChangeCount.' unresolved customer change request'.($pendingChangeCount===1?'':'s').'.' : '' ?></div></div>
  <div class="live-alert-actions"><a class="btn" id="reviewLiveChange" href="#tasks">REVIEW NOW</a></div>
</div>


<!-- V8.4A MATERIALS PLANNING CARD -->
<div class="card">
  <h2>Materials &amp; expenses</h2>
  <?php
    $matMode = (string)($job['materials_responsibility'] ?? 'mike_advise');
    $matLabels = [
      'mike_all' => 'Mike to provide all materials',
      'customer_all' => 'Customer says they already have all materials',
      'shared' => 'Customer has some / Mike to provide some',
      'labour_only' => 'Labour only / no materials required',
      'mike_advise' => 'Not sure — Mike to advise',
    ];
  ?>
  <p><b>Customer materials instruction:</b> <?=wt_html($matLabels[$matMode] ?? $matLabels['mike_advise'])?></p>
  <?php if(trim((string)($job['materials_notes'] ?? '')) !== ''):?>
    <p><b>Customer note:</b><br><?=nl2br(wt_html((string)$job['materials_notes']))?></p>
  <?php endif;?>
  <p class="muted">This is the planning instruction supplied with the job. Actual materials, costs, supplier and reimbursement records will be recorded separately.</p>
</div>

<!-- V8.4B MATERIALS MANAGER -->
<p><a class="btn" href="materials.php?id=<?=$id?>">🧰 OPEN DETAILED MATERIALS MANAGER</a></p>
<div class="card intake-card" id="customer-request">
<h2>Customer request / intake</h2>
<p class="small">The customer can edit this list immediately from their live job link. Their revisions are retained instead of silently replacing the history.</p>
<div style="white-space:pre-wrap;background:#fff;border:1px solid #d7e3ec;border-radius:10px;padding:12px"><?=wt_html($customerRequest)?></div>
<p><span class="ai-status <?=wt_html((string)($job['ai_breakdown_status']??'not_requested'))?>">AI breakdown: <?=wt_html(str_replace('_',' ',(string)($job['ai_breakdown_status']??'not requested')))?></span><?php if(!empty($job['ai_breakdown_generated_at'])):?> <span class="small">Generated <?=wt_html($job['ai_breakdown_generated_at'])?></span><?php endif;?></p>
<?php if(!empty($job['ai_breakdown_error'])):?><div class="notice-warn" style="padding:10px;border-radius:9px"><?=wt_html($job['ai_breakdown_error'])?></div><?php endif;?>
<div class="row">
<form method="post" action="../../api/work/send_customer_job_link.php"><input type="hidden" name="job_id" value="<?=$id?>"><button class="btn" type="submit">SEND / RESEND CUSTOMER LINK</button></form>
<button class="btn" type="button" id="generateAiTasks">GENERATE / UPDATE AI TASK BREAKDOWN</button>
</div>
<div id="aiBreakdownMessage" class="small" style="margin-top:8px"></div>
<?php foreach($pendingRequestRevisions as $rr):?><div class="request-revision"><b>Customer updated job list — awaiting your review</b><div class="small"><?=wt_html($rr['created_at'])?></div><div style="white-space:pre-wrap;margin:8px 0"><?=wt_html($rr['new_text'])?></div><form method="post" action="../../api/work/review_customer_request.php"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="revision_id" value="<?=$rr['id']?>"><button class="btn" type="submit">MARK REVIEWED &amp; NOTIFY CUSTOMER</button></form></div><?php endforeach;?>
</div>

<div class="grid">
    <div class="metric">Job to date<div class="big"><?=wt_money($tot['total'])?></div></div>
    <div class="metric">Paid<div class="big"><?=wt_money($tot['payments'])?></div></div>
    <div class="metric">Outstanding<div class="big"><?=wt_money($tot['outstanding'])?></div></div>
</div>

<?php if(($_GET['started'] ?? '') === '1'):?>
<div class="card notice-good"><b>✓ SESSION STARTED.</b> The activity is now running and visible in the live job record.</div>
<?php elseif(($_GET['duplicate'] ?? '') === '1'):?>
<div class="card notice-warn"><b>SESSION ALREADY RUNNING.</b> That worker already has an active session. Stop it before starting another one.</div>
<?php elseif(($_GET['stopped'] ?? '') === '1'):?>
<div class="card notice-good"><b>✓ SESSION STOPPED.</b> The stop reason and expected return have been recorded.</div>
<?php elseif(($_GET['retrospective_added'] ?? '') === '1'):?>
<div class="card notice-good"><b>✓ PAST WORK ADDED.</b> This session is clearly marked as a retrospective entry and is included in the job history/totals.</div>
<?php elseif(($_GET['task_added'] ?? '') === '1'):?>
<div class="card notice-good"><b>✓ TASK ADDED.</b></div>
<?php elseif(($_GET['task_saved'] ?? '') === '1'):?>
<div class="card notice-good"><b>✓ TASK UPDATED.</b></div>
<?php elseif(($_GET['source_saved'] ?? '') === '1'):?>
<div class="card notice-good"><b>✓ JOB SOURCE SAVED.</b></div>
<?php elseif(($_GET['on_my_way'] ?? '') === '1'):?>
<div class="card notice-good"><b>🚗 ON MY WAY STARTED.</b> Travel time is now running and the ETA/customer update has been recorded.</div>
<?php elseif(($_GET['arrived'] ?? '') === '1'):?>
<div class="card notice-good"><b>📍 ARRIVED.</b> Travel was stopped and a new on-site work session was started.</div>
<?php endif;?>

<?php foreach($runningSessions as $rs):
    $locLabel = $locationLabels[$rs['start_location'] ?? '']
        ?? ($rs['start_location'] ?: 'Not specified');

    $isPaused = !empty($rs['active_break']);
?>
<div class="card running-card" id="live-timer">
    <div class="running-head">
        <div>
            <div class="running-title">
                <?=$isPaused ? '⏸ ACTIVITY PAUSED' : '● ACTIVITY RUNNING'?>
                — <?=wt_html($rs['worker_name'] ?: 'Mike')?>
            </div>

            <div>
                <b><?=wt_html($locLabel)?></b>
                <?php if(!empty($rs['location_detail'])):?>
                    — <?=wt_html($rs['location_detail'])?>
                <?php endif;?>
            </div>

            <div class="small">
                Started <?=wt_html(wt_melbourne_time($rs['started_at']))?>
                · <?=wt_html($rs['category'])?>
            </div>

            <?php if(!empty($rs['notes'])):?>
                <div style="margin-top:6px">
                    <?=wt_html($rs['notes'])?>
                </div>
            <?php endif;?>
        </div>

        <div
            class="timer live-timer"
            data-start="<?=wt_html($rs['started_at'])?>"
            data-break-seconds="<?=(int)($rs['break_seconds'] ?? 0)?>"
            data-paused="<?=$isPaused ? '1' : '0'?>"
        >00:00:00</div>
    </div>

    <?php if($isPaused):?>

        <div
            class="notice-warn"
            style="padding:12px;border-radius:10px;margin-top:14px"
        >
            <b>Non-chargeable break in progress.</b><br>
            The original activity remains open, but break time is excluded
            from the customer’s job tally.
        </div>

        <form
            method="post"
            action="../../api/work/continue_session.php"
            style="margin-top:12px"
        >
            <input type="hidden" name="job_id" value="<?=$id?>">
            <input type="hidden" name="session_id" value="<?=$rs['id']?>">

            <button
                class="btn start"
                style="font-size:20px;padding:17px 24px;width:100%"
            >
                ▶ CONTINUE SAME ACTIVITY
            </button>
        </form>

    <?php else:?>

        <?php if(
            ($rs['travel_type'] ?? '') === 'to_customer'
            && $rs['worker_id'] === null
        ):?>

        <form
            method="post"
            action="../../api/work/arrive_start_work.php"
            style="margin-top:14px"
        >
            <input type="hidden" name="job_id" value="<?=$id?>">

            <div class="field">
                <label>Task</label>
                <select name="task_id">
                    <option value="0">General / not task-specific</option>

                    <?php foreach($activeTasks as $task):?>
                    <option value="<?=$task['id']?>">
                        <?=wt_html($task['title'])?>
                    </option>
                    <?php endforeach;?>
                </select>
            </div>

            <div class="field wide">
                <label>What are you starting on site?</label>
                <input
                    name="notes"
                    placeholder="e.g. continue bathroom preparation"
                    required
                >
            </div>

            <button
                class="btn start"
                style="font-size:19px;padding:16px 22px"
            >
                📍 ARRIVED — STOP TRAVEL &amp; START WORK
            </button>
        </form>

        <?php endif;?>

        <div
            class="row"
            style="margin-top:14px;align-items:flex-start"
        >
            <form
                method="post"
                action="../../api/work/pause_session.php"
                style="flex:1;min-width:260px"
            >
                <input type="hidden" name="job_id" value="<?=$id?>">
                <input type="hidden" name="session_id" value="<?=$rs['id']?>">

                <div class="field">
                    <label>Non-chargeable interruption</label>

                    <select name="pause_reason">
                        <option value="toilet">
                            Quick personal break
                        </option>
                        <option value="meal">
                            Meal break
                        </option>
                        <option value="personal_call">
                            Personal phone call
                        </option>
                        <option value="rest">
                            Rest break
                        </option>
                        <option value="other">
                            Other non-chargeable break
                        </option>
                    </select>
                </div>

                <button
                    class="btn"
                    style="
                        background:#b76e00;
                        font-size:18px;
                        padding:15px 22px;
                        width:100%
                    "
                >
                    ⏸ PAUSE
                </button>
            </form>

            <details
                class="stop-panel"
                style="flex:2;min-width:280px;margin-top:0"
            >
                <summary style="cursor:pointer">
                    <b>🔄 Change activity or ■ finish activity</b>
                </summary>

                <form
                    method="post"
                    action="../../api/work/stop_session.php"
                    enctype="multipart/form-data"
                    data-current-task-id="<?=(int)($rs['task_id'] ?? 0)?>"
                    style="margin-top:12px"
                >
                    <input type="hidden" name="job_id" value="<?=$id?>">
                    <input type="hidden" name="session_id" value="<?=$rs['id']?>">
                    <div class="field">
                        <label>What do you want to do?</label>

                        <select
                            name="session_action"
                            class="session-action-select"
                            required
                        >
                            <option value="change">
                                Change activity — remain on this job
                            </option>
                            <option value="finish">
                                Finish current activity
                            </option>
                            <?php if(!empty($activeTasks)):?>
                            <option value="finish_task_start_next">
                                <?=!empty($rs['task_id']) ? 'Finish current task + start next task' : 'Finish current activity + start a task'?>
                            </option>
                            <?php endif;?>
                        </select>
                    </div>

                    <div class="change-activity-fields">
                        <div class="field">
                            <label>New location</label>

                            <select name="start_location">
                                <option value="travel_job">
                                    Travelling for this job
                                </option>
                                <option value="bunnings">
                                    Bunnings
                                </option>
                                <option value="supplier">
                                    Another supplier / store
                                </option>
                                <option value="onsite">
                                    On site
                                </option>
                                <option value="workshop_home">
                                    Workshop / home preparation
                                </option>
                                <option value="offsite_planning">
                                    Off-site planning / admin
                                </option>
                                <option value="other">
                                    Other
                                </option>
                            </select>
                        </div>

                        <div class="field">
                            <label>New activity category</label>

                            <select name="category">
                                <option value="travel">
                                    Job-specific travel
                                </option>
                                <option value="procurement">
                                    Sourcing / procurement
                                </option>
                                <option value="onsite">
                                    On-site work
                                </option>
                                <option value="loading_setup">
                                    Loading / setup / pack-up
                                </option>
                                <option value="planning">
                                    Planning
                                </option>
                                <option value="measurement">
                                    Measurement / investigation
                                </option>
                                <option value="repair">
                                    Repair / preparation
                                </option>
                                <option value="unforeseen">
                                    Unforeseen / remedial
                                </option>
                                <option value="other">
                                    Other
                                </option>
                            </select>
                        </div>

                        <div class="field wide">
                            <label>What are you changing to?</label>

                            <input
                                name="notes"
                                placeholder="e.g. travelling to Bunnings for tri-quad trim sprays"
                            >
                        </div>
                    </div>

                    <div class="field wide">
                        <label>Note about the change or completed activity</label>
                        <input
                            name="stop_note"
                            placeholder="Optional"
                        >
                    </div>

                    <?php if(!empty($activeTasks)):?>
                    <div class="finish-next-fields" style="display:none">
                        <div class="field">
                            <label><?=!empty($rs['task_id']) ? 'Next task' : 'Task to start'?></label>
                            <select name="next_task_id">
                                <option value=""><?=!empty($rs['task_id']) ? 'Select next task...' : 'Select task to start...'?></option>
                                <?php foreach($activeTasks as $nextTask):?>
                                    <?php if(!empty($rs['task_id']) && (int)$nextTask['id'] === (int)$rs['task_id']) continue;?>
                                    <option value="<?=$nextTask['id']?>"><?=wt_html($nextTask['title'])?></option>
                                <?php endforeach;?>
                            </select>
                        </div>

                        <div class="field">
                            <label>Next location</label>
                            <select name="next_start_location">
                                <option value="onsite">On site</option>
                                <option value="bunnings">Bunnings</option>
                                <option value="supplier">Another supplier / store</option>
                                <option value="travel_job">Travelling for this job</option>
                                <option value="workshop_home">Workshop / home preparation</option>
                                <option value="offsite_planning">Off-site planning / admin</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="field">
                            <label>Next activity type</label>
                            <select name="next_category">
                                <option value="onsite">On-site work</option>
                                <option value="measurement">Measurement / investigation</option>
                                <option value="planning">Planning</option>
                                <option value="procurement">Sourcing / procurement</option>
                                <option value="travel">Job-specific travel</option>
                                <option value="loading_setup">Loading / setup / pack-up</option>
                                <option value="demolition">Demolition / removal</option>
                                <option value="repair">Repair / preparation</option>
                                <option value="unforeseen">Unforeseen / remedial</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="field wide">
                            <label>What are you doing next?</label>
                            <input
                                name="next_notes"
                                placeholder="e.g. begin sanding the filled section"
                            >
                        </div>
                    </div>
                    <?php endif;?>

                    <div class="task-detail-box" style="border:2px solid #3973a8;background:#eef6ff">
                        <b>📷 Add photos with this finish</b>
                        <p class="small" style="margin:6px 0 10px">Optional. Add up to 8 photos for the completed activity or finished current task.</p>
                        <input type="file" name="finish_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                        <div class="field" style="margin-top:9px">
                            <label>Photo note (optional)</label>
                            <input name="finish_photo_note" placeholder="e.g. task complete; ready for next stage">
                        </div>
                    </div>

                    <details class="task-detail-box task-sms-box">
                        <summary>📱 Customer SMS for this finish</summary>
                        <p class="small" style="margin-top:10px">
                            Use Save only when the customer does not need an SMS, or Save + SMS customer when you want them notified of this finish.
                        </p>
                        <div class="field">
                            <label>SMS message</label>
                            <textarea name="customer_sms_message" rows="4">Mike of All Trades update: current job activity has finished. Your job record has been updated.</textarea>
                        </div>
                    </details>

                    <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:12px">
                        <button
                            class="btn stop"
                            name="save_action"
                            value="save_only"
                            style="font-size:18px;padding:15px 22px"
                        >
                            SAVE ONLY
                        </button>

                        <button
                            class="btn sms"
                            name="save_action"
                            value="save_sms"
                            style="font-size:18px;padding:15px 22px"
                        >
                            📱 SAVE + SMS CUSTOMER
                        </button>
                    </div>
                </form>
            </details>
        </div>

    <?php endif;?>
</div>
<?php endforeach;?>

<datalist id="eta-options">
    <option value="About 10 minutes">
    <option value="About 20 minutes">
    <option value="About 30 minutes">
    <option value="About 1 hour">
    <option value="Later today">
    <option value="Tomorrow morning">
    <option value="Tomorrow">
    <option value="Not returning today">
    <option value="To be confirmed">
</datalist>

<?php if($job['unpaid_balance_limit'] && $tot['outstanding'] >= (float)$job['unpaid_balance_limit']):?>
<div class="card warn">
    <b>PAYMENT REVIEW:</b> outstanding balance has reached the configured limit of <?=wt_money((float)$job['unpaid_balance_limit'])?>.
</div>
<?php endif;?>

<div class="card info">
<h2>Pricing / agreement status</h2>
<p><span class="tag"><?=wt_html($pricingLabel)?></span></p>

<?php if(!empty($job['original_scope'])):?>
<p><b>Original scope:</b><br><?=nl2br(wt_html($job['original_scope']))?></p>
<?php endif;?>

<?php if(!empty($job['current_scope'])):?>
<p><b>Current / expanded scope:</b><br><?=nl2br(wt_html($job['current_scope']))?></p>
<?php endif;?>

<?php if(!empty($job['unforeseen_conditions'])):?>
<p><b>Changed / unforeseen conditions:</b><br><?=nl2br(wt_html($job['unforeseen_conditions']))?></p>
<?php endif;?>

<?php if($variationRequired):?>
<div class="variation" style="padding:14px;border-radius:10px">
    <b>Fixed-price variation required</b>
    <p><?=nl2br(wt_html($job['variation_description'] ?: $job['current_scope']))?></p>
    <p><b>Pricing:</b> <?=wt_html($variationLabel)?></p>

    <?php if(($job['variation_pricing_method'] ?? '') === 'fixed_amount' && $job['variation_fixed_amount'] !== null):?>
        <p><b>Additional fixed amount:</b> <?=wt_money((float)$job['variation_fixed_amount'])?></p>
    <?php elseif(($job['variation_pricing_method'] ?? '') === 'hourly' && $job['variation_hourly_rate'] !== null):?>
        <p><b>Variation rate:</b> <?=wt_money((float)$job['variation_hourly_rate'])?>/hr</p>
    <?php elseif(($job['variation_pricing_method'] ?? '') === 'estimate'):?>
        <p><b>Variation forecast:</b> <?=wt_money((float)$job['variation_forecast_low'])?> – <?=wt_money((float)$job['variation_forecast_high'])?></p>
    <?php endif;?>

    <p>
        Variation authorisation:
        <?php if(!empty($job['variation_authorised'])):?>
            <span class="status-good">✓ AUTHORISED</span>
        <?php else:?>
            <span class="status-warn">NOT YET AUTHORISED</span>
        <?php endif;?>
    </p>
</div>
<?php endif;?>

<p><b>Customer agreement:</b>
<?php if(!empty($job['agreement_signed_at'])):?>
    <span class="status-good">✓ Signed <?=wt_html($job['agreement_signed_at'])?> by <?=wt_html($job['agreement_name'] ?? '')?></span>
<?php else:?>
    <span class="status-warn">Not yet signed</span>
<?php endif;?>
</p>

<?php if(!empty($job['agreement_version'])):?>
<p class="small">Agreement version: <?=wt_html($job['agreement_version'])?></p>
<?php endif;?>
</div>


<?php if(($_GET['plan_saved'] ?? '')==='1'):?>
<div class="card notice-good"><b>✓ TODAY'S WORK PLAN UPDATED.</b> The customer live record now shows the revised plan.</div>
<?php endif;?>

<div class="card plan-card">
<h2>Today's work plan</h2>
<p class="small">Set expectations before the customer starts wondering where you are. Times and hours are estimates only and can be updated during the day.</p>

<form method="post" action="../../api/work/save_daily_plan.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="plan_date" value="<?=wt_html($today)?>">

<div class="plan-grid">
    <div class="field">
        <label>Planned start</label>
        <input type="time" name="planned_start_time" value="<?=wt_html($todayPlan['planned_start_time'] ?? '')?>">
    </div>
    <div class="field">
        <label>Anticipated finish tonight</label>
        <input type="time" name="planned_finish_time" value="<?=wt_html($todayPlan['planned_finish_time'] ?? '')?>">
    </div>
    <div class="field">
        <label>Anticipated job hours low</label>
        <input type="number" step=".25" min="0" name="anticipated_job_hours_low" value="<?=wt_html((string)($todayPlan['anticipated_job_hours_low'] ?? ''))?>">
    </div>
    <div class="field">
        <label>Anticipated job hours high</label>
        <input type="number" step=".25" min="0" name="anticipated_job_hours_high" value="<?=wt_html((string)($todayPlan['anticipated_job_hours_high'] ?? ''))?>">
    </div>
</div>

<div class="plan-grid" style="margin-top:10px">
    <div class="field">
        <label>Expected workers today</label>
        <input type="number" min="1" name="expected_worker_count" value="<?=wt_html((string)($todayPlan['expected_worker_count'] ?? 1))?>">
    </div>
    <div class="field wide">
        <label>Who is expected?</label>
        <input name="expected_workers_text" value="<?=wt_html($todayPlan['expected_workers_text'] ?? 'Mike only')?>" placeholder="Mike only / Mike + 2 helpers">
    </div>
    <div class="field wide">
        <label>Helper roles / explanation</label>
        <input name="helper_roles" value="<?=wt_html($todayPlan['helper_roles'] ?? '')?>" placeholder="e.g. parents helping with labour, cleanup and carrying">
    </div>
</div>

<label style="display:block;margin-top:12px;font-weight:800">Planned interruptions / other commitments</label>
<textarea name="planned_interruptions" placeholder="e.g. 3:00–5:00 pm — drive to Clayton to collect glass; may briefly attend another customer while nearby; expect back approx. 6:30 pm"><?=wt_html($todayPlan['planned_interruptions'] ?? '')?></textarea>

<label style="display:block;margin-top:12px;font-weight:800">Overall plan / customer expectation note</label>
<textarea name="overall_plan_note" placeholder="e.g. Extended work day planned. I expect to continue well into the evening. Any unrelated errands are excluded from this job's recorded time."><?=wt_html($todayPlan['overall_plan_note'] ?? '')?></textarea>

<button class="btn">Save / update today's plan</button>
</form>
</div>

<div class="card">
<h2>Recorded time breakdown</h2>
<p class="small">This separates time physically on site from supplier/store time, job-related travel and off-site preparation/planning.</p>
<div class="timebreak-grid">
    <div class="timebreak-item"><b>On site</b><br><?=wt_html(wt_duration_hm($timeBreakdown['onsite']))?></div>
    <div class="timebreak-item"><b>At suppliers / stores</b><br><?=wt_html(wt_duration_hm($timeBreakdown['supplier']))?></div>
    <div class="timebreak-item"><b>Job-related travel</b><br><?=wt_html(wt_duration_hm($timeBreakdown['travel']))?></div>
    <div class="timebreak-item"><b>Off-site prep / planning</b><br><?=wt_html(wt_duration_hm($timeBreakdown['offsite']))?></div>
    <div class="timebreak-item"><b>Other job activity</b><br><?=wt_html(wt_duration_hm($timeBreakdown['other']))?></div>
</div>
</div>

<?php if(($_GET['free_added'] ?? '')==='1'):?>
<div class="card notice-good"><b>✓ COMPLIMENTARY VALUE RECORDED.</b> It is visible to the customer but is not added to the billable job total.</div>
<?php endif;?>

<div class="card free-card" id="no-charge-work">

<h2>No-charge work / materials</h2>

<p class="small">
Record anything the customer is not being charged for.
Keep <b>goodwill / free extras</b> separate from
<b>rectification / rework</b>, and record labour and materials separately.
</p>

<form method="post" action="../../api/work/add_complimentary_item.php">

<input type="hidden" name="job_id" value="<?=$id?>">

<div class="row">

    <div class="field">
        <label>Why is this not being charged?</label>
        <select name="no_charge_reason" required>
            <option value="goodwill">Goodwill / love job / free extra</option>
            <option value="rectification">Rectification / correcting my own work</option>
            <option value="other">Other no-charge work</option>
        </select>
    </div>

    <div class="field">
        <label>Type</label>
        <select name="item_type">
            <option value="labour">Labour</option>
            <option value="material">Materials</option>
            <option value="repair">Repair / rectification</option>
            <option value="improvement">Improvement / upgrade</option>
            <option value="other">Other / mixed</option>
        </select>
    </div>

</div>

<div class="field wide">
    <label>Description</label>
    <textarea
        name="description"
        placeholder="What was done or supplied?"
        required
    ></textarea>
</div>

<div class="row">

    <div class="field">
        <label>Labour hours</label>
        <input
            name="labour_hours"
            type="number"
            step=".01"
            min="0"
            placeholder="e.g. 4"
        >
    </div>

    <div class="field">
        <label>Labour value $</label>
        <input
            name="labour_value"
            type="number"
            step=".01"
            min="0"
            placeholder="e.g. 280"
        >
    </div>

    <div class="field">
        <label>Materials value $</label>
        <input
            name="material_value"
            type="number"
            step=".01"
            min="0"
            placeholder="e.g. 45"
        >
    </div>

</div>

<div class="field wide">
    <label>Materials used / supplied</label>
    <textarea
        name="material_details"
        placeholder="e.g. filler, timber, screws, paint, adhesive..."
    ></textarea>
</div>

<div class="field wide">
    <label>Explanation / context</label>
    <textarea
        name="note"
        placeholder="Why was this not charged?"
    ></textarea>
</div>

<button class="btn" type="submit">
    ADD NO-CHARGE ITEM
</button>

</form>

<?php if($complimentaryItems):?>

<h3 style="margin-top:24px">No-charge tallies</h3>

<div style="overflow-x:auto">

<table style="width:100%;border-collapse:collapse">

<thead>
<tr>
    <th style="text-align:left;padding:8px">Category</th>
    <th style="text-align:right;padding:8px">Labour hours</th>
    <th style="text-align:right;padding:8px">Labour</th>
    <th style="text-align:right;padding:8px">Materials</th>
    <th style="text-align:right;padding:8px">Unallocated</th>
    <th style="text-align:right;padding:8px">Total</th>
</tr>
</thead>

<tbody>

<?php
$reasonLabels = [
    'goodwill' => 'Goodwill / free extras',
    'rectification' => 'Rectification / rework',
    'other' => 'Other no-charge',
    'unclassified' => 'Unclassified — review required',
];

foreach ($reasonLabels as $reasonKey=>$reasonLabel):
    $bucket = $noChargeTotals[$reasonKey];
?>

<tr>
    <td style="padding:8px;border-top:1px solid #ddd">
        <b><?=wt_html($reasonLabel)?></b>
    </td>

    <td style="padding:8px;border-top:1px solid #ddd;text-align:right">
        <?=number_format((float)$bucket['hours'],2)?> h
    </td>

    <td style="padding:8px;border-top:1px solid #ddd;text-align:right">
        <?=wt_money((float)$bucket['labour'])?>
    </td>

    <td style="padding:8px;border-top:1px solid #ddd;text-align:right">
        <?=wt_money((float)$bucket['materials'])?>
    </td>

    <td style="padding:8px;border-top:1px solid #ddd;text-align:right">
        <?=wt_money((float)$bucket['unallocated'])?>
    </td>

    <td style="padding:8px;border-top:1px solid #ddd;text-align:right">
        <b><?=wt_money((float)$bucket['total'])?></b>
    </td>
</tr>

<?php endforeach;?>

<tr>
    <td style="padding:10px 8px;border-top:3px solid #333">
        <b>ALL NO-CHARGE WORK</b>
    </td>

    <td style="padding:10px 8px;border-top:3px solid #333;text-align:right">
        <b><?=number_format((float)$noChargeGrand['hours'],2)?> h</b>
    </td>

    <td style="padding:10px 8px;border-top:3px solid #333;text-align:right">
        <b><?=wt_money((float)$noChargeGrand['labour'])?></b>
    </td>

    <td style="padding:10px 8px;border-top:3px solid #333;text-align:right">
        <b><?=wt_money((float)$noChargeGrand['materials'])?></b>
    </td>

    <td style="padding:10px 8px;border-top:3px solid #333;text-align:right">
        <b><?=wt_money((float)$noChargeGrand['unallocated'])?></b>
    </td>

    <td style="padding:10px 8px;border-top:3px solid #333;text-align:right">
        <b><?=wt_money((float)$noChargeGrand['total'])?></b>
    </td>
</tr>

</tbody>
</table>

</div>

<p class="small" style="margin-top:8px">
<b>Unallocated</b> means an older record still contains a value that has not
yet been split between labour and materials. Edit the item to classify it.
</p>

<h3 style="margin-top:24px">Manage no-charge records</h3>

<p class="small">
Classify records here. Once an item is classified it moves out of
<b>Needs review</b> and into its correct section below.
</p>

<?php

$noChargeEditorGroups = [
    'unclassified' => [],
    'goodwill' => [],
    'rectification' => [],
    'other' => [],
];

foreach ($complimentaryItems as $editorItem) {
    $editorReason =
        (string)($editorItem['no_charge_reason'] ?? 'unclassified');

    if (!isset($noChargeEditorGroups[$editorReason])) {
        $editorReason = 'unclassified';
    }

    $noChargeEditorGroups[$editorReason][] = $editorItem;
}

$noChargeEditorMeta = [
    'unclassified' => [
        'title' => '⚠ Needs review',
        'description' => 'These records still need you to decide what they really were.',
        'border' => '#e2c760',
        'background' => '#fff8df',
    ],
    'goodwill' => [
        'title' => '🎁 Goodwill / complimentary',
        'description' => 'Extra labour or materials deliberately provided to the customer at no charge.',
        'border' => '#abd49b',
        'background' => '#f4fbf1',
    ],
    'rectification' => [
        'title' => '🛠 Rectification / rework',
        'description' => 'Work absorbed by Mike to correct or rework previous work. Kept separate from goodwill.',
        'border' => '#dfb4b4',
        'background' => '#fff6f6',
    ],
    'other' => [
        'title' => '📋 Other no-charge',
        'description' => 'Other work or materials that were not charged to the customer.',
        'border' => '#c8d3da',
        'background' => '#f7f9fa',
    ],
];

foreach (
    ['unclassified', 'goodwill', 'rectification', 'other']
    as $editorGroupKey
):

    $editorGroupItems = $noChargeEditorGroups[$editorGroupKey];
    $editorMeta = $noChargeEditorMeta[$editorGroupKey];
    $editorTotals = $noChargeTotals[$editorGroupKey];

?>

<div
    id="no-charge-group-<?=wt_html($editorGroupKey)?>"
    style="
        margin-top:18px;
        padding:14px;
        border:2px solid <?=wt_html($editorMeta['border'])?>;
        border-radius:12px;
        background:<?=wt_html($editorMeta['background'])?>;
    "
>

<div
    style="
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:flex-start;
        flex-wrap:wrap;
    "
>
    <div>
        <h3 style="margin:0 0 4px">
            <?=wt_html($editorMeta['title'])?>
            (<?=count($editorGroupItems)?>)
        </h3>

        <div class="small">
            <?=wt_html($editorMeta['description'])?>
        </div>
    </div>

    <div
        style="
            text-align:right;
            font-weight:800;
            line-height:1.55;
        "
    >
        <?php if((float)$editorTotals['hours'] > 0):?>
            <?=number_format((float)$editorTotals['hours'],2)?> hrs<br>
        <?php endif;?>

        Labour <?=wt_money((float)$editorTotals['labour'])?><br>
        Materials <?=wt_money((float)$editorTotals['materials'])?>

        <?php if((float)$editorTotals['unallocated'] > 0):?>
            <br>
            Unallocated <?=wt_money((float)$editorTotals['unallocated'])?>
        <?php endif;?>

        <br>
        <span style="font-size:1.12em">
            Total <?=wt_money((float)$editorTotals['total'])?>
        </span>
    </div>
</div>

<?php if(!$editorGroupItems):?>

<p class="small" style="margin:12px 0 0">
    <?=$editorGroupKey === 'unclassified'
        ? '✓ Nothing currently needs review.'
        : 'No records in this category yet.'?>
</p>

<?php endif;?>

<?php foreach($editorGroupItems as $ci):

    $reason = (string)($ci['no_charge_reason'] ?? 'unclassified');

    $displayReason = [
        'goodwill' => 'Goodwill / free extra',
        'rectification' => 'Rectification / rework',
        'other' => 'Other no-charge',
        'unclassified' => 'Unclassified — review required',
    ][$reason] ?? 'Unclassified — review required';

    $labourHours = (float)($ci['labour_hours'] ?? 0);
    $labourValue = (float)($ci['labour_value'] ?? 0);
    $materialValue = (float)($ci['material_value'] ?? 0);
    $legacyTotal = (float)($ci['estimated_value'] ?? 0);

    $unallocated = max(
        0,
        $legacyTotal - $labourValue - $materialValue
    );

?>

<details
    id="complimentary-<?=$ci['id']?>"
    style="
        border-top:1px solid #d9e9d2;
        padding:12px 0
    "
>

<summary style="cursor:pointer">

    <b><?=wt_html($displayReason)?></b> —
    <?=wt_html($ci['description'])?>

    <?php if($labourValue > 0):?>
        · Labour <?=wt_money($labourValue)?>
    <?php endif;?>

    <?php if($materialValue > 0):?>
        · Materials <?=wt_money($materialValue)?>
    <?php endif;?>

    <?php if($unallocated > 0):?>
        · Unallocated <?=wt_money($unallocated)?>
    <?php endif;?>

    <span class="small"> · Edit</span>

</summary>

<?php if($labourHours > 0):?>
<p class="small">
<b>Labour time:</b>
<?=number_format($labourHours,2)?> hours
</p>
<?php endif;?>

<?php if(!empty($ci['material_details'])):?>
<p class="small">
<b>Materials:</b>
<?=nl2br(wt_html($ci['material_details']))?>
</p>
<?php endif;?>

<?php if(!empty($ci['note'])):?>
<p class="small">
<?=nl2br(wt_html($ci['note']))?>
</p>
<?php endif;?>

<form
    method="post"
    action="../../api/work/update_complimentary_item.php"
    style="margin-top:12px"
>

<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="item_id" value="<?=$ci['id']?>">

<div class="row">

    <div class="field">
        <label>Why is this not charged?</label>
        <select name="no_charge_reason" required>

            <?php foreach([
                'unclassified'=>'Unclassified — review this',
                'goodwill'=>'Goodwill / love job / free extra',
                'rectification'=>'Rectification / correcting my own work',
                'other'=>'Other no-charge work',
            ] as $key=>$label):?>

            <option
                value="<?=wt_html($key)?>"
                <?=$reason===$key?'selected':''?>
            >
                <?=wt_html($label)?>
            </option>

            <?php endforeach;?>

        </select>
    </div>

    <div class="field">
        <label>Type</label>
        <select name="item_type">

            <?php foreach([
                'labour'=>'Labour',
                'material'=>'Materials',
                'repair'=>'Repair / rectification',
                'improvement'=>'Improvement / upgrade',
                'other'=>'Other / mixed',
            ] as $key=>$label):?>

            <option
                value="<?=wt_html($key)?>"
                <?=($ci['item_type']??'other')===$key?'selected':''?>
            >
                <?=wt_html($label)?>
            </option>

            <?php endforeach;?>

        </select>
    </div>

</div>

<div class="field wide">

<label>Description</label>

<textarea
    name="description"
    required
><?=wt_html($ci['description']??'')?></textarea>

</div>

<div class="row">

<div class="field">

<label>Labour hours</label>

<input
    name="labour_hours"
    type="number"
    step=".01"
    min="0"
    value="<?=wt_html((string)($ci['labour_hours']??''))?>"
>

</div>

<div class="field">

<label>Labour value $</label>

<input
    name="labour_value"
    type="number"
    step=".01"
    min="0"
    value="<?=wt_html((string)($ci['labour_value']??'0'))?>"
>

</div>

<div class="field">

<label>Materials value $</label>

<input
    name="material_value"
    type="number"
    step=".01"
    min="0"
    value="<?=wt_html((string)($ci['material_value']??'0'))?>"
>

</div>

</div>

<?php if($unallocated > 0):?>

<div
    style="
        margin:10px 0;
        padding:10px;
        border:2px solid #e3b23c;
        border-radius:10px;
        background:#fff8dc
    "
>

<b>Older unallocated value: <?=wt_money($unallocated)?></b><br>

<span class="small">
This was recorded before labour/material splitting existed.
Allocate it above before saving if you know the breakdown.
</span>

</div>

<?php endif;?>

<div class="field wide">

<label>Materials used / supplied</label>

<textarea
    name="material_details"
><?=wt_html($ci['material_details']??'')?></textarea>

</div>

<div class="field wide">

<label>Explanation / context</label>

<textarea
    name="note"
><?=wt_html($ci['note']??'')?></textarea>

</div>

<button class="btn" type="submit">
SAVE CHANGES
</button>

</form>

</details>

<?php endforeach;?>

</div>

<?php endforeach;?>

<?php else:?>

<p class="small">
No no-charge items recorded yet.
</p>

<?php endif;?>

</div>


<?php if(($_GET['update_mode_saved'] ?? '')==='1'):?>
<div class="card notice-good"><b>✓ CUSTOMER COMMUNICATION SETTING SAVED.</b></div>
<?php endif;?>

<div class="card">
<h2>Customer communication</h2>
<p class="small">Choose how proactively this customer is updated. Full transparency is the default and sends a useful SMS whenever job activity starts or pauses/stops.</p>

<form method="post" action="../../api/work/save_update_mode.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <div class="field wide">
        <label>Automatic update level</label>
        <select name="customer_update_mode">
            <?php foreach($customerUpdateModeLabels as $value=>$label):?>
            <option value="<?=wt_html($value)?>" <?=($job['customer_update_mode']??'full_transparency')===$value?'selected':''?>>
                <?=wt_html($label)?>
            </option>
            <?php endforeach;?>
        </select>
    </div>
    <button class="btn">Save communication setting</button>
</div>
</form>

<p class="small" style="margin-top:10px">
<b>Full transparency:</b> Start and Stop/Pause messages are sent automatically with location, activity, reason, ETA and today's anticipated finish where available.<br>
<b>Important only:</b> only material changes/delays are sent automatically.<br>
<b>Daily only:</b> session changes remain in the live record but are not individually texted.<br>
<b>None:</b> no automatic session SMS.
</p>

<h3 style="margin-top:22px">SMS history</h3>
<p class="small">Latest customer SMS activity for this job. Gateway acceptance means the SMS provider accepted the message for sending; it does not by itself prove handset delivery.</p>

<?php if(!$smsMessages):?>
<p class="small">No SMS messages recorded for this job yet.</p>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:8px;margin-top:10px">
<?php foreach($smsMessages as $sm):
    $direction = strtolower((string)($sm['direction'] ?? 'outbound'));
    $status = trim((string)($sm['provider_status'] ?? $sm['status'] ?? ''));
    $purpose = trim((string)($sm['purpose'] ?? ''));
    $created = !empty($sm['created_at']) ? date('j M Y, g:i a', strtotime($sm['created_at'])) : '';
?>
<div style="border:1px solid #dfe5e9;border-radius:10px;padding:11px 13px;background:<?=$direction==='inbound'?'#eef8ff':'#f8fafb'?>">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <b><?=$direction==='inbound'?'← Incoming SMS':'→ Outgoing SMS'?></b>
        <span class="small"><?=wt_html($created)?></span>
    </div>
    <?php if($purpose!=='' || $status!==''):?>
    <div class="small" style="margin-top:4px">
        <?php if($purpose!==''):?><?=wt_html(ucwords(str_replace('_',' ',$purpose)))?><?php endif;?>
        <?php if($purpose!=='' && $status!==''):?> · <?php endif;?>
        <?php if($status!==''):?><b><?=wt_html(ucwords(str_replace('_',' ',$status)))?></b><?php endif;?>
    </div>
    <?php endif;?>
    <div style="margin-top:7px;white-space:pre-wrap"><?=wt_html((string)($sm['message'] ?? ''))?></div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>

</div>

<div class="card">
<h2>Pricing &amp; agreement</h2>

<?php if(isset($_GET['pricing_saved'])):?><p style="color:#087830"><b>✓ Pricing settings saved.</b></p><?php endif;?>

<form method="post" action="../../api/work/update_pricing.php">
<input type="hidden" name="job_id" value="<?=$id?>">

<div class="row">
    <div class="field">
        <label>Mike's agreed hourly rate $</label>
        <input
            name="agreed_hourly_rate"
            type="number"
            min="0"
            step=".01"
            value="<?=wt_html((string)($job['agreed_hourly_rate'] ?? ''))?>"
            placeholder="e.g. 85.00">
    </div>

    <div class="field">
        <label>Payment mode</label>
        <select name="payment_mode">
            <?php foreach([
                'daily'=>'Daily progress payments',
                'balance_limit'=>'Unpaid-balance limit',
                'completion'=>'Payment on completion',
                'milestone'=>'Milestones'
            ] as $value=>$label):?>
            <option value="<?=$value?>" <?=($job['payment_mode']??'completion')===$value?'selected':''?>>
                <?=wt_html($label)?>
            </option>
            <?php endforeach;?>
        </select>
    </div>

    <div class="field">
        <label>Maximum unpaid balance $</label>
        <input
            name="unpaid_balance_limit"
            type="number"
            min="0"
            step=".01"
            value="<?=wt_html((string)($job['unpaid_balance_limit'] ?? ''))?>"
            placeholder="Optional">
    </div>
</div>

<p class="small">Saving these settings does not send the customer an SMS. Check them before sending the agreement.</p>
<button class="btn">SAVE PRICING</button>
</form>

<hr style="margin:20px 0;border:0;border-top:1px solid #dfe5e9">

<p><a target="_blank" href="<?=wt_html($url)?>">Open customer job page</a></p>

<div class="row">
<form method="post" action="../../api/work/send_sms.php">
    <input type="hidden" name="job_id" value="<?=$id?>">
    <input type="hidden" name="kind" value="agreement">
    <button class="btn sms">SMS agreement/report link</button>
</form>
</div>

<p>Status: <b><?=wt_html($job['status'])?></b></p>
</div>

<div class="card">
<h2>Workers</h2>
<form method="post" action="../../api/work/add_worker.php" class="row">
    <input type="hidden" name="job_id" value="<?=$id?>">
    <input name="worker_name" placeholder="Worker name" required>
    <input name="hourly_rate" type="number" step=".01" placeholder="$ / hour" required>
    <button class="btn">Add worker</button>
</form>
<?php foreach($workers as $w):?>
<p><?=wt_html($w['worker_name'])?> — <?=wt_money((float)$w['hourly_rate'])?>/hr</p>
<?php endforeach;?>
</div>

<div class="card">
<h2>Where this job came from</h2>
<form method="post" action="../../api/work/save_job_source.php"><input type="hidden" name="job_id" value="<?=$id?>">
<div class="row"><div class="field"><label>Original contact source</label><select name="job_source"><option value="">Not recorded</option><?php foreach($jobSourceLabels as $k=>$v):?><option value="<?=wt_html($k)?>" <?=($job['job_source']??'')===$k?'selected':''?>><?=wt_html($v)?></option><?php endforeach;?></select></div><div class="field wide"><label>Source detail</label><input name="job_source_detail" value="<?=wt_html($job['job_source_detail']??'')?>" placeholder="e.g. WhatsApp chat with Vivek / referred by John"></div></div>
<div class="field wide"><label>Original contact / booking notes</label><textarea name="original_contact_notes" placeholder="Enough detail to find the original conversation later"><?=wt_html($job['original_contact_notes']??'')?></textarea></div>
<button class="btn">SAVE SOURCE</button></form></div>

<div class="card" id="tasks">

<section
    class="card"
    id="customer-approvals"
    style="border:2px solid #d6dde3"
>
    <h2>Customer approvals</h2>

    <?php if ($approvalFlash): ?>
        <div
            class="<?=!empty($approvalFlash['ok'])
                ? 'status-good'
                : 'status-warn'?>"
            style="margin:10px 0"
        >
            <?=wt_html(
                (string)$approvalFlash['message']
            )?>
        </div>
    <?php endif; ?>

    <?php if ($approvalSummary['holds'] > 0): ?>
        <div
            style="
                background:#fff1cf;
                border:2px solid #d49a19;
                border-radius:10px;
                padding:12px;
                margin:12px 0;
                font-weight:900
            "
        >
            ⚠ CUSTOMER APPROVAL REQUIRED BEFORE
            CONTINUING SOME WORK

            <div
                class="small"
                style="margin-top:5px;font-weight:600"
            >
                <?=$approvalSummary['holds']?>
                approval-required
                item<?=$approvalSummary['holds']===1?'':'s'?>
                currently remain<?=$approvalSummary['holds']===1?'s':''?>
                on hold.
            </div>
        </div>
    <?php endif; ?>

    <div
        class="task-summary-grid"
        style="margin-bottom:15px"
    >
        <div class="task-summary-box">
            <span class="n">
                <?=$approvalSummary['approved']?>
            </span>
            Approved
        </div>

        <div class="task-summary-box">
            <span class="n">
                <?=$approvalSummary['awaiting']?>
            </span>
            Awaiting
        </div>

        <div class="task-summary-box">
            <span class="n">
                <?=$approvalSummary['snoozed']?>
            </span>
            Snoozed
        </div>

        <div class="task-summary-box">
            <span class="n">
                <?=$approvalSummary['declined']
                    + $approvalSummary['expired']?>
            </span>
            Declined / expired
        </div>
    </div>


    <?php if ($approvalRequests): ?>

        <?php foreach ($approvalRequests as $ar): ?>

            <?php
            $arStatus =
                (string)$ar['status'];

            $arBlocks =
                wt_approval_blocks_work($ar);
            ?>

            <div
                class="task-detail-box"
                style="<?=$arBlocks
                    ? 'border:2px solid #d49a19;background:#fffaf0'
                    : ''?>"
            >
                <div
                    style="
                        display:flex;
                        justify-content:space-between;
                        gap:12px;
                        flex-wrap:wrap
                    "
                >
                    <div>
                        <strong>
                            #<?=(int)$ar['id']?>
                            —
                            <?=wt_html(
                                (string)$ar['subject']
                            )?>
                        </strong>

                        <div class="small">
                            <?=wt_html(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        (string)$ar['request_type']
                                    )
                                )
                            )?>
                            ·
                            <?=wt_html(
                                wt_approval_status_label(
                                    $arStatus
                                )
                            )?>
                        </div>
                    </div>

                    <div>
                        <?php if ($arStatus === 'approved'): ?>
                            <span class="status-good">
                                ✓ APPROVED
                            </span>
                        <?php elseif ($arStatus === 'snoozed'): ?>
                            <span class="status-warn">
                                💤 SNOOZED
                            </span>
                        <?php elseif ($arStatus === 'awaiting'): ?>
                            <span class="status-warn">
                                ⏳ AWAITING CUSTOMER
                            </span>
                        <?php elseif ($arStatus === 'declined'): ?>
                            <span class="status-warn">
                                ✕ DECLINED
                            </span>
                        <?php elseif ($arStatus === 'expired'): ?>
                            <span class="status-warn">
                                ⚠ EXPIRED
                            </span>
                        <?php else: ?>
                            <span class="source-badge">
                                <?=wt_html(
                                    strtoupper($arStatus)
                                )?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <p>
                    <?=nl2br(
                        wt_html(
                            (string)$ar['request_text']
                        )
                    )?>
                </p>

                <?php if (
                    $ar['estimated_hours_low'] !== null
                    || $ar['estimated_hours_high'] !== null
                    || $ar['estimated_amount_low'] !== null
                    || $ar['estimated_amount_high'] !== null
                ): ?>
                    <div class="small">
                        <?php if (
                            $ar['estimated_hours_low'] !== null
                            || $ar['estimated_hours_high'] !== null
                        ): ?>
                            <b>Labour:</b>
                            <?=wt_html(
                                (string)(
                                    $ar['estimated_hours_low']
                                    ?? '?'
                                )
                            )?>
                            –
                            <?=wt_html(
                                (string)(
                                    $ar['estimated_hours_high']
                                    ?? '?'
                                )
                            )?>
                            h
                        <?php endif; ?>

                        <?php if (
                            $ar['estimated_amount_low'] !== null
                            || $ar['estimated_amount_high'] !== null
                        ): ?>
                            &nbsp;
                            <b>Amount:</b>
                            <?=wt_money(
                                (float)(
                                    $ar['estimated_amount_low']
                                    ?? $ar['estimated_amount_high']
                                    ?? 0
                                )
                            )?>
                            –
                            <?=wt_money(
                                (float)(
                                    $ar['estimated_amount_high']
                                    ?? $ar['estimated_amount_low']
                                    ?? 0
                                )
                            )?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($arBlocks): ?>
                    <p
                        style="
                            font-weight:900;
                            color:#8a5c00
                        "
                    >
                        ⚠ Affected work remains on hold.
                    </p>
                <?php endif; ?>

                <?php if (!empty($ar['approved_at'])): ?>
                    <div class="small">
                        Approved
                        <?=wt_html(
                            date(
                                'j M Y, g:i a',
                                strtotime(
                                    (string)$ar['approved_at']
                                )
                            )
                        )?>
                        via
                        <?=wt_html(
                            strtoupper(
                                (string)(
                                    $ar['response_source']
                                    ?? 'unknown'
                                )
                            )
                        )?>.
                    </div>
                <?php endif; ?>

                <?php if (!empty($ar['snoozed_until'])): ?>
                    <div class="small">
                        Snoozed until
                        <?=wt_html(
                            date(
                                'j M Y, g:i a',
                                strtotime(
                                    (string)$ar['snoozed_until']
                                )
                            )
                        )?>.
                    </div>
                <?php endif; ?>

                <?php if (!empty($ar['next_reminder_at'])): ?>
                    <div class="small">
                        Next reminder currently scheduled for
                        <?=wt_html(
                            date(
                                'j M Y, g:i a',
                                strtotime(
                                    (string)$ar['next_reminder_at']
                                )
                            )
                        )?>.
                        <b>
                            Automatic reminder sending is not
                            enabled yet.
                        </b>
                    </div>
                <?php endif; ?>
            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p class="small">
            No customer approval requests have been created
            for this job yet.
        </p>

    <?php endif; ?>


    <details
        class="task-detail-box"
        style="margin-top:16px"
    >
        <summary>
            ➕ Create customer approval / acknowledgement
        </summary>

        <form
            method="post"
            action="../../api/work/create_approval_request.php"
            style="margin-top:14px"
        >
            <input
                type="hidden"
                name="job_id"
                value="<?=$id?>"
            >

            <div class="task-grid">

                <div class="field">
                    <label>Request type</label>

                    <select name="request_type">
                        <option value="additional_work">
                            Additional work
                        </option>

                        <option value="variation">
                            Variation
                        </option>

                        <option value="acknowledgement">
                            Acknowledgement only
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label>Related task</label>

                    <select name="task_id">
                        <option value="">
                            Whole job / not assigned
                        </option>

                        <?php foreach ($tasks as $t): ?>
                            <option
                                value="<?=(int)$t['id']?>"
                            >
                                <?=wt_html(
                                    (string)$t['title']
                                )?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Urgency</label>

                    <select name="urgency">
                        <option value="normal">
                            Normal
                        </option>

                        <option value="time_sensitive">
                            Time-sensitive / onsite
                        </option>
                    </select>
                </div>

            </div>


            <div class="field">
                <label>Short heading</label>

                <input
                    name="subject"
                    maxlength="255"
                    required
                    placeholder="e.g. Replace rotten fascia section"
                >
            </div>


            <div class="field">
                <label>
                    Exact work / information being sent
                </label>

                <textarea
                    name="request_text"
                    required
                    placeholder="Describe exactly what has changed, what work is proposed and why."
                ></textarea>
            </div>


            <div
                style="
                    background:#eef6ff;
                    border:1px solid #c9dff5;
                    border-radius:10px;
                    padding:11px 12px;
                    margin:12px 0;
                "
            >
                <b>Pricing for additional work</b>

                <div class="small" style="margin-top:5px">
                    No time estimate is required here.
                    Approved additional work is charged using the
                    normal agreed job rate, plus applicable
                    materials / expenses.
                </div>

                <?php if (
                    isset($job['agreed_hourly_rate'])
                    && (float)$job['agreed_hourly_rate'] > 0
                ): ?>
                    <div style="margin-top:7px">
                        Current agreed rate:
                        <b>
                            <?=wt_money(
                                (float)$job['agreed_hourly_rate']
                            )?>/hr
                        </b>
                    </div>
                <?php endif; ?>
            </div>

            <div class="task-grid">

                <div class="field">
                    <label>Customer authority</label>

                    <select
                        name="approval_required"
                        id="approvalRequired"
                    >
                        <option value="1">
                            Written approval required
                        </option>

                        <option value="0">
                            Information / acknowledgement only
                        </option>
                    </select>

                    <select
                        name="work_hold_required"
                        style="margin-top:7px"
                    >
                        <option value="1">
                            Hold affected work until approved
                        </option>

                        <option value="0">
                            Do not automatically hold work
                        </option>
                    </select>
                </div>

            </div>

            <div
                style="
                    background:#eef6ff;
                    border:1px solid #a7c8eb;
                    border-radius:10px;
                    padding:12px;
                    margin:12px 0
                "
            >
                <b>What happens next</b>

                <div class="small" style="margin-top:5px">
                    The request is saved first, then sent by SMS.
                    For approval-required work the customer can
                    reply <b>YES request-number</b>,
                    <b>NO request-number</b> or
                    <b>SNOOZE request-number 20</b>.
                    A snooze does not authorise the work.
                </div>
            </div>

            <button
                class="btn"
                type="submit"
            >
                👀 PREVIEW APPROVAL MESSAGE
            </button>

            <div class="small" style="margin-top:7px">
                Nothing is sent from this screen.
                The next screen shows the exact SMS for review
                and editing before you send it.
            </div>
        </form>
    </details>
</section>

<h2>Job tasks <?php if($pendingChangeCount):?><span class="source-badge source-retro"><?=$pendingChangeCount?> customer change request<?=$pendingChangeCount===1?'':'s'?> awaiting review</span><?php endif;?></h2>

<?php if(is_array($taskSmsFlash)):?>
<?php
$taskSmsRequested =
    !empty($taskSmsFlash['sms_requested']);

$taskSmsOk =
    !empty($taskSmsFlash['ok']);

$taskFlashBackground =
    !$taskSmsRequested
        ? '#eef6ff'
        : ($taskSmsOk ? '#e7f6ec' : '#fde8e8');

$taskFlashBorder =
    !$taskSmsRequested
        ? '#3973a8'
        : ($taskSmsOk ? '#268447' : '#b42318');
?>
<div style="
    margin:10px 0 14px;
    padding:12px 14px;
    background:<?=$taskFlashBackground?>;
    border:2px solid <?=$taskFlashBorder?>;
    border-radius:10px;
">
    <?php if(!$taskSmsRequested):?>

        <b>✓ Task saved — no SMS sent.</b>

    <?php elseif($taskSmsOk):?>

        <b>✓ Task saved and SMS dispatched.</b>

        <?php if(!empty($taskSmsFlash['message'])):?>
        <div style="
            margin-top:8px;
            background:#fff;
            padding:9px;
            border-radius:7px;
            white-space:pre-wrap;
        "><?=wt_html((string)$taskSmsFlash['message'])?></div>
        <?php endif;?>

    <?php else:?>

        <b>⚠ Task saved, but SMS was not dispatched.</b>

        <?php if(!empty($taskSmsFlash['status'])):?>
        <div class="small" style="margin-top:6px">
            <?=wt_html((string)$taskSmsFlash['status'])?>
        </div>
        <?php endif;?>

    <?php endif;?>
</div>
<?php endif;?>

<p class="small">Break the job into real pieces of work. AI and Mike estimates are kept separately. Tracked time is calculated from sessions linked to each task.</p>
<div style="margin:10px 0"><b>Approximate progress: <?=$taskProgress['percent']?>%</b><div class="progressbar"><div class="progressfill" style="width:<?=$taskProgress['percent']?>%"></div></div></div>
<?php if(!$tasks):?><p>No tasks yet.</p><?php endif;?>
<?php foreach($tasks as $t): $cls='task-card '.(($t['status']==='completed')?'task-completed':(($t['status']==='blocked')?'task-blocked':(($t['status']==='cancelled')?'task-cancelled':'')));?>
<div class="card <?=$cls?>" id="task-<?=$t['id']?>"><form method="post" action="../../api/work/update_task.php" enctype="multipart/form-data"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="task_id" value="<?=$t['id']?>">
<div class="task-grid"><div class="field"><label>Task</label><input name="title" value="<?=wt_html($t['title'])?>" required></div><div class="field"><label>Status</label><select name="status"><?php foreach(['not_started'=>'Not started','in_progress'=>'In progress','blocked'=>'Blocked','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$v):?><option value="<?=$k?>" <?=$t['status']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="field"><label>Origin</label><select name="task_origin"><?php foreach(['original'=>'Original scope','customer_requested'=>'Customer requested','mike_added'=>'Mike added','ai_suggested'=>'AI suggested','unforeseen'=>'Unforeseen / discovered'] as $k=>$v):?><option value="<?=$k?>" <?=$t['task_origin']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div></div>
<div class="field"><label>Description / scope notes</label><textarea name="description"><?=wt_html($t['description']??'')?></textarea></div>
<details class="task-detail-box"><summary>Customer explanation &amp; granular task detail</summary>
<div class="field"><label>Customer summary — simple explanation of this task</label><textarea name="customer_summary" placeholder="e.g. Rebuild rotten timber so the window can be safely refitted and operate correctly."><?=wt_html($t['customer_summary']??'')?></textarea></div>
<div class="field"><label>What is involved — granular procedure</label><textarea name="detailed_procedure" placeholder="List the actual stages that may be required, preferably one step per line."><?=wt_html($t['detailed_procedure']??'')?></textarea></div>
<div class="field"><label>Why this can take time / variables</label><textarea name="time_drivers" placeholder="e.g. hidden rot, access, repeated shaping, weather, previous repairs, adjustment/testing."><?=wt_html($t['time_drivers']??'')?></textarea></div>
<div class="field"><label>Waiting / drying / curing notes</label><textarea name="waiting_curing_notes" placeholder="Explain elapsed waiting time separately from billable labour, e.g. timber drying, filler curing, primer/paint drying."><?=wt_html($t['waiting_curing_notes']??'')?></textarea></div>
</details>
<details class="task-detail-box task-materials"><summary>Suggested materials &amp; consumables</summary>
<div class="field"><label>Suggested materials — one item per line</label><textarea name="suggested_materials" placeholder="Builders Bog
Wood putty
Sandpaper / sanding discs
Primer / undercoat
Top coat paint
Replacement seals if required"><?=wt_html($t['suggested_materials']??'')?></textarea></div>
<p class="small">This is a planning list, not a statement that every item was actually used. Actual purchases/use can be recorded separately later.</p>
</details>
<div class="task-grid"><div class="field"><label>AI estimate low (h)</label><input type="number" step="0.1" min="0" name="ai_estimate_low" value="<?=wt_html((string)($t['ai_estimate_low']??''))?>"></div><div class="field"><label>AI estimate high (h)</label><input type="number" step="0.1" min="0" name="ai_estimate_high" value="<?=wt_html((string)($t['ai_estimate_high']??''))?>"></div><div class="field"><label>Tracked actual</label><input value="<?=number_format((float)$t['tracked_hours'],2)?> h" disabled></div></div>
<div class="field"><label>AI reasoning</label><textarea name="ai_reasoning"><?=wt_html($t['ai_reasoning']??'')?></textarea></div>
<div class="task-grid"><div class="field"><label>Mike estimate low (h)</label><input type="number" step="0.1" min="0" name="mike_estimate_low" value="<?=wt_html((string)($t['mike_estimate_low']??''))?>"></div><div class="field"><label>Mike estimate high (h)</label><input type="number" step="0.1" min="0" name="mike_estimate_high" value="<?=wt_html((string)($t['mike_estimate_high']??''))?>"></div><div class="field"><label>Mike best actual guess (optional)</label><input type="number" step="0.1" min="0" name="actual_adjusted_hours" value="<?=wt_html((string)($t['actual_adjusted_hours']??''))?>"></div></div>
<div class="field"><label>Mike estimate reasoning</label><textarea name="mike_reasoning"><?=wt_html($t['mike_reasoning']??'')?></textarea></div><div class="field"><label>Actual-time reasoning / why it differed</label><textarea name="actual_reasoning"><?=wt_html($t['actual_reasoning']??'')?></textarea></div>
<p class="checkline"><input type="checkbox" name="customer_visible" value="1" <?=$t['customer_visible']?'checked':''?>> Visible to customer</p>

<div class="task-detail-box" style="border:2px solid #3973a8;background:#eef6ff">
<b>📷 Add photos with this task update</b>
<p class="small" style="margin:6px 0 10px">Optional. Add up to 8 photos whenever you save progress, mark the task waiting, or complete it. They stay attached to this task.</p>
<input type="file" name="progress_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
<div class="field" style="margin-top:9px"><label>Photo note (optional)</label><input name="progress_photo_note" placeholder="e.g. second coat applied; ready for sanding after curing"></div>
</div>

<?php
$taskStatusLabels = [
    'not_started' => 'not started',
    'in_progress' => 'in progress',
    'blocked' => 'blocked',
    'completed' => 'completed',
    'cancelled' => 'cancelled',
];

$defaultTaskSms =
    'Mike of All Trades update: "' .
    (string)$t['title'] .
    '" is now ' .
    ($taskStatusLabels[$t['status']] ?? $t['status']) .
    '. Your job record has been updated.';
?>

<details class="task-detail-box task-sms-box">
<summary>📱 Customer SMS for this update</summary>

<p class="small" style="margin-top:10px">
Edit this message if required. Use <b>Save only</b> when the customer
does not need an SMS, or <b>Save + SMS customer</b> when you want the
customer notified of this specific update.
</p>

<div class="field">
<label>SMS message</label>
<textarea
    name="customer_sms_message"
    class="task-customer-sms"
    data-auto-sms="1"
    rows="4"
><?=wt_html($defaultTaskSms)?></textarea>
</div>

</details>

<div style="
    display:flex;
    gap:9px;
    flex-wrap:wrap;
    margin-top:12px;
">
    <button
        class="btn"
        type="submit"
        name="save_action"
        value="save_only"
    >
        SAVE ONLY
    </button>

    <button
        class="btn sms"
        type="submit"
        name="save_action"
        value="save_sms"
    >
        📱 SAVE + SMS CUSTOMER
    </button>
</div>

</form>
<?php foreach(($changesByTask[(int)$t['id']]??[]) as $cr):?>
<div class="task-change-admin <?=$cr['status']==='awaiting_review'?'pending':''?>"><div><b>Customer change request</b> <span class="change-pill <?=wt_html($cr['status'])?>"><?=wt_html(ucwords(str_replace('_',' ',$cr['status'])))?></span></div><div class="meta"><?=wt_html(date('j M Y, g:i a',strtotime($cr['created_at'])))?> · <?=wt_html(ucwords(str_replace('_',' ',$cr['request_type'])))?></div><p><?=nl2br(wt_html($cr['customer_message']))?></p>
<form method="post" action="../../api/work/review_task_change.php"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="request_id" value="<?=$cr['id']?>"><div class="task-grid"><div class="field"><label>Decision</label><select name="status"><?php foreach(['accepted'=>'Accept','amended'=>'Accept with amendment','question_sent'=>'Ask / clarify','declined'=>'Decline'] as $k=>$v):?><option value="<?=$k?>" <?=$cr['status']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="field"><label>Effect on estimate</label><select name="affects_estimate"><option value="unknown" <?=$cr['affects_estimate']==='unknown'?'selected':''?>>Not assessed</option><option value="no" <?=$cr['affects_estimate']==='no'?'selected':''?>>No material effect</option><option value="yes" <?=$cr['affects_estimate']==='yes'?'selected':''?>>May affect time/cost</option></select></div><div class="field"><label>Approx. extra hours low / high</label><div class="row"><input type="number" step="0.1" min="0" name="estimate_delta_low" value="<?=wt_html((string)($cr['estimate_delta_low']??''))?>"><input type="number" step="0.1" min="0" name="estimate_delta_high" value="<?=wt_html((string)($cr['estimate_delta_high']??''))?>"></div></div></div><div class="field"><label>Mike response / clarification</label><textarea name="mike_response" placeholder="Explain what has been accepted, changed, needs clarification, or why it cannot be done as requested."><?=wt_html($cr['mike_response']??'')?></textarea></div><button class="btn">SAVE RESPONSE &amp; NOTIFY CUSTOMER</button></form>
<?php if(in_array($cr['status'],['accepted','amended'],true)):?><p class="small"><b>Reminder:</b> if this request changes the actual task instructions, update the task fields above as well. The original customer request remains preserved here.</p><?php endif;?></div>
<?php endforeach;?>
</div><?php endforeach;?>
<details><summary><b>＋ Add another task</b></summary><form method="post" action="../../api/work/add_task.php" style="margin-top:12px"><input type="hidden" name="job_id" value="<?=$id?>"><div class="task-grid"><div class="field"><label>Task title</label><input name="title" required placeholder="e.g. Repair rotten window sill"></div><div class="field"><label>Origin</label><select name="task_origin"><option value="original">Original scope</option><option value="customer_requested">Customer requested</option><option value="mike_added" selected>Mike added</option><option value="ai_suggested">AI suggested</option><option value="unforeseen">Unforeseen / discovered</option></select></div><div class="field"><label>Customer visibility</label><label class="checkline"><input type="checkbox" name="customer_visible" value="1" checked> Show customer</label></div></div><div class="field"><label>Description</label><textarea name="description"></textarea></div>
<details class="task-detail-box"><summary>Customer explanation &amp; granular task detail</summary>
<div class="field"><label>Customer summary</label><textarea name="customer_summary" placeholder="Short plain-English explanation for the customer"></textarea></div>
<div class="field"><label>What is involved — granular procedure</label><textarea name="detailed_procedure" placeholder="One stage per line"></textarea></div>
<div class="field"><label>Why this can take time / variables</label><textarea name="time_drivers"></textarea></div>
<div class="field"><label>Waiting / drying / curing notes</label><textarea name="waiting_curing_notes"></textarea></div>
</details>
<details class="task-detail-box task-materials"><summary>Suggested materials &amp; consumables</summary><div class="field"><label>One item per line</label><textarea name="suggested_materials"></textarea></div></details>
<div class="task-grid"><div class="field"><label>AI estimate low (h)</label><input type="number" step="0.1" min="0" name="ai_estimate_low"></div><div class="field"><label>AI estimate high (h)</label><input type="number" step="0.1" min="0" name="ai_estimate_high"></div><div class="field"><label>Mike estimate low/high (h)</label><div class="row"><input type="number" step="0.1" min="0" name="mike_estimate_low" placeholder="low"><input type="number" step="0.1" min="0" name="mike_estimate_high" placeholder="high"></div></div></div><div class="field"><label>AI reasoning</label><textarea name="ai_reasoning"></textarea></div><div class="field"><label>Mike reasoning</label><textarea name="mike_reasoning"></textarea></div><button class="btn start">ADD TASK</button></form></details></div>

<div class="card travel-card">
<h2>🚗 On my way to customer</h2>
<p class="small">Use this when leaving for the customer's premises. It starts a separate live travel session, records the ETA, and sends an SMS under Full transparency / Important only.</p>
<form method="post" action="../../api/work/on_my_way.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <div class="field">
        <label>Worker</label>
        <select name="worker_id" required>
            <option value="mike" <?=isset($runningWorkerKeys['mike'])?'disabled':''?>>Mike<?=isset($runningWorkerKeys['mike'])?' — RUNNING':''?></option>
            <?php foreach($workers as $w): $wr=isset($runningWorkerKeys['worker_'.$w['id']]); ?>
            <option value="<?=$w['id']?>" <?=$wr?'disabled':''?>><?=wt_html($w['worker_name'])?><?=$wr?' — RUNNING':''?></option>
            <?php endforeach;?>
        </select>
    </div>
    <div class="field"><label>Leaving from (optional)</label><input name="origin" placeholder="e.g. Home / Bunnings Pakenham"></div>
    <div class="field"><label>ETA in minutes</label><input name="eta_minutes" type="number" min="1" max="240" value="30" required></div>
    <div class="field wide"><label>Travel note (optional)</label><input name="notes" placeholder="e.g. bringing materials collected this morning"></div>
</div>
<p class="checkline">
    <input type="checkbox" name="charge_travel" value="1">
    <b>Charge this travel time</b>
    <span class="small">Use only for CBD / north-of-Yarra / unusual toll or parking jobs.</span>
</p>
<button class="btn sms" style="font-size:18px;padding:15px 22px">🚗 ON MY WAY + START TRAVEL</button>
</form>

<?php foreach($runningSessions as $rs): if(($rs['category']??'')==='travel' && ($rs['start_location']??'')==='travel_job'): ?>
<form method="post" action="../../api/work/arrive_start_work.php" style="margin-top:14px;background:#fff;padding:13px;border-radius:10px;border:1px solid #b9d6ee">
<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="worker_id" value="<?=$rs['worker_id']===null?'mike':(int)$rs['worker_id']?>">
<b>Travel currently running for <?=wt_html($rs['worker_name']?:'Mike')?>.</b>
<?php if(!empty($rs['travel_eta'])):?><span class="small"> ETA was <?=wt_html(date('g:i a',strtotime($rs['travel_eta'])))?>.</span><?php endif;?>
<div class="row" style="margin-top:8px">
    <div class="field"><label>Task</label><select name="task_id"><option value="0">General / not task-specific</option><?php foreach($activeTasks as $t):?><option value="<?=$t['id']?>"><?=wt_html($t['title'])?></option><?php endforeach;?></select></div><div class="field wide"><label>What are you starting on arrival?</label><input name="notes" placeholder="e.g. continue wall preparation and tile removal"></div>
</div>
<button class="btn start">📍 ARRIVED — STOP TRAVEL & START ON-SITE WORK</button>
</form>
<?php endif; endforeach;?>
</div>

<?php
/*
 * V9 WORK / FINANCIAL BREAKDOWN OVERVIEW
 *
 * Read-only overview. This does not alter job records.
 * It deliberately keeps:
 *   - actual recorded time
 *   - customer-billable time
 *   - goodwill
 *   - rectification
 *   - other / unclassified no-charge work
 * visibly separate.
 */

$wbTimeStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            CASE
                WHEN s.ended_at IS NULL THEN 0
                WHEN s.session_source='retrospective'
                     AND s.retrospective_hours IS NOT NULL
                    THEN s.retrospective_hours
                ELSE
                    GREATEST(
                        0,
                        TIMESTAMPDIFF(
                            SECOND,
                            s.started_at,
                            s.ended_at
                        )
                        -
                        COALESCE(
                            (
                                SELECT SUM(
                                    TIMESTAMPDIFF(
                                        SECOND,
                                        b.started_at,
                                        COALESCE(
                                            b.ended_at,
                                            s.ended_at
                                        )
                                    )
                                )
                                FROM work_session_breaks b
                                WHERE b.session_id=s.id
                            ),
                            0
                        )
                    ) / 3600
            END
        ),0) AS actual_hours,

        COALESCE(SUM(
            CASE
                WHEN s.billable=1
                     AND s.ended_at IS NOT NULL
                THEN
                    CASE
                        WHEN s.session_source='retrospective'
                             AND s.retrospective_hours IS NOT NULL
                            THEN s.retrospective_hours
                        ELSE
                            GREATEST(
                                0,
                                TIMESTAMPDIFF(
                                    SECOND,
                                    s.started_at,
                                    s.ended_at
                                )
                                -
                                COALESCE(
                                    (
                                        SELECT SUM(
                                            TIMESTAMPDIFF(
                                                SECOND,
                                                b2.started_at,
                                                COALESCE(
                                                    b2.ended_at,
                                                    s.ended_at
                                                )
                                            )
                                        )
                                        FROM work_session_breaks b2
                                        WHERE b2.session_id=s.id
                                    ),
                                    0
                                )
                            ) / 3600
                    END
                ELSE 0
            END
        ),0) AS billable_hours,

        COALESCE(SUM(
            CASE
                WHEN s.billable=0
                     AND s.ended_at IS NOT NULL
                THEN
                    CASE
                        WHEN s.session_source='retrospective'
                             AND s.retrospective_hours IS NOT NULL
                            THEN s.retrospective_hours
                        ELSE
                            GREATEST(
                                0,
                                TIMESTAMPDIFF(
                                    SECOND,
                                    s.started_at,
                                    s.ended_at
                                )
                                -
                                COALESCE(
                                    (
                                        SELECT SUM(
                                            TIMESTAMPDIFF(
                                                SECOND,
                                                b3.started_at,
                                                COALESCE(
                                                    b3.ended_at,
                                                    s.ended_at
                                                )
                                            )
                                        )
                                        FROM work_session_breaks b3
                                        WHERE b3.session_id=s.id
                                    ),
                                    0
                                )
                            ) / 3600
                    END
                ELSE 0
            END
        ),0) AS nonbillable_session_hours
    FROM work_sessions s
    WHERE s.job_id=?
");

$wbTimeStmt->execute([$id]);
$wbTime = $wbTimeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$wbActualHours =
    round((float)($wbTime['actual_hours'] ?? 0), 2);

$wbBillableHours =
    round((float)($wbTime['billable_hours'] ?? 0), 2);

$wbNonbillableSessionHours =
    round((float)($wbTime['nonbillable_session_hours'] ?? 0), 2);


$wbNoChargeStmt = $pdo->prepare("
    SELECT *
    FROM work_complimentary_items
    WHERE job_id=?
    ORDER BY
        CASE no_charge_reason
            WHEN 'unclassified' THEN 0
            WHEN 'rectification' THEN 1
            WHEN 'goodwill' THEN 2
            ELSE 3
        END,
        id
");

$wbNoChargeStmt->execute([$id]);

$wbNoChargeItems =
    $wbNoChargeStmt->fetchAll(PDO::FETCH_ASSOC);


$wbGroups = [
    'goodwill' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'items' => [],
    ],
    'rectification' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'items' => [],
    ],
    'other' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'items' => [],
    ],
    'unclassified' => [
        'hours' => 0.0,
        'labour' => 0.0,
        'materials' => 0.0,
        'unallocated' => 0.0,
        'items' => [],
    ],
];

foreach ($wbNoChargeItems as $wbItem) {

    $wbReason =
        (string)($wbItem['no_charge_reason'] ?? 'unclassified');

    if (!isset($wbGroups[$wbReason])) {
        $wbReason = 'other';
    }

    $wbHours =
        (float)($wbItem['labour_hours'] ?? 0);

    $wbLabour =
        (float)($wbItem['labour_value'] ?? 0);

    $wbMaterials =
        (float)($wbItem['material_value'] ?? 0);

    $wbLegacy =
        (float)($wbItem['estimated_value'] ?? 0);

    $wbAllocated =
        $wbLabour + $wbMaterials;

    $wbUnallocated =
        max(0, $wbLegacy - $wbAllocated);

    $wbGroups[$wbReason]['hours'] += $wbHours;
    $wbGroups[$wbReason]['labour'] += $wbLabour;
    $wbGroups[$wbReason]['materials'] += $wbMaterials;
    $wbGroups[$wbReason]['unallocated'] += $wbUnallocated;
    $wbGroups[$wbReason]['items'][] = $wbItem;
}

foreach ($wbGroups as &$wbGroup) {
    $wbGroup['hours'] =
        round($wbGroup['hours'], 2);

    $wbGroup['labour'] =
        round($wbGroup['labour'], 2);

    $wbGroup['materials'] =
        round($wbGroup['materials'], 2);

    $wbGroup['unallocated'] =
        round($wbGroup['unallocated'], 2);

    $wbGroup['total'] =
        round(
            $wbGroup['labour']
            +
            $wbGroup['materials']
            +
            $wbGroup['unallocated'],
            2
        );
}
unset($wbGroup);

$wbClassifiedNoChargeHours =
    $wbGroups['goodwill']['hours']
    +
    $wbGroups['rectification']['hours']
    +
    $wbGroups['other']['hours'];

$wbUnclassifiedHours =
    max(
        0,
        $wbNonbillableSessionHours
        -
        $wbClassifiedNoChargeHours
    );

$wbNeedsAttention =
    count($wbGroups['unclassified']['items'])
    +
    ($wbUnclassifiedHours > 0.01 ? 1 : 0);

function wb_hours(float $hours): string {
    return number_format($hours, 2);
}
?>

<div
    class="card"
    id="work-breakdown-overview"
    style="
        border:2px solid #44515d;
        background:#f8fafb;
    "
>

<h2 style="margin-bottom:4px">
    📊 Job work &amp; financial breakdown
</h2>

<p class="small" style="margin-top:0">
    One place to see what has already been classified.
    <b>Actual time worked stays separate from what the customer is charged.</b>
</p>

<?php if($wbNeedsAttention > 0):?>
<div
    style="
        background:#fff3cd;
        border:1px solid #e1c15b;
        padding:12px;
        border-radius:10px;
        margin:12px 0;
    "
>
    <b>⚠ Needs review:</b>

    <?php if(count($wbGroups['unclassified']['items']) > 0):?>
        <?=count($wbGroups['unclassified']['items'])?>
        old no-charge
        <?=count($wbGroups['unclassified']['items'])===1?'item':'items'?>
        still
        <?=count($wbGroups['unclassified']['items'])===1?'has':'have'?>
        no goodwill / rectification classification.
    <?php endif;?>

    <?php if($wbUnclassifiedHours > 0.01):?>
        There are also approximately
        <b><?=wb_hours($wbUnclassifiedHours)?> hrs</b>
        of non-billable session time not yet matched to a classified
        no-charge labour record.
    <?php endif;?>
</div>
<?php endif;?>


<div
    style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
        gap:10px;
        margin:14px 0;
    "
>

    <div
        style="
            background:#eef3f7;
            border:1px solid #c8d2da;
            border-radius:10px;
            padding:13px;
        "
    >
        <div class="small"><b>ACTUAL JOB TIME</b></div>
        <div style="font-size:25px;font-weight:900">
            <?=wb_hours($wbActualHours)?> hrs
        </div>
        <div class="small">
            All completed recorded work
        </div>
    </div>


    <div
        style="
            background:#edf8f0;
            border:1px solid #abd2b3;
            border-radius:10px;
            padding:13px;
        "
    >
        <div class="small"><b>💵 BILLABLE / PAID</b></div>
        <div style="font-size:25px;font-weight:900">
            <?=wb_hours($wbBillableHours)?> hrs
        </div>
        <div class="small">
            Customer-chargeable recorded time
        </div>
    </div>


    <div
        style="
            background:#eef6ff;
            border:1px solid #a8c9e8;
            border-radius:10px;
            padding:13px;
        "
    >
        <div class="small"><b>🎁 GOODWILL / FREE EXTRA</b></div>
        <div style="font-size:25px;font-weight:900">
            <?=wb_hours($wbGroups['goodwill']['hours'])?> hrs
        </div>

        <div class="small">
            Labour <?=wt_money($wbGroups['goodwill']['labour'])?><br>
            Materials <?=wt_money($wbGroups['goodwill']['materials'])?><br>
            <b>Total <?=wt_money($wbGroups['goodwill']['total'])?></b>
        </div>
    </div>


    <div
        style="
            background:#fff0f0;
            border:1px solid #e4aaaa;
            border-radius:10px;
            padding:13px;
        "
    >
        <div class="small"><b>🛠 RECTIFICATION / REWORK</b></div>
        <div style="font-size:25px;font-weight:900">
            <?=wb_hours($wbGroups['rectification']['hours'])?> hrs
        </div>

        <div class="small">
            Labour <?=wt_money($wbGroups['rectification']['labour'])?><br>
            Materials <?=wt_money($wbGroups['rectification']['materials'])?><br>
            <b>Total <?=wt_money($wbGroups['rectification']['total'])?></b>
        </div>
    </div>


    <div
        style="
            background:#f7f2ff;
            border:1px solid #cab7e1;
            border-radius:10px;
            padding:13px;
        "
    >
        <div class="small"><b>OTHER NO-CHARGE</b></div>
        <div style="font-size:25px;font-weight:900">
            <?=wb_hours($wbGroups['other']['hours'])?> hrs
        </div>

        <div class="small">
            Total <?=wt_money($wbGroups['other']['total'])?>
        </div>
    </div>


    <div
        style="
            background:#fff8df;
            border:1px solid #e2c760;
            border-radius:10px;
            padding:13px;
        "
    >
        <div class="small"><b>⚠ UNCLASSIFIED</b></div>

        <div style="font-size:25px;font-weight:900">
            <?=count($wbGroups['unclassified']['items'])?>
            <?=count($wbGroups['unclassified']['items'])===1?'item':'items'?>
        </div>

        <div class="small">
            <?=$wbUnclassifiedHours > 0.01
                ? wb_hours($wbUnclassifiedHours).' hrs unmatched'
                : 'No unmatched session hours'?>
        </div>
    </div>

</div>


<details open style="margin-top:14px">
<summary style="cursor:pointer;font-weight:900">
    🎁 Goodwill / free-extra records
    (<?=count($wbGroups['goodwill']['items'])?>)
</summary>

<?php if(!$wbGroups['goodwill']['items']):?>
<p class="small">No goodwill records classified yet.</p>
<?php endif;?>

<?php foreach($wbGroups['goodwill']['items'] as $wbItem):?>
<div
    style="
        border-top:1px solid #d8e2e8;
        padding:10px 0;
    "
>
    <b>#<?=(int)$wbItem['id']?> —
        <?=wt_html((string)$wbItem['description'])?>
    </b>

    <div class="small">
        <?=number_format((float)($wbItem['labour_hours'] ?? 0),2)?> hrs
        · labour <?=wt_money((float)($wbItem['labour_value'] ?? 0))?>
        · materials <?=wt_money((float)($wbItem['material_value'] ?? 0))?>
    </div>

    <?php if(!empty($wbItem['note'])):?>
    <div class="small">
        <?=wt_html((string)$wbItem['note'])?>
    </div>
    <?php endif;?>

    <a
        href="#complimentary-<?=(int)$wbItem['id']?>"
        style="font-weight:800"
    >
        Edit this record ↓
    </a>
</div>
<?php endforeach;?>
</details>


<details style="margin-top:12px">
<summary style="cursor:pointer;font-weight:900">
    🛠 Rectification / rework records
    (<?=count($wbGroups['rectification']['items'])?>)
</summary>

<?php if(!$wbGroups['rectification']['items']):?>
<p class="small">No rectification records classified yet.</p>
<?php endif;?>

<?php foreach($wbGroups['rectification']['items'] as $wbItem):?>
<div
    style="
        border-top:1px solid #ead1d1;
        padding:10px 0;
    "
>
    <b>#<?=(int)$wbItem['id']?> —
        <?=wt_html((string)$wbItem['description'])?>
    </b>

    <div class="small">
        <?=number_format((float)($wbItem['labour_hours'] ?? 0),2)?> hrs
        · labour <?=wt_money((float)($wbItem['labour_value'] ?? 0))?>
        · materials <?=wt_money((float)($wbItem['material_value'] ?? 0))?>
    </div>

    <?php if(!empty($wbItem['note'])):?>
    <div class="small">
        <?=wt_html((string)$wbItem['note'])?>
    </div>
    <?php endif;?>

    <a
        href="#complimentary-<?=(int)$wbItem['id']?>"
        style="font-weight:800"
    >
        Edit this record ↓
    </a>
</div>
<?php endforeach;?>
</details>


<?php if(
    $wbGroups['other']['items']
    ||
    $wbGroups['unclassified']['items']
):?>

<details
    <?=$wbNeedsAttention > 0 ? 'open' : ''?>
    style="margin-top:12px"
>
<summary style="cursor:pointer;font-weight:900">
    ⚠ Other / unclassified no-charge records
    (
        <?=count($wbGroups['other']['items'])
          + count($wbGroups['unclassified']['items'])?>
    )
</summary>

<?php foreach(
    array_merge(
        $wbGroups['unclassified']['items'],
        $wbGroups['other']['items']
    )
    as $wbItem
):?>

<div
    style="
        border-top:1px solid #e5d69c;
        padding:10px 0;
    "
>
    <?php
    $wbReason =
        (string)($wbItem['no_charge_reason'] ?? 'unclassified');
    ?>

    <b>
        <?=$wbReason==='unclassified' ? '⚠ ' : ''?>
        #<?=(int)$wbItem['id']?> —
        <?=wt_html((string)$wbItem['description'])?>
    </b>

    <div class="small">
        Classification:
        <b><?=wt_html(ucfirst($wbReason))?></b>
        ·
        <?=number_format((float)($wbItem['labour_hours'] ?? 0),2)?> hrs
        · labour <?=wt_money((float)($wbItem['labour_value'] ?? 0))?>
        · materials <?=wt_money((float)($wbItem['material_value'] ?? 0))?>
    </div>

    <a
        href="#complimentary-<?=(int)$wbItem['id']?>"
        style="font-weight:900"
    >
        Review / classify this record ↓
    </a>
</div>

<?php endforeach;?>
</details>

<?php endif;?>


<div
    class="small"
    style="
        margin-top:14px;
        padding-top:10px;
        border-top:1px solid #d7dde2;
    "
>
    <b>How to read this:</b>
    billable time comes from recorded work sessions.
    Goodwill and rectification values come from the separate
    no-charge ledger so they are not added to the customer amount.
    Historical items marked <b>Unclassified</b> still need you to decide
    what they really were.
</div>

</div>

<div class="card retro-card">
<h2>🕘 Add previously completed work</h2>
<p class="small"><b>Retrospective entry.</b> Use this for legitimate work already completed before it was entered into the tracker. For complicated days, simply enter the total job hours after excluding breaks, unrelated calls, errands and other customers. No fake historical SMS is sent.</p>
<form method="post" action="../../api/work/add_retrospective_session.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <div class="field"><label>Worker</label><select name="worker_id" required><option value="mike">Mike / default rate</option><?php foreach($workers as $w):?><option value="<?=$w['id']?>"><?=wt_html($w['worker_name'])?> — <?=wt_money((float)$w['hourly_rate'])?>/hr</option><?php endforeach;?></select></div>
    <div class="field"><label>Task (optional)</label><select name="task_id"><option value="0">General / not task-specific</option><?php foreach($tasks as $t): if($t['status']==='cancelled') continue; ?><option value="<?=$t['id']?>"><?=wt_html($t['title'])?></option><?php endforeach;?></select></div>
    <div class="field"><label>Date work occurred</label><input name="work_date" type="date" max="<?=date('Y-m-d')?>" required></div>
    <div class="field"><label>Total job hours</label><input name="recorded_hours" type="number" min="0.01" max="24" step="0.01" placeholder="e.g. 8.5" required></div>
    <div class="field wide"><label>What was done? / notes</label><textarea name="notes" placeholder="e.g. Removed damaged materials, measured, sourced supplies, preparation and cleanup. Hours exclude lunch and unrelated calls/errands." required></textarea></div>
</div>
<details style="margin:12px 0">
<summary><b>I know the exact start &amp; finish times</b> (optional)</summary>
<div class="row" style="margin-top:10px">
    <div class="field"><label>Start time</label><input name="start_time" type="time"></div>
    <div class="field"><label>Finish time</label><input name="end_time" type="time"></div>
</div>
<p class="small">If both times are entered, they are shown as the known start/finish times. Otherwise only the total job hours are shown.</p>
</details>
<p class="checkline"><input type="checkbox" name="billable" value="1" checked> <b>Billable job time</b></p>
<button class="btn">＋ ADD PAST WORK</button>
</form>
</div>

<div class="card">
<h2>Start job activity</h2>
<p class="small">Record where you are and what you are doing. One active session per worker <b>on this job</b> is allowed, so repeated clicks cannot create duplicate timers. You can still leave this job open or waiting and work on another job concurrently.</p>
<form method="post" action="../../api/work/start_session.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <div class="field">
        <label>Worker</label>
        <select name="worker_id" required>
            <option value="mike" <?=isset($runningWorkerKeys['mike'])?'disabled':''?>>Mike / default rate<?=isset($runningWorkerKeys['mike'])?' — RUNNING':''?></option>
            <?php foreach($workers as $w):
                $workerRunning = isset($runningWorkerKeys['worker_'.$w['id']]);
            ?>
            <option value="<?=$w['id']?>" <?=$workerRunning?'disabled':''?>><?=wt_html($w['worker_name'])?> — <?=wt_money((float)$w['hourly_rate'])?>/hr<?=$workerRunning?' — RUNNING':''?></option>
            <?php endforeach;?>
        </select>
    </div>

    <div class="field"><label>Task (optional)</label><select name="task_id"><option value="0">General / not task-specific</option><?php foreach($activeTasks as $t):?><option value="<?=$t['id']?>"><?=wt_html($t['title'])?></option><?php endforeach;?></select></div>

    <div class="field">
        <label>Where / context</label>
        <select name="start_location" id="startLocation" required>
            <option value="onsite">On site</option>
            <option value="bunnings">Bunnings</option>
            <option value="supplier">Another supplier / store</option>
            <option value="travel_job">Travelling for this job</option>
            <option value="workshop_home">Workshop / home preparation</option>
            <option value="offsite_planning">Off-site planning / admin for this job</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div class="field">
        <label>Location detail (optional)</label>
        <input name="location_detail" id="locationDetail" placeholder="e.g. Bunnings Pakenham / Reece">
        <div class="small" id="locationHint">Optional — add a specific site, store, supplier or route.</div>
    </div>

    <div class="field">
        <label>Activity type</label>
        <select name="category" id="activityType">
            <option value="onsite">On-site work</option>
            <option value="measurement">Measurement / investigation</option>
            <option value="planning">Planning</option>
            <option value="procurement">Sourcing / procurement</option>
            <option value="travel">Job-specific travel</option>
            <option value="loading_setup">Loading / setup / pack-up</option>
            <option value="demolition">Demolition / removal</option>
            <option value="repair">Repair / preparation</option>
            <option value="unforeseen">Unforeseen / remedial</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div class="field wide">
        <label>What are you doing?</label>
        <input name="notes" placeholder="e.g. removing damaged tiles and checking wall condition" required>
    </div>
</div>
<p class="checkline">
    <input type="checkbox" name="charge_travel" value="1">
    <b>Charge travel time if this activity is travel</b>
    <span class="small">Leave unticked for normal local travel.</span>
</p>

<?php if(!isset($runningWorkerKeys['mike']) || count($workers) > 0):?>
<button class="btn start" style="font-size:18px;padding:15px 22px">▶ START ACTIVITY</button>
<?php else:?>
<p class="status-warn">Mike already has a running session. Stop it above before starting another.</p>
<?php endif;?>
</form>
</div>

<div class="card">
<h2>Recent sessions</h2>
<?php if(!$sessions):?><p>No sessions recorded yet.</p><?php endif;?>
<?php foreach($sessions as $s):
    $locLabel = $locationLabels[$s['start_location'] ?? ''] ?? ($s['start_location'] ?: 'Not specified');
    $stopLabel = $stopReasonLabels[$s['stop_reason'] ?? ''] ?? ($s['stop_reason'] ?: '');
    $durationSeconds = !empty($s['ended_at']) ? max(0, strtotime($s['ended_at']) - strtotime($s['started_at'])) : null;
    $durationText = $durationSeconds !== null ? sprintf('%d:%02d', intdiv($durationSeconds,3600), intdiv($durationSeconds%3600,60)) : 'RUNNING';
?>
<div class="session-row">
    <b><?=wt_html($s['worker_name'] ?: 'Mike')?></b>
    <?php if(($s['session_source']??'live')==='retrospective'):?><span class="source-badge source-retro">RETROSPECTIVE</span><?php else:?><span class="source-badge source-live">LIVE</span><?php endif;?>
    <?php if(empty($s['ended_at'])):?><span class="status-good"> · RUNNING</span><?php endif;?><br>
    <?php if(($s['session_source']??'live')==='retrospective' && !empty($s['retrospective_entered_at'])):?><div class="small">Entered into tracker <?=wt_html($s['retrospective_entered_at'])?></div><?php endif;?>
    <div class="session-meta">
        <?php if(!empty($s['task_id']) && isset($taskById[(int)$s['task_id']])):?><b>Task:</b> <?=wt_html($taskById[(int)$s['task_id']]['title'])?><br><?php endif;?>
        <b>Started:</b> <?=wt_html(wt_melbourne_time($s['started_at']))?> · <?=wt_html($locLabel)?>
        <?php if(!empty($s['location_detail'])):?> (<?=wt_html($s['location_detail'])?>)<?php endif;?>
        · <?=wt_html($s['category'])?><br>
        <?php if(!empty($s['notes'])):?><b>Start note:</b> <?=wt_html($s['notes'])?><br><?php endif;?>
        <?php if(!empty($s['ended_at'])):?>
            <b>Stopped:</b> <?=wt_html(wt_melbourne_time($s['ended_at']))?> · duration <?=$durationText?><br>
            <?php if($stopLabel):?><b>Reason:</b> <?=wt_html($stopLabel)?><br><?php endif;?>
            <?php if(!empty($s['stop_note'])):?><b>Stop note:</b> <?=wt_html($s['stop_note'])?><br><?php endif;?>
            <?php if(!empty($s['expected_return'])):?><b>Expected return / next attendance:</b> <?=wt_html($s['expected_return'])?><br><?php endif;?>
        <?php endif;?>
    </div>

    <?php if(!empty($s['ended_at'])):?>
    <details style="margin-top:10px">
        <summary style="cursor:pointer"><b>✏️ Edit recorded work</b></summary>

        <form
            method="post"
            action="../../api/work/update_recorded_session.php"
            style="margin-top:10px;padding:12px;background:#f7f9fb;border:1px solid #d8e0e8;border-radius:10px"
        >
            <input type="hidden" name="job_id" value="<?=$id?>">
            <input type="hidden" name="session_id" value="<?=(int)$s['id']?>">

            <div class="row">

                <div class="field">
                    <label>Start date / time</label>
                    <input
                        type="datetime-local"
                        name="started_at"
                        value="<?=wt_html(date('Y-m-d\\TH:i', strtotime($s['started_at'])))?>"
                        required
                    >
                </div>

                <div class="field">
                    <label>Finish date / time</label>
                    <input
                        type="datetime-local"
                        name="ended_at"
                        value="<?=wt_html(date('Y-m-d\\TH:i', strtotime($s['ended_at'])))?>"
                        required
                    >
                </div>

                <?php
    /*
     * Reload the true saved treatment.
     * billable=1 means charged.
     * Non-billable sessions use their linked ledger entry to distinguish
     * goodwill from rectification.
     */
    $sessionChargeTreatment =
        ((int)($s['billable'] ?? 1) === 1)
            ? 'billable'
            : 'no_charge_labour';

    $sessionMarker = '[session:' . (int)$s['id'] . ']';

    foreach ($complimentaryItems as $linkedChargeItem) {
        $linkedNote = (string)($linkedChargeItem['note'] ?? '');

        if (strpos($linkedNote, $sessionMarker) === false) {
            continue;
        }

        $linkedReason =
            (string)($linkedChargeItem['no_charge_reason'] ?? '');

        if ($linkedReason === 'rectification') {
            $sessionChargeTreatment = 'no_charge_rectification';
        } elseif ($linkedReason === 'goodwill') {
            $sessionChargeTreatment = 'no_charge_labour';
        }

        break;
    }
?>

<div class="field">
    <label>Charge treatment</label>

    <select name="charge_treatment">
        <option
            value="billable"
            <?=$sessionChargeTreatment === 'billable' ? 'selected' : ''?>
        >
            Billable
        </option>

        <option
            value="no_charge_labour"
            <?=$sessionChargeTreatment === 'no_charge_labour' ? 'selected' : ''?>
        >
            No-charge labour / goodwill
        </option>

        <option
            value="no_charge_rectification"
            <?=$sessionChargeTreatment === 'no_charge_rectification' ? 'selected' : ''?>
        >
            No-charge rectification
        </option>
    </select>
</div>

                <div class="field wide">
                    <label>Start note / work performed</label>
                    <textarea name="notes"><?=wt_html((string)($s['notes'] ?? ''))?></textarea>
                </div>

                <div class="field wide">
                    <label>Stop note</label>
                    <input
                        name="stop_note"
                        value="<?=wt_html((string)($s['stop_note'] ?? ''))?>"
                    >
                </div>

            </div>

            <p class="small" style="margin-top:8px">
                Use this to correct historical entries, descriptions or recorded
                start/finish times. Changes affect the recorded job-time totals.
            </p>

            <button class="btn">
                💾 SAVE CHANGES
            </button>
        </form>
    </details>

    <?php if(($s['session_source']??'')==='retrospective'):?>
    <details style="margin-top:8px">
        <summary style="cursor:pointer">
            <b>✂️ Split recorded work</b>
        </summary>

        <form
            method="post"
            action="../../api/work/split_recorded_session.php"
            style="margin-top:10px;padding:12px;background:#fff8e8;border:1px solid #ead39a;border-radius:10px"
        >
            <input type="hidden" name="job_id" value="<?=$id?>">
            <input type="hidden" name="session_id" value="<?=(int)$s['id']?>">

            <p class="small">
                Use this when one past-work entry contains both paid time
                and time you are not charging for.
            </p>

            <div class="row">

                <div class="field">
                    <label>Billable hours</label>
                    <input
                        type="number"
                        name="billable_hours"
                        min="0.01"
                        step="0.01"
                        required
                    >
                </div>

                <div class="field">
                    <label>No-charge hours</label>
                    <input
                        type="number"
                        name="free_hours"
                        min="0.01"
                        step="0.01"
                        required
                    >
                </div>

                <div class="field">
                    <label>No-charge reason</label>
                    <select name="free_reason">
                        <option value="goodwill">
                            Goodwill / free extra
                        </option>

                        <option value="rectification">
                            Rectification / correcting my own work
                        </option>

                        <option value="other">
                            Other no-charge work
                        </option>
                    </select>
                </div>

                <div class="field wide">
                    <label>Explanation</label>
                    <input
                        name="free_note"
                        placeholder="e.g. 8 hours paid, remaining 4 hours supplied free"
                    >
                </div>

            </div>

            <p class="small">
                The two amounts must equal the original recorded hours.
                The overall job-time total will stay unchanged.
            </p>

            <button
                class="btn"
                type="submit"
                onclick="return confirm('Split this historical work record into separate billable and no-charge records?');"
            >
                ✂️ SPLIT RECORDED WORK
            </button>

        </form>
    </details>
    <?php endif;?>

    <?php endif;?>

</div>
<?php endforeach;?>
</div>

<div class="card">
<h2>Add material / expense</h2>
<form method="post" action="../../api/work/add_material.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <input name="description" placeholder="Material / item" required>
    <input name="supplier" placeholder="Supplier">
    <input name="cost" type="number" step=".01" placeholder="$" required>
    <select name="paid_by">
        <option value="mike">Paid by Mike</option>
        <option value="customer">Paid by customer</option>
        <option value="other">Other</option>
    </select>

    <select name="financial_treatment">
        <option value="charge_customer">Charge customer</option>
        <option value="included_in_price">Included in agreed price</option>
        <option value="goodwill">Goodwill — no charge</option>
        <option value="rectification">Rectification — Mike absorbs cost</option>
    </select>

    <button class="btn">Add</button>
</div>
</form>
</div>

<div class="card">
<h2>Progress payment</h2>
<form method="post" action="../../api/work/add_payment.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <input name="amount" type="number" step=".01" placeholder="Amount received $" required>
    <input name="method" placeholder="Bank / cash / etc">
    <button class="btn">Record payment</button>
</div>
</form>

<form method="post" action="../../api/work/send_sms.php">
    <input type="hidden" name="job_id" value="<?=$id?>">
    <input type="hidden" name="kind" value="progress_payment">
    <button class="btn sms">SMS progress-payment request</button>
</form>
</div>

<div class="card">
<h2>Send daily progress report</h2>
<form method="post" action="../../api/work/daily_report.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<textarea name="work_summary" required placeholder="What was done today?"></textarea><br><br>
<textarea name="issues_summary" placeholder="Problems / unforeseen conditions / why things took longer"></textarea><br><br>
<textarea name="next_steps" placeholder="Next work / priorities"></textarea><br>
<button class="btn sms">Save report + SMS customer</button>
</form>
</div>


</div>

<script>
(function(){
    function parseMysqlDate(s){
        if(!s) return null;
        return new Date(s.replace(' ', 'T') + 'Z');
    }
    function pad(n){ return String(n).padStart(2,'0'); }
    function updateTimers(){
        document.querySelectorAll('.live-timer').forEach(function(el){
            const start = parseMysqlDate(el.dataset.start);
            if(!start || isNaN(start.getTime())) return;
            const breakSeconds = Number(el.dataset.breakSeconds || 0);
            const calculatedSeconds = Math.max(
                0,
                Math.floor((Date.now()-start.getTime())/1000) - breakSeconds
            );

            let sec = calculatedSeconds;

            if (el.dataset.paused === '1') {
                if (!el.dataset.frozenSeconds) {
                    el.dataset.frozenSeconds = String(calculatedSeconds);
                }

                sec = Number(el.dataset.frozenSeconds);
            } else {
                delete el.dataset.frozenSeconds;
            }

            const h = Math.floor(sec/3600);
            const m = Math.floor((sec%3600)/60);
            const s = sec%60;
            el.textContent = pad(h)+':'+pad(m)+':'+pad(s);
        });
    }
    updateTimers();
    setInterval(updateTimers,1000);
})();
</script>

<script>
(function(){
    const dryingButton = document.querySelector('[data-quick-open="drying"]');
    if (!dryingButton) return;

    dryingButton.addEventListener('click', function(){
        const existingWaitButton = document.querySelector('.wait-cure-quick-button');
        if (existingWaitButton) {
            existingWaitButton.click();
            existingWaitButton.scrollIntoView({behavior:'smooth', block:'center'});
            return;
        }

        const runningTimer = document.getElementById('live-timer');
        if (runningTimer) {
            runningTimer.scrollIntoView({behavior:'smooth', block:'center'});
        }
    });
})();
</script>


<script>
(function(){
    const locationSelect = document.getElementById('startLocation');
    const activitySelect = document.getElementById('activityType');
    const detailInput = document.getElementById('locationDetail');
    const hint = document.getElementById('locationHint');

    if(!locationSelect || !activitySelect || !detailInput) return;

    const presets = {
        onsite: {
            activity: 'onsite',
            placeholder: 'e.g. Bathroom / rear window / upstairs bedroom',
            hint: 'Optional — identify the part of the property you are working in.'
        },
        bunnings: {
            activity: 'procurement',
            placeholder: 'e.g. Bunnings Pakenham',
            hint: 'Add the specific Bunnings/store so the customer can see where sourcing occurred.'
        },
        supplier: {
            activity: 'procurement',
            placeholder: 'e.g. Clayton Glass / specialty timber supplier',
            hint: 'Add the supplier or store name.'
        },
        travel_job: {
            activity: 'travel',
            placeholder: 'e.g. Pakenham → Clayton Glass',
            hint: 'A route is useful here, especially for long supplier trips.'
        },
        workshop_home: {
            activity: 'planning',
            placeholder: 'e.g. Workshop — cutting/preparing window trims',
            hint: 'Describe the off-site preparation location or task.'
        },
        offsite_planning: {
            activity: 'planning',
            placeholder: 'e.g. Home office — supplier calls and job planning',
            hint: 'Describe the off-site planning/admin activity.'
        },
        other: {
            activity: 'other',
            placeholder: 'Describe where / context',
            hint: 'Add enough detail for the customer to understand what is happening.'
        }
    };

    let lastAutoActivity = null;

    function applyLocationPreset(forceActivity){
        const preset = presets[locationSelect.value] || presets.other;
        detailInput.placeholder = preset.placeholder;
        if(hint) hint.textContent = preset.hint;

        // Auto-select the sensible activity when location changes.
        // The user can still manually override it afterwards.
        if(forceActivity || activitySelect.value === lastAutoActivity || !lastAutoActivity){
            activitySelect.value = preset.activity;
            lastAutoActivity = preset.activity;
        }
    }

    locationSelect.addEventListener('change', function(){
        applyLocationPreset(true);
    });

    activitySelect.addEventListener('change', function(){
        // Manual override: stop treating current value as auto-selected.
        lastAutoActivity = null;
    });

    applyLocationPreset(false);
})();
</script>
<script>
(function(){
    const jobId = <?= (int)$id ?>;
    const initialIds = <?= json_encode(array_merge(array_map(fn($cr)=>'task-'.(int)$cr['id'], array_values(array_filter($taskChangeRequests,fn($cr)=>$cr['status']==='awaiting_review'))), array_map(fn($rr)=>'request-'.(int)$rr['id'], $pendingRequestRevisions))) ?>;
    let knownIds = new Set(initialIds);
    const alertBox = document.getElementById('liveChangeAlert');
    const alertText = document.getElementById('liveChangeText');
    const reviewLink = document.getElementById('reviewLiveChange');
    const enableBtn = document.getElementById('enableBrowserAlerts');

    function updatePermissionButton(){
        if(!('Notification' in window)){ enableBtn.style.display='none'; return; }
        if(Notification.permission === 'granted') enableBtn.textContent='CHROME ALERTS ON';
        else if(Notification.permission === 'denied') enableBtn.textContent='CHROME ALERTS BLOCKED';
        else enableBtn.textContent='ENABLE CHROME ALERTS';
    }
    enableBtn.addEventListener('click', async function(){
        if(!('Notification' in window)) return;
        if(Notification.permission === 'default') await Notification.requestPermission();
        updatePermissionButton();
    });
    updatePermissionButton();

    function showBrowserNotification(item){
        if(!('Notification' in window) || Notification.permission !== 'granted') return;
        const n = new Notification('Customer job change received', {
            body: item.customer_name + ' — ' + item.task_title + ': ' + item.customer_message,
            tag: 'work-change-' + item.id,
            requireInteraction: true
        });
        n.onclick = function(){ window.focus(); location.href='manage_job.php?id='+jobId+'#'+(item.anchor||('task-'+item.task_id)); n.close(); };
    }

    async function pollChanges(){
        try{
            const r = await fetch('../../api/work/pending_task_changes.php?job_id='+encodeURIComponent(jobId), {cache:'no-store', credentials:'same-origin'});
            if(!r.ok) return;
            const data = await r.json();
            const items = Array.isArray(data.pending) ? data.pending : [];
            const ids = new Set(items.map(x=>String(x.id)));
            const fresh = items.filter(x=>!knownIds.has(String(x.id)));
            knownIds = ids;

            if(items.length){
                alertBox.classList.add('show');
                const newest=items[0];
                alertText.textContent=items.length+' unresolved customer change request'+(items.length===1?'':'s')+'. Latest: '+newest.task_title+' — '+newest.customer_message;
                reviewLink.href='manage_job.php?id='+jobId+'&live_change=1#'+(newest.anchor||('task-'+newest.task_id));
            }else{
                alertBox.classList.remove('show');
            }
            fresh.forEach(showBrowserNotification);
        }catch(e){ /* keep admin page usable if polling temporarily fails */ }
    }
    setInterval(pollChanges, 10000);
    document.addEventListener('visibilitychange', function(){ if(!document.hidden) pollChanges(); });
})();
</script>

<script>
(function(){
 const btn=document.getElementById('generateAiTasks');
 const msg=document.getElementById('aiBreakdownMessage');
 if(!btn) return;
 async function runAi(){
   btn.disabled=true; btn.textContent='AI BREAKDOWN RUNNING…'; msg.textContent='Creating individual tasks, granular procedures, time drivers, waiting notes and suggested materials…';
   try{
     const fd=new FormData(); fd.append('job_id','<?= (int)$id ?>');
     const r=await fetch('../../api/work/generate_ai_tasks.php',{method:'POST',body:fd,credentials:'same-origin'});
     const d=await r.json();
     if(!r.ok||!d.ok) throw new Error(d.error||'AI breakdown failed');
     msg.textContent='✓ Added '+d.count+' AI-generated task'+(d.count===1?'':'s')+'. Reloading…';
     setTimeout(()=>location.href='manage_job.php?id=<?= (int)$id ?>&ai_generated=1#tasks',700);
   }catch(e){msg.textContent='AI breakdown failed: '+e.message;btn.disabled=false;btn.textContent='GENERATE / UPDATE AI TASK BREAKDOWN';}
 }
 btn.addEventListener('click',runAi);
 <?php if(($_GET['run_ai']??'')==='1'):?>setTimeout(runAi,500);<?php endif;?>
})();
</script>

<script src="assets/workspace_v8_3.js?v=4" defer></script>
<script src="assets/workspace_v8_4b.js?v=1" defer></script>
<script src="assets/task_photos_inline_v8_4c.js?v=1" defer></script>

<script>
(function () {
    function updateChangeActivityForm(select) {
        const form = select.closest('form');
        if (!form) return;

        form.enctype = 'multipart/form-data';

        const fields = form.querySelector('.change-activity-fields');
        const nextFields = form.querySelector('.finish-next-fields');
        const notes = form.querySelector('[name="notes"]');
        const nextTask = form.querySelector('[name="next_task_id"]');
        const nextNotes = form.querySelector('[name="next_notes"]');

        if (!fields || !notes) return;

        const changing = select.value === 'change';
        const finishNext = select.value === 'finish_task_start_next';

        fields.style.display = changing ? '' : 'none';
        if (nextFields) nextFields.style.display = finishNext ? '' : 'none';
        notes.required = changing;
        if (nextTask) nextTask.required = finishNext;
        if (nextNotes) nextNotes.required = finishNext;
    }

    document.querySelectorAll('.session-action-select').forEach(function (select) {
        updateChangeActivityForm(select);

        select.addEventListener('change', function () {
            updateChangeActivityForm(select);
        });
    });

    /*
     * Keep the active timer directly below the job heading on mobile.
     * This avoids scrolling through the complete admin record every time.
     */
    function positionMobileTimer() {
        if (!window.matchMedia('(max-width: 700px)').matches) return;

        const timer = document.getElementById('live-timer');
        const heading = document.querySelector('.wrap h1');

        if (!timer || !heading) return;

        let anchor = heading.nextElementSibling;

        if (anchor) {
            anchor.insertAdjacentElement('afterend', timer);
        } else {
            heading.insertAdjacentElement('afterend', timer);
        }
    }

    window.addEventListener('load', positionMobileTimer);
})();
</script>


<script>
/* V8.7 COMPLETE SMS RESULT TOAST */
(() => {
    'use strict';

    const jobId = <?=json_encode((string)$id)?>;
    const storageKey =
        'mot-pending-admin-action-' + jobId;

    const latestSms = {
        id: <?=(int)$latestOutboundSmsId?>,
        status: <?=json_encode($latestOutboundSmsStatus)?>,
        purpose: <?=json_encode($latestOutboundSmsPurpose)?>,
        message: <?=json_encode($latestOutboundSmsMessage)?>
    };

    const existingBackendToast =
        <?=is_array($smsFlash) ? 'true' : 'false'?>;

    function clean(value) {
        return (value || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getActionName(form) {
        const clicked = form.__clickedButton;

        if (clicked) {
            const label = clean(
                clicked.textContent || clicked.value
            );

            if (label) return label;
        }

        const container = form.closest(
            '.card, .wt83-section, details, section'
        );

        const heading = container?.querySelector(
            'h2, h3, summary, .wt83-section-title'
        );

        return clean(heading?.textContent)
            || 'Manage Job change';
    }

    function showResultToast(
        type,
        heading,
        summary,
        details
    ) {
        document
            .getElementById('mot-complete-sms-toast')
            ?.remove();

        const colours = {
            sent: {
                background: '#e7f6ec',
                border: '#268447',
                heading: '#146b32'
            },
            none: {
                background: '#eef4fb',
                border: '#4779a8',
                heading: '#225985'
            },
            failed: {
                background: '#fde8e8',
                border: '#b42318',
                heading: '#9d1c13'
            }
        };

        const colour = colours[type] || colours.none;

        const toast = document.createElement('div');
        toast.id = 'mot-complete-sms-toast';

        Object.assign(toast.style, {
            position: 'fixed',
            top: '18px',
            right: '18px',
            zIndex: '30000',
            width: 'min(460px, calc(100vw - 36px))',
            maxHeight: '85vh',
            overflowY: 'auto',
            background: colour.background,
            border: '2px solid ' + colour.border,
            borderRadius: '12px',
            padding: '15px 17px',
            boxShadow: '0 8px 28px #0004',
            fontFamily: 'system-ui, sans-serif'
        });

        const top = document.createElement('div');

        Object.assign(top.style, {
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'flex-start',
            gap: '12px'
        });

        const title = document.createElement('strong');
        title.textContent = heading;

        Object.assign(title.style, {
            color: colour.heading,
            fontSize: '18px'
        });

        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '×';

        Object.assign(close.style, {
            border: '0',
            background: 'transparent',
            cursor: 'pointer',
            fontSize: '24px',
            lineHeight: '1'
        });

        close.addEventListener(
            'click',
            () => toast.remove()
        );

        top.append(title, close);

        const summaryBox = document.createElement('div');
        summaryBox.textContent = summary;
        summaryBox.style.marginTop = '9px';

        toast.append(top, summaryBox);

        if (details) {
            const detailBox = document.createElement('div');
            detailBox.textContent = details;

            Object.assign(detailBox.style, {
                marginTop: '10px',
                padding: '10px',
                background: '#fff',
                borderRadius: '8px',
                fontSize: '13px',
                whiteSpace: 'pre-wrap'
            });

            toast.append(detailBox);
        }

        document.body.append(toast);
    }

    document.addEventListener('click', event => {
        const button = event.target.closest(
            'button[type="submit"], ' +
            'input[type="submit"], ' +
            'button:not([type])'
        );

        if (button?.form) {
            button.form.__clickedButton = button;
        }
    });

    document.addEventListener('submit', event => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const action = form.getAttribute('action') || '';

        /*
         * These forms return JSON and do not reload the page.
         */
        if (
            action.includes(
                'upload_task_photo_inline_admin.php'
            ) ||
            action.includes('generate_ai_tasks.php')
        ) {
            return;
        }

        const pending = {
            jobId: jobId,
            action: getActionName(form),
            previousSmsId: latestSms.id,
            submittedAt: Date.now()
        };

        try {
            sessionStorage.setItem(
                storageKey,
                JSON.stringify(pending)
            );
        } catch (_) {}
    });

    let pending = null;

    try {
        pending = JSON.parse(
            sessionStorage.getItem(storageKey) || 'null'
        );
    } catch (_) {
        pending = null;
    }

    if (!pending || pending.jobId !== jobId) {
        return;
    }

    sessionStorage.removeItem(storageKey);

    /*
     * Ignore an abandoned action older than 15 minutes.
     */
    if (
        !pending.submittedAt ||
        Date.now() - pending.submittedAt >
            15 * 60 * 1000
    ) {
        return;
    }

    /*
     * Direct SMS controls already create a detailed backend
     * confirmation containing the exact message.
     */
    if (existingBackendToast) {
        return;
    }

    const actionName =
        pending.action || 'Manage Job change';

    const newSmsRecorded =
        latestSms.id >
        Number(pending.previousSmsId || 0);

    if (!newSmsRecorded) {
        showResultToast(
            'none',
            'ℹ NO SMS WAS SENT',
            actionName + ' was saved successfully.',
            'No new customer SMS was recorded.\n\n' +
            'Notify the customer manually if this change ' +
            'affects what they need to know.'
        );

        return;
    }

    const lowerStatus =
        clean(latestSms.status).toLowerCase();

    const failed =
        lowerStatus.includes('fail') ||
        lowerStatus.includes('error') ||
        lowerStatus.includes('reject') ||
        lowerStatus.includes('undeliver');

    if (failed) {
        showResultToast(
            'failed',
            '✕ SMS FAILED',
            actionName + ' was saved, but the SMS failed.',
            'MESSAGE:\n\n' +
            (latestSms.message ||
                '(Message content unavailable)') +
            '\n\nGateway: ' +
            (latestSms.status || 'Failure recorded')
        );

        return;
    }

    const metadata = [
        latestSms.purpose
            ? 'Type: ' +
              latestSms.purpose.replace(/_/g, ' ')
            : '',
        latestSms.status
            ? 'Gateway: ' + latestSms.status
            : 'Recorded for dispatch'
    ].filter(Boolean).join(' · ');

    showResultToast(
        'sent',
        '✓ SMS SENT AND RECORDED',
        actionName + ' was saved successfully.',
        'MESSAGE SENT TO CUSTOMER:\n\n' +
        (latestSms.message ||
            '(Message content unavailable)') +
        '\n\n' +
        metadata +
        '\n\nA permanent copy is in SMS history.'
    );
})();
</script>


<script src="assets/workspace_v8_8.js"></script>


<script>
/* V8.9 OPTIONAL PER-EDIT CUSTOMER SMS */
(() => {
    'use strict';

    const labels = {
        not_started: 'not started',
        in_progress: 'in progress',
        blocked: 'blocked',
        completed: 'completed',
        cancelled: 'cancelled'
    };

    function buildTaskSms(form) {
        const title =
            form.querySelector('[name="title"]')?.value.trim()
            || 'Job task';

        const status =
            form.querySelector('[name="status"]')?.value
            || 'not_started';

        return (
            'Mike of All Trades update: "' +
            title +
            '" is now ' +
            (labels[status] || status) +
            '. Your job record has been updated.'
        );
    }

    document.querySelectorAll(
        '#tasks form[action*="update_task.php"]'
    ).forEach(form => {

        const sms =
            form.querySelector('.task-customer-sms');

        const title =
            form.querySelector('[name="title"]');

        const status =
            form.querySelector('[name="status"]');

        if (!sms || !title || !status) {
            return;
        }

        let manuallyEdited = false;

        sms.addEventListener('input', () => {
            manuallyEdited = true;
            sms.dataset.autoSms = '0';
        });

        function refreshDraft() {
            if (manuallyEdited) {
                return;
            }

            sms.value = buildTaskSms(form);
        }

        title.addEventListener('input', refreshDraft);
        status.addEventListener('change', refreshDraft);

        /*
         * If Mike clears the text completely, Save + SMS will still
         * let the server generate a safe current-status message.
         */
    });
})();
</script>


<script>
/* V8.9 WAITING FOR DRYING / CURING */
(function () {
    'use strict';

    const cureTaskOptions = <?=json_encode(array_map(static fn($t) => [
        'id' => (int)$t['id'],
        'title' => (string)$t['title'],
    ], $activeTasks), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildTaskOptions(currentTaskId) {
        const current = Number(currentTaskId || 0);
        const options = cureTaskOptions
            .filter(task => Number(task.id) !== current)
            .map(task => (
                '<option value="' +
                Number(task.id) +
                '">' +
                escapeHtml(task.title) +
                '</option>'
            ))
            .join('');

        return '<option value="">Select task to start...</option>' + options;
    }

    function installCureOption(select) {
        if (!select || select.dataset.cureOptionInstalled === '1') return;

        const form = select.closest('form');
        if (!form) return;

        select.dataset.cureOptionInstalled = '1';

        const option = document.createElement('option');
        option.value = 'wait_cure';
        option.textContent =
            'Waiting for drying / curing / setting — leave job open';

        /*
         * Put this directly underneath "Change activity" where possible.
         */
        if (select.options.length > 0) {
            select.insertBefore(option, select.options[1] || null);
        } else {
            select.appendChild(option);
        }

        const normalAction = form.getAttribute('action');
        const currentTaskId = form.dataset.currentTaskId || '0';

        const panel = document.createElement('div');
        panel.className = 'wait-cure-fields';
        panel.style.display = 'none';
        panel.style.marginTop = '14px';
        panel.style.padding = '14px';
        panel.style.border = '2px solid #d29a31';
        panel.style.borderRadius = '12px';
        panel.style.background = '#fff8e9';

        panel.innerHTML = `
            <div style="font-weight:900;font-size:18px;margin-bottom:8px">
                🕒 Waiting for material to dry / cure / set
            </div>

            <div class="small" style="margin-bottom:12px">
                This stops chargeable work on this job but keeps the job open
                so you can work elsewhere while the applied material dries,
                cures or sets.
            </div>

            <div class="field">
                <label>What was applied?</label>
                <select name="cure_material">
                    <option value="">Select...</option>
                    <option value="Paint / coating">Paint / coating</option>
                    <option value="Primer / undercoat">Primer / undercoat</option>
                    <option value="Plaster / joint compound">Plaster / joint compound</option>
                    <option value="Builders bog">Builders bog</option>
                    <option value="Wood filler / putty">Wood filler / putty</option>
                    <option value="Adhesive / glue">Adhesive / glue</option>
                    <option value="Tile adhesive">Tile adhesive</option>
                    <option value="Grout">Grout</option>
                    <option value="Silicone / sealant">Silicone / sealant</option>
                    <option value="Waterproofing membrane">Waterproofing membrane</option>
                    <option value="Concrete / patching compound">Concrete / patching compound</option>
                    <option value="Multiple applications">Multiple applications</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="field wide">
                <label>What was applied / what needs to happen next?</label>
                <textarea
                    name="cure_note"
                    placeholder="e.g. Plaster applied over wall-anchor holes. Needs to dry before sanding and applying the next coat."
                ></textarea>
            </div>

            <div class="field wide">
                <label>Expected return / next attendance</label>
                <input
                    name="cure_expected_return"
                    placeholder="e.g. Tomorrow morning / after 2–4 hours / next visit"
                >
            </div>

            <div class="field wide">
                <label>📷 Stage photo(s) (optional)</label>
                <input type="file" name="cure_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                <div class="small">Attach what you just applied. Up to 8 photos; they are saved against the current task.</div>
            </div>

            <div class="field wide" style="border-top:1px solid #e1c172;padding-top:10px">
                <label class="checkline"><input type="checkbox" name="start_next_task" value="1"> Start another task now while this dries/cures</label>
            </div>

            <div class="cure-next-task-fields" style="display:none">
                <div class="field">
                    <label>Task to start</label>
                    <select name="cure_next_task_id">
                        ${buildTaskOptions(currentTaskId)}
                    </select>
                </div>

                <div class="field">
                    <label>Next location</label>
                    <select name="cure_next_start_location">
                        <option value="onsite">On site</option>
                        <option value="bunnings">Bunnings</option>
                        <option value="supplier">Another supplier / store</option>
                        <option value="travel_job">Travelling for this job</option>
                        <option value="workshop_home">Workshop / home preparation</option>
                        <option value="offsite_planning">Off-site planning / admin</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="field">
                    <label>Next activity type</label>
                    <select name="cure_next_category">
                        <option value="onsite">On-site work</option>
                        <option value="measurement">Measurement / investigation</option>
                        <option value="planning">Planning</option>
                        <option value="procurement">Sourcing / procurement</option>
                        <option value="travel">Job-specific travel</option>
                        <option value="loading_setup">Loading / setup / pack-up</option>
                        <option value="demolition">Demolition / removal</option>
                        <option value="repair">Repair / preparation</option>
                        <option value="unforeseen">Unforeseen / remedial</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="field wide">
                    <label>What are you doing next?</label>
                    <input
                        name="cure_next_notes"
                        placeholder="e.g. sanding the door frame while plaster dries"
                    >
                </div>
            </div>

            <div class="field wide" style="border-top:1px solid #e1c172;padding-top:10px">
                <label>Customer update</label>
                <label class="checkline"><input type="radio" name="notify_customer" value="0" checked> Save only — do not SMS</label>
                <label class="checkline"><input type="radio" name="notify_customer" value="1"> Save + SMS customer</label>
            </div>
        `;

        select.closest('.field')?.parentNode?.appendChild(panel);

        const submit =
            form.querySelector('button[type="submit"]') ||
            form.querySelector('button:not([type])');

        const normalButtonText = submit ? submit.textContent : '';

        function update() {
            const waiting = select.value === 'wait_cure';

            panel.style.display = waiting ? '' : 'none';

            const material = panel.querySelector('[name="cure_material"]');
            const note = panel.querySelector('[name="cure_note"]');
            const startNext = panel.querySelector('[name="start_next_task"]');
            const nextFields = panel.querySelector('.cure-next-task-fields');
            const nextTask = panel.querySelector('[name="cure_next_task_id"]');
            const nextNotes = panel.querySelector('[name="cure_next_notes"]');
            const startingNext = waiting && !!startNext?.checked;

            if (material) material.required = waiting;
            if (note) note.required = waiting;
            if (nextFields) nextFields.style.display = startingNext ? '' : 'none';
            if (nextTask) nextTask.required = startingNext;
            if (nextNotes) nextNotes.required = startingNext;

            /*
             * Existing Change Activity fields must not remain required while
             * this new action is selected.
             */
            form.querySelectorAll('.change-activity-fields input, .change-activity-fields select, .change-activity-fields textarea')
                .forEach(function (el) {
                    if (waiting) {
                        if (el.required) el.dataset.wasRequired = '1';
                        el.required = false;
                    } else if (el.dataset.wasRequired === '1') {
                        el.required = true;
                        delete el.dataset.wasRequired;
                    }
                });

            if (waiting) {
                form.action = '../../api/work/wait_for_cure.php';

                if (submit) {
                    submit.textContent = '🕒 SAVE — WAITING TO DRY / CURE';
                }
            } else {
                form.action = normalAction;

                if (submit) {
                    submit.textContent = normalButtonText;
                }
            }
        }

        panel.querySelector('[name="start_next_task"]')?.addEventListener('change', update);

        select.addEventListener('change', function () {
            /*
             * Allow the existing change-activity script to run first, then
             * apply our waiting/cure state.
             */
            setTimeout(update, 0);
        });

        const details = form.closest('details.stop-panel');
        if (details && !details.previousElementSibling?.classList.contains('wait-cure-quick-button')) {
            const quick = document.createElement('button');
            quick.type = 'button';
            quick.className = 'btn wait-cure-quick-button';
            quick.style.cssText = 'width:100%;margin:10px 0;background:#d28a00;color:#fff;font-size:18px;padding:15px 18px';
            quick.textContent = '🕒 WAITING FOR DRYING / CURING';
            quick.addEventListener('click', function () {
                details.open = true;
                select.value = 'wait_cure';
                select.dispatchEvent(new Event('change', {bubbles:true}));
                setTimeout(function () { panel.scrollIntoView({behavior:'smooth', block:'center'}); }, 30);
            });
            details.insertAdjacentElement('beforebegin', quick);
        }

        update();
    }

    function install() {
        document
            .querySelectorAll('.session-action-select')
            .forEach(installCureOption);
    }

    document.addEventListener('DOMContentLoaded', install);
    window.addEventListener('load', install);

    setTimeout(install, 500);
})();
</script>

</body>
</html>

<script>
/*
 * Work Tracker:
 * Reliable editing from the financial reconciliation dashboard.
 *
 * Clicking an Edit / Review link opens the exact complimentary-item
 * editor, opens any collapsed workspace container, scrolls to it,
 * and briefly highlights the editor.
 */
(() => {
    'use strict';

    function openComplimentaryEditor(hash) {
        if (!hash || !hash.startsWith('#complimentary-')) {
            return;
        }

        const id = hash.substring(1);
        const target = document.getElementById(id);

        if (!target) {
            console.warn('Complimentary editor not found:', id);
            return;
        }

        /*
         * First open any ordinary <details> ancestors in case the
         * editor sits inside another collapsible block.
         */
        let parent = target.parentElement;

        while (parent) {
            if (parent.tagName === 'DETAILS') {
                parent.open = true;
            }

            parent = parent.parentElement;
        }

        /*
         * Open the Work Tracker workspace section containing the editor.
         */
        const workspaceSection = target.closest('.wt83-section');

        if (
            workspaceSection &&
            workspaceSection.dataset.wt83Open !== '1'
        ) {
            const toggle = workspaceSection.querySelector(
                ':scope > .wt83-section-toggle'
            );

            if (toggle) {
                toggle.click();
            }
        }

        /*
         * Open the actual complimentary-item editor.
         */
        if (target.tagName === 'DETAILS') {
            target.open = true;
        }

        /*
         * Make the selected record visually obvious.
         */
        const oldOutline = target.style.outline;
        const oldOutlineOffset = target.style.outlineOffset;
        const oldBackground = target.style.background;

        target.style.outline = '3px solid #2f7fca';
        target.style.outlineOffset = '4px';
        target.style.background = '#f3f8ff';

        window.setTimeout(() => {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 150);

        window.setTimeout(() => {
            target.style.outline = oldOutline;
            target.style.outlineOffset = oldOutlineOffset;
            target.style.background = oldBackground;
        }, 2500);
    }

    document.addEventListener('click', event => {
        const link = event.target.closest(
            'a[href^="#complimentary-"], a[href*="#complimentary-"]'
        );

        if (!link) {
            return;
        }

        const url = new URL(
            link.getAttribute('href'),
            window.location.href
        );

        if (!url.hash.startsWith('#complimentary-')) {
            return;
        }

        event.preventDefault();

        history.replaceState(
            null,
            '',
            window.location.pathname +
            window.location.search +
            url.hash
        );

        openComplimentaryEditor(url.hash);
    });

    window.addEventListener('load', () => {
        openComplimentaryEditor(window.location.hash);
    });

    window.addEventListener('hashchange', () => {
        openComplimentaryEditor(window.location.hash);
    });
})();
</script>
