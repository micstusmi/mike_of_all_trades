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


$sms = $pdo->prepare("SELECT * FROM work_sms_messages WHERE job_id=? ORDER BY id DESC LIMIT 15");

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
    $endTs = !empty($s['ended_at']) ? strtotime($s['ended_at']) : time();
    $startTs = strtotime($s['started_at']);
    $secs = max(0, $endTs - $startTs);

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

function wt_duration_hm(int $seconds): string {
    $hours = intdiv($seconds, 3600);
    $mins = intdiv($seconds % 3600, 60);
    if ($hours > 0) return $hours.' hr '.str_pad((string)$mins, 2, '0', STR_PAD_LEFT).' min';
    if ($mins > 0) return $mins.' min';
    return '< 1 min';
}

$sms->execute([$id]);
$sms = $sms->fetchAll(PDO::FETCH_ASSOC);

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
.source-badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:900;margin-left:6px}.source-live{background:#e7f5ec;color:#087f23}.source-retro{background:#fff3cd;color:#795d00}.travel-card{background:#eef6ff;border:2px solid #7fb2df}.retro-card{background:#fffaf0;border:2px solid #e1c66f}.checkline{display:flex;align-items:center;gap:8px}.checkline input{width:auto}
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
</head>
<body>

<?php require __DIR__ . '/../../includes/admin_nav.php'; ?>

<div class="wrap">

<p><a href="index.php">← All jobs</a></p>
<h1>Manage Job #<?=$id?> — <?=wt_html($job['customer_name'])?></h1>
<p><?=wt_html($job['job_address'])?></p>

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
<?php elseif(($_GET['on_my_way'] ?? '') === '1'):?>
<div class="card notice-good"><b>🚗 ON MY WAY STARTED.</b> Travel time is now running and the ETA/customer update has been recorded.</div>
<?php elseif(($_GET['arrived'] ?? '') === '1'):?>
<div class="card notice-good"><b>📍 ARRIVED.</b> Travel was stopped and a new on-site work session was started.</div>
<?php endif;?>

<?php foreach($runningSessions as $rs):
    $locLabel = $locationLabels[$rs['start_location'] ?? ''] ?? ($rs['start_location'] ?: 'Not specified');
?>
<div class="card running-card">
    <div class="running-head">
        <div>
            <div class="running-title">● SESSION RUNNING — <?=wt_html($rs['worker_name'] ?: 'Mike')?></div>
            <div><b><?=wt_html($locLabel)?></b><?php if(!empty($rs['location_detail'])):?> — <?=wt_html($rs['location_detail'])?><?php endif;?></div>
            <div class="small">Started <?=wt_html($rs['started_at'])?> · <?=wt_html($rs['category'])?></div>
            <?php if(!empty($rs['notes'])):?><div style="margin-top:6px"><?=wt_html($rs['notes'])?></div><?php endif;?>
        </div>
        <div class="timer live-timer" data-start="<?=wt_html($rs['started_at'])?>">00:00:00</div>
    </div>

    <?php if(($rs['travel_type'] ?? '') === 'to_customer' && $rs['worker_id'] === null):?>
    <form method="post" action="../../api/work/arrive_start_work.php" style="margin-top:14px">
        <input type="hidden" name="job_id" value="<?=$id?>">
        <div class="field wide"><label>What are you starting on site?</label><input name="notes" placeholder="e.g. continue bathroom preparation" required></div>
        <button class="btn start" style="font-size:19px;padding:16px 22px">📍 ARRIVED — STOP TRAVEL &amp; START WORK</button>
    </form>
    <details style="margin-top:12px"><summary><b>Need to pause or cancel the trip instead?</b></summary>
    <?php endif;?>
    <form class="stop-panel" method="post" action="../../api/work/stop_session.php">
        <input type="hidden" name="job_id" value="<?=$id?>">
        <input type="hidden" name="session_id" value="<?=$rs['id']?>">
        <div class="row">
            <div class="field">
                <label>Why are you stopping / pausing?</label>
                <select name="stop_reason" required>
                    <option value="">Select reason...</option>
                    <?php foreach($stopReasonLabels as $value=>$label):?>
                    <option value="<?=wt_html($value)?>"><?=wt_html($label)?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="field wide">
                <label>Stop note / explanation</label>
                <input name="stop_note" placeholder="e.g. stopping for lunch after completing demolition">
            </div>
            <div class="field">
                <label>Expected return / next attendance</label>
                <input name="expected_return" list="eta-options" placeholder="e.g. about 1:30 pm">
            </div>
        </div>
        <button class="btn stop" style="font-size:18px;padding:15px 22px">■ STOP / PAUSE SESSION</button>
    </form>
    <?php if(($rs['travel_type'] ?? '') === 'to_customer' && $rs['worker_id'] === null):?></details><?php endif;?>
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
</div>

<div class="card">
<h2>Customer agreement / live report</h2>
<p><a target="_blank" href="<?=wt_html($url)?>"><?=wt_html($url)?></a></p>
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
    <div class="field wide"><label>What are you starting on arrival?</label><input name="notes" placeholder="e.g. continue wall preparation and tile removal"></div>
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
        <b>Started:</b> <?=wt_html($s['started_at'])?> · <?=wt_html($locLabel)?>
        <?php if(!empty($s['location_detail'])):?> (<?=wt_html($s['location_detail'])?>)<?php endif;?>
        · <?=wt_html($s['category'])?><br>
        <?php if(!empty($s['notes'])):?><b>Start note:</b> <?=wt_html($s['notes'])?><br><?php endif;?>
        <?php if(!empty($s['ended_at'])):?>
            <b>Stopped:</b> <?=wt_html($s['ended_at'])?> · duration <?=$durationText?><br>
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

<div class="card">
<h2>SMS history</h2>
<?php if(!$sms):?><p>No SMS history yet.</p><?php endif;?>
<?php foreach($sms as $m):?>
<p><b><?=wt_html($m['direction'])?></b> <?=wt_html($m['sent_at'])?> — <?=nl2br(wt_html($m['message']))?></p>
<?php endforeach;?>
</div>

</div>

<script>
(function(){
    function parseMysqlDate(s){
        if(!s) return null;
        return new Date(s.replace(' ', 'T'));
    }
    function pad(n){ return String(n).padStart(2,'0'); }
    function updateTimers(){
        document.querySelectorAll('.live-timer').forEach(function(el){
            const start = parseMysqlDate(el.dataset.start);
            if(!start || isNaN(start.getTime())) return;
            const sec = Math.max(0, Math.floor((Date.now()-start.getTime())/1000));
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

</body>
</html>
