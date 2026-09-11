<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

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

$tot = wt_totals($pdo, $id);
$tasks = wt_job_tasks($pdo,$id);
$taskProgress = wt_task_progress($tasks);
$activeTasks = array_values(array_filter($tasks, fn($t)=>!in_array($t['status'],['completed','cancelled'],true)));
$taskById=[]; foreach($tasks as $t)$taskById[(int)$t['id']]=$t;

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
foreach ($complimentaryItems as $ci) $complimentaryTotal += (float)$ci['estimated_value'];

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
</style>
<link rel="stylesheet" href="assets/workspace_v8_3.css?v=1">
<link rel="stylesheet" href="assets/task_photos_inline_v8_4c.css?v=1">
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

<p><a href="index.php">← All jobs</a></p>
<h1>Manage Job #<?=$id?> — <?=wt_html($job['customer_name'])?></h1>
<p><?=wt_html($job['job_address'])?></p>

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

                    <button
                        class="btn stop"
                        style="
                            font-size:18px;
                            padding:15px 22px;
                            width:100%
                        "
                    >
                        SAVE CHANGE / FINISH
                    </button>
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

<div class="card free-card">
<h2>Complimentary extras / value provided at no charge</h2>
<p class="small">Use this whenever you provide extra labour, materials, repairs or improvements as a goodwill gesture. These items are shown separately and are <b>not charged</b>.</p>

<form method="post" action="../../api/work/add_complimentary_item.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
    <select name="item_type">
        <option value="labour">Free labour</option>
        <option value="material">Free material</option>
        <option value="repair">Free repair / fix</option>
        <option value="improvement">Free improvement / upgrade</option>
        <option value="other">Other goodwill extra</option>
    </select>
    <input name="description" placeholder="What did you provide for free?" required>
    <input type="number" step=".01" min="0" name="estimated_value" placeholder="Approx value $">
    <input name="note" placeholder="Why / context (optional)">
    <button class="btn">Add free extra</button>
</div>
</form>

<?php if($complimentaryItems):?>
<p class="free-total">Complimentary value recorded: <?=wt_money($complimentaryTotal)?></p>
<?php foreach($complimentaryItems as $ci):?>
<div style="border-top:1px solid #d9e9d2;padding:10px 0">
    <b><?=wt_html(ucwords(str_replace('_',' ',$ci['item_type'])))?>:</b>
    <?=wt_html($ci['description'])?>
    <?php if((float)$ci['estimated_value']>0):?> — <b>Approx. value <?=wt_money((float)$ci['estimated_value'])?></b><?php endif;?>
    <?php if(!empty($ci['note'])):?><br><span class="small"><?=wt_html($ci['note'])?></span><?php endif;?>
</div>
<?php endforeach;?>
<?php else:?>
<p class="small">No complimentary extras recorded yet.</p>
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
<h2>Job tasks <?php if($pendingChangeCount):?><span class="source-badge source-retro"><?=$pendingChangeCount?> customer change request<?=$pendingChangeCount===1?'':'s'?> awaiting review</span><?php endif;?></h2>
<p class="small">Break the job into real pieces of work. AI and Mike estimates are kept separately. Tracked time is calculated from sessions linked to each task.</p>
<div style="margin:10px 0"><b>Approximate progress: <?=$taskProgress['percent']?>%</b><div class="progressbar"><div class="progressfill" style="width:<?=$taskProgress['percent']?>%"></div></div></div>
<?php if(!$tasks):?><p>No tasks yet.</p><?php endif;?>
<?php foreach($tasks as $t): $cls='task-card '.(($t['status']==='completed')?'task-completed':(($t['status']==='blocked')?'task-blocked':(($t['status']==='cancelled')?'task-cancelled':'')));?>
<div class="card <?=$cls?>" id="task-<?=$t['id']?>"><form method="post" action="../../api/work/update_task.php"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="task_id" value="<?=$t['id']?>">
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
<p class="checkline"><input type="checkbox" name="customer_visible" value="1" <?=$t['customer_visible']?'checked':''?>> Visible to customer</p><button class="btn">SAVE TASK</button></form>
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
<p class="small">Record where you are and what you are doing. One active session per worker is allowed, so repeated clicks cannot create duplicate running timers.</p>
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

<script src="assets/workspace_v8_3.js?v=1" defer></script>
<script src="assets/workspace_v8_4b.js?v=1" defer></script>
<script src="assets/task_photos_inline_v8_4c.js?v=1" defer></script>

<script>
(function () {
    function updateChangeActivityForm(select) {
        const form = select.closest('form');
        if (!form) return;

        const fields = form.querySelector('.change-activity-fields');
        const notes = form.querySelector('[name="notes"]');

        if (!fields || !notes) return;

        const changing = select.value === 'change';

        fields.style.display = changing ? '' : 'none';
        notes.required = changing;
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

</body>
</html>
