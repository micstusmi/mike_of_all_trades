<?php
require_once __DIR__ . '/../includes/work_tracker.php';
$token=$_GET['t']??'';
try{$job=wt_job_by_token($pdo,$token);}catch(Throwable $e){http_response_code(404);die('Job not found.');}
$tot=wt_totals($pdo,(int)$job['id']);

$reports=$pdo->prepare("SELECT * FROM work_reports WHERE job_id=? ORDER BY report_date DESC");
$reports->execute([$job['id']]);
$reports=$reports->fetchAll(PDO::FETCH_ASSOC);

$sessions=$pdo->prepare("SELECT s.*,w.worker_name,w.hourly_rate
FROM work_sessions s
LEFT JOIN work_workers w ON w.id=s.worker_id
WHERE s.job_id=?
ORDER BY started_at DESC LIMIT 100");
$sessions->execute([$job['id']]);
$sessions=$sessions->fetchAll(PDO::FETCH_ASSOC);

$workers=$pdo->prepare("SELECT * FROM work_workers WHERE job_id=? AND active=1 ORDER BY id");
$workers->execute([$job['id']]);
$workers=$workers->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');

$planStmt=$pdo->prepare("SELECT * FROM work_daily_plans WHERE job_id=? AND plan_date=? LIMIT 1");
$planStmt->execute([$job['id'],$today]);
$todayPlan=$planStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$freeStmt=$pdo->prepare("SELECT * FROM work_complimentary_items WHERE job_id=? ORDER BY created_at DESC,id DESC");
$freeStmt->execute([$job['id']]);
$complimentaryItems=$freeStmt->fetchAll(PDO::FETCH_ASSOC);

$complimentaryTotal=0.0;
foreach($complimentaryItems as $ci) $complimentaryTotal+=(float)$ci['estimated_value'];

$timeBreakdown=['onsite'=>0,'supplier'=>0,'travel'=>0,'offsite'=>0,'other'=>0];
foreach($sessions as $s){
    if(empty($s['started_at'])) continue;
    $endTs=!empty($s['ended_at'])?strtotime($s['ended_at']):time();
    $startTs=strtotime($s['started_at']);
    $secs=max(0,$endTs-$startTs);
    $loc=$s['start_location']??'';
    $cat=$s['category']??'';

    if($loc==='onsite') $bucket='onsite';
    elseif(in_array($loc,['bunnings','supplier'],true)) $bucket='supplier';
    elseif($loc==='travel_job'||$cat==='travel') $bucket='travel';
    elseif(in_array($loc,['workshop_home','offsite_planning'],true)) $bucket='offsite';
    else $bucket='other';

    $timeBreakdown[$bucket]+=$secs;
}

function wt_public_duration_hm(int $seconds): string {
    $hours=intdiv($seconds,3600);
    $mins=intdiv($seconds%3600,60);
    if($hours>0) return $hours.' hr '.str_pad((string)$mins,2,'0',STR_PAD_LEFT).' min';
    if($mins>0) return $mins.' min';
    return '< 1 min';
}


$pricingLabels=[
 'fixed_price'=>'Fixed-price quote',
 'estimate'=>'Estimate / approximate budget',
 'hourly'=>'Hourly rate',
 'no_price'=>'No specific price was agreed',
 'unspecified'=>'Not yet specified'
];
$pricingLabel=$pricingLabels[$job['original_pricing_type'] ?? 'unspecified'] ?? 'Not yet specified';

$variationLabels=[
 'fixed_amount'=>'Fixed additional amount',
 'hourly'=>'Hourly varied work',
 'estimate'=>'Estimated range',
 'not_applicable'=>'Not applicable'
];
$variationLabel=$variationLabels[$job['variation_pricing_method'] ?? 'not_applicable'] ?? 'Not applicable';

$locationLabels=[
 'onsite'=>'On site',
 'bunnings'=>'Bunnings',
 'supplier'=>'Another supplier / store',
 'travel_job'=>'Travelling for this job',
 'workshop_home'=>'Workshop / home preparation',
 'offsite_planning'=>'Off-site planning / admin for this job',
 'other'=>'Other'
];

$stopReasonLabels=[
 'lunch'=>'Lunch',
 'coffee_break'=>'Coffee / short break',
 'other_job_errand'=>'Errand for a different job',
 'other_customer_call'=>'Phone call / admin for another customer',
 'home_for_night'=>'Going home for the night',
 'personal_errand'=>'Personal errand',
 'customer_emergency'=>'Helping another customer with an emergency',
 'supplier_delay'=>'Supplier / material delay',
 'awaiting_customer'=>'Waiting for customer decision / access',
 'job_related_travel'=>'Changing location / travelling for this job',
 'completed_activity'=>'Current activity completed',
 'other'=>'Other'
];

$isFixed = (($job['original_pricing_type'] ?? '') === 'fixed_price');
$variationRequired = $isFixed && !empty($job['variation_required']);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Job record</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;margin:0;background:#f4f6f8;color:#17202a}
.wrap{max-width:850px;margin:auto;padding:16px}
.brand{font-size:29px;font-weight:850}
.card{background:#fff;border-radius:14px;padding:18px;margin:12px 0;box-shadow:0 2px 10px #0001}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.metric{padding:12px;background:#f2f4f5;border-radius:10px}
.big{font-weight:850;font-size:23px}
.notice{background:#fff8d9;border:1px solid #e3c55a}
.info{background:#eef6ff;border:1px solid #a7c8eb}
.position{background:#f8fafb;border:1px solid #dce2e7}
.variation{background:#fff4ef;border:2px solid #e58a60}
.btn{background:#17202a;color:#fff;border:0;border-radius:10px;padding:13px 16px;font-weight:800}
.sig{border:1px solid #aaa;width:100%;height:180px;touch-action:none;background:white}
textarea,input{box-sizing:border-box;width:100%;padding:11px;border:1px solid #ccd1d5;border-radius:8px;font:inherit}
.muted{color:#66717c;font-size:14px}
.check{display:flex;gap:10px;align-items:flex-start;margin:14px 0}
.check input{width:auto;margin-top:4px}
.amountrow{display:flex;justify-content:space-between;gap:15px;padding:7px 0;border-bottom:1px solid #e8ecef}
.amountrow:last-child{border-bottom:0}
.totalrow{font-size:19px;font-weight:850}
.workers{margin:7px 0}
.tag{display:inline-block;padding:5px 9px;border-radius:999px;background:#17202a;color:white;font-size:12px;font-weight:800}
.activity-card{border:1px solid #dce3e8;border-radius:14px;overflow:hidden;margin:14px 0;background:#fff}
.activity-start{background:#eefaf1;border-bottom:1px solid #b7dfc1;padding:14px 16px}
.activity-start.running{background:#e6f8eb}
.activity-stop{background:#fff8e7;border-top:1px solid #ead39a;padding:14px 16px}
.activity-heading{display:flex;align-items:center;gap:9px;flex-wrap:wrap;font-size:18px;font-weight:900}
.activity-time{font-variant-numeric:tabular-nums}
.activity-icon{font-size:20px}
.activity-grid{display:grid;grid-template-columns:150px 1fr;gap:7px 12px;margin-top:11px}
.activity-label{font-size:13px;font-weight:850;color:#53606c}
.activity-value{font-size:14px}
.activity-note{margin-top:10px;padding:10px 12px;background:#fff;border-radius:9px;border:1px solid #dde5ea}
.activity-footer{padding:11px 16px;background:#f7f9fa;border-top:1px solid #e4e9ed;font-size:14px}
.eta-box{margin-top:11px;padding:11px 12px;background:#fff3c4;border:1px solid #e2c35e;border-radius:9px;font-size:16px}
.running-pill{display:inline-block;background:#087f23;color:#fff;font-size:12px;font-weight:850;border-radius:999px;padding:4px 8px}
.activity-empty{color:#68737d;font-style:italic}
@media(max-width:600px){
  .activity-grid{grid-template-columns:1fr;gap:2px}
  .activity-label{margin-top:7px}
}
@media(max-width:650px){.grid{grid-template-columns:1fr}.brand{font-size:25px}}

.today-plan{background:#eef6ff;border:2px solid #9fc4e5}
.today-plan-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:12px}
.today-plan-stat{background:#fff;border:1px solid #d8e5ef;border-radius:10px;padding:11px}
.today-plan-stat b{display:block;font-size:12px;color:#526574;margin-bottom:4px}
.today-plan-big{font-size:18px;font-weight:900}
.interruption-box{background:#fff6df;border:1px solid #e4c66c;border-radius:10px;padding:12px;margin-top:12px}
.time-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px}
.time-summary div{background:#f7f9fa;border:1px solid #e2e7ea;border-radius:9px;padding:10px;text-align:center}
.goodwill{background:#f3fbef;border:1px solid #b9dda9}
.goodwill-total{font-size:23px;font-weight:900;color:#2b6a1f}
@media(max-width:700px){.today-plan-grid,.time-summary{grid-template-columns:1fr 1fr}}
@media(max-width:500px){.today-plan-grid,.time-summary{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<div class="brand">Mike of All Trades</div>
<h2>Current Job Record & Agreement</h2>

<div class="card">
<b>Customer:</b> <?=wt_html($job['customer_name'])?><br>
<b>Site:</b> <?=wt_html($job['job_address'])?>
</div>

<div class="grid">
<div class="metric">Recorded job total<div class="big"><?=wt_money($tot['total'])?></div></div>
<div class="metric">Payments<div class="big"><?=wt_money($tot['payments'])?></div></div>
<div class="metric">Current balance<div class="big"><?=wt_money($tot['outstanding'])?></div></div>
</div>

<div class="card info">
<b>Important: cost/time consumed is not necessarily the same as visual percentage complete.</b>
<p style="margin-bottom:0">A substantial part of repair work can occur before the finished result becomes visible—for example investigation, measuring, planning, sourcing, supplier enquiries, procurement, job-specific travel, loading/setup, demolition, preparation and dealing with concealed or unforeseen conditions.</p>
</div>

<div class="card">
<h3>Original arrangement</h3>
<p><span class="tag"><?=wt_html($pricingLabel)?></span></p>
<p><b>Original work discussed</b></p>
<p><?=nl2br(wt_html($job['original_scope']))?></p>

<?php if($job['original_estimate_amount']):?>
<p><b>Original price / estimate:</b> <?=wt_money((float)$job['original_estimate_amount'])?>
<?php if($job['original_estimate_hours']):?> / approximately <?=wt_html((string)$job['original_estimate_hours'])?> hours<?php endif;?>
</p>
<?php endif;?>

<?php if($job['current_scope']):?>
<h3>Current / additional scope</h3>
<p><?=nl2br(wt_html($job['current_scope']))?></p>
<?php endif;?>

<?php if($job['unforeseen_conditions']):?>
<div class="notice" style="padding:12px;border-radius:9px">
<b>Unforeseen conditions / changed circumstances</b>
<p><?=nl2br(wt_html($job['unforeseen_conditions']))?></p>
</div>
<?php endif;?>
</div>

<?php if($variationRequired):?>
<div class="card variation">
<h2>Fixed-price variation requiring explicit authorisation</h2>
<p>The original pricing arrangement is recorded as a fixed-price quote. This section is therefore separate from the general agreement to continue.</p>

<p><b>Variation / changed work</b></p>
<p><?=nl2br(wt_html($job['variation_description'] ?: $job['current_scope']))?></p>

<p><b>Variation pricing method:</b> <?=wt_html($variationLabel)?></p>

<?php if(($job['variation_pricing_method'] ?? '')==='fixed_amount' && $job['variation_fixed_amount']):?>
<div class="big">Additional fixed amount: <?=wt_money((float)$job['variation_fixed_amount'])?></div>
<?php elseif(($job['variation_pricing_method'] ?? '')==='hourly' && $job['variation_hourly_rate']):?>
<div class="big">Varied work: <?=wt_money((float)$job['variation_hourly_rate'])?> / hour</div>
<?php elseif(($job['variation_pricing_method'] ?? '')==='estimate'):?>
<div class="big">Variation forecast: <?=wt_money((float)$job['variation_forecast_low'])?> – <?=wt_money((float)$job['variation_forecast_high'])?></div>
<?php endif;?>

<p class="muted">This variation does not silently convert the original fixed-price arrangement into an unlimited hourly arrangement. Only the varied/additional work described here is being separately authorised on the pricing basis shown.</p>
</div>
<?php endif;?>

<div class="card position">
<h2>A. Position to date</h2>
<p>This section records the current account presented for authorised work/materials already performed or incurred before this agreement is signed.</p>
<div class="amountrow"><span>Labour/work already performed</span><b><?=wt_money((float)$job['work_already_value'])?></b></div>
<div class="amountrow"><span>Mike-paid materials/expenses already incurred</span><b><?=wt_money((float)$job['materials_already_value'])?></b></div>
<div class="amountrow"><span>Payments already received</span><b>− <?=wt_money((float)$job['payments_received'])?></b></div>
<div class="amountrow totalrow"><span>Current balance before new timed sessions</span><span><?=wt_money(max(0,(float)$job['work_already_value']+(float)$job['materials_already_value']-(float)$job['payments_received']))?></span></div>

<?php if($isFixed):?>
<p class="muted"><b>Fixed-price note:</b> this position-to-date section documents work/value and costs recorded to date. It does not by itself rewrite the original fixed price. Any additional price arising from changed scope must be expressly authorised in the variation section above where applicable.</p>
<?php endif;?>
</div>

<div class="card">
<h2>B. Agreement from this point forward</h2>
<p>This agreement applies from the time it is signed. It does not backdate terms or remove statutory consumer rights.</p>

<?php if(!$isFixed):?>
<p>The customer authorises Mike of All Trades to continue the currently authorised work. Unless a separate fixed-price arrangement is expressly recorded, further authorised work from this point is charged using the rates shown below plus authorised materials/expenses.</p>
<?php else:?>
<p>The customer authorises Mike of All Trades to continue the currently authorised work, subject to the original fixed-price arrangement and any separately authorised variation shown above.</p>
<?php endif;?>

<?php if(!$isFixed && $job['agreed_hourly_rate']):?>
<div class="big">Mike: <?=wt_money((float)$job['agreed_hourly_rate'])?> / hour</div>
<?php endif;?>

<?php if(!$isFixed):?>
<?php foreach($workers as $w):?>
<div class="workers"><b><?=wt_html($w['worker_name'])?>:</b> <?=wt_money((float)$w['hourly_rate'])?> / hour</div>
<?php endforeach;?>
<?php endif;?>

<p>Reasonably necessary job-specific activities may include on-site work, investigation, measurement, planning, sourcing/procurement, job-specific supplier travel, loading/setup/pack-up, demolition/removal, preparation and remedial work where they form part of carrying out the authorised job.</p>
<p>The customer may ask Mike to pause/stop further work at any time. Stopping future work does not itself erase properly incurred charges for authorised work/materials already performed or supplied, subject to applicable law and consumer rights.</p>
</div>

<?php if($job['revised_forecast_low']||$job['revised_forecast_high']):?>
<div class="card">
<h2>C. Current revised forecast</h2>
<div class="big"><?=wt_money((float)$job['revised_forecast_low'])?> – <?=wt_money((float)$job['revised_forecast_high'])?></div>
<p>This forecast is based on what is currently known. If the scope, conditions or forecast materially change again, Mike will update the job record and the parties should review the scope and payment position before substantial further affected work proceeds.</p>
</div>
<?php endif;?>

<?php if(!$job['agreement_signed_at']):?>
<div class="card">
<h2>Review & sign</h2>
<form method="post" action="../api/work/sign.php" onsubmit="return prepareSignature()">
<input type="hidden" name="token" value="<?=wt_html($token)?>">
<input type="hidden" name="signature" id="signature">

<div class="check">
<input type="checkbox" name="ack_balance" value="1" required>
<label><b>I acknowledge that I have reviewed the current account shown above.</b><br>
<span class="muted">This acknowledgement remains subject to applicable law and statutory consumer rights.</span></label>
</div>

<?php if($variationRequired):?>
<div class="check" style="background:#fff4ef;padding:12px;border-radius:10px">
<input type="checkbox" name="authorise_variation" value="1" required>
<label><b>I EXPRESSLY AUTHORISE THE FIXED-PRICE VARIATION DESCRIBED ABOVE.</b><br>
<span class="muted">I understand the variation pricing method and amount/range/rate shown above and authorise that changed/additional work to proceed.</span></label>
</div>
<?php endif;?>

<div class="check">
<input type="checkbox" name="authorise_continue" value="1" required>
<label><b>I authorise the current work to continue from this point.</b><br>
<span class="muted">I have reviewed the current scope, changed/unforeseen circumstances, pricing basis and forecast shown above.</span></label>
</div>

<label><b>Your full name</b></label>
<input name="agreement_name" required>

<p><b>Sign below</b></p>
<canvas id="pad" class="sig"></canvas>
<p><button type="button" onclick="clearPad()">Clear signature</button></p>

<button class="btn">SIGN & AUTHORISE CONTINUATION</button>
</form>
</div>
<?php else:?>
<div class="card notice">
<b>Agreement recorded</b><br>
<?=wt_html($job['agreement_name']??'')?> · <?=wt_html($job['agreement_signed_at'])?><br>
<?php if($job['acknowledged_current_balance']):?>✓ Current account reviewed<br><?php endif;?>
<?php if($job['variation_authorised']):?>✓ Fixed-price variation expressly authorised<br><?php endif;?>
<?php if($job['authorised_continuation']):?>✓ Continuation authorised<?php endif;?>
</div>
<?php endif;?>


<?php if($todayPlan):?>
<div class="card today-plan">
<h2>Today's work plan</h2>
<p class="muted">This is Mike's current expectation for today. Times and hours are approximate and may be updated as the job develops.</p>

<div class="today-plan-grid">
    <div class="today-plan-stat">
        <b>Planned start</b>
        <div class="today-plan-big"><?=!empty($todayPlan['planned_start_time'])?wt_html(date('g:i a',strtotime($todayPlan['planned_start_time']))):'Not specified'?></div>
    </div>
    <div class="today-plan-stat">
        <b>Anticipated finish tonight</b>
        <div class="today-plan-big"><?=!empty($todayPlan['planned_finish_time'])?wt_html(date('g:i a',strtotime($todayPlan['planned_finish_time']))):'Not specified'?></div>
    </div>
    <div class="today-plan-stat">
        <b>Anticipated job hours</b>
        <div class="today-plan-big">
            <?php
            $lo=$todayPlan['anticipated_job_hours_low']??null;
            $hi=$todayPlan['anticipated_job_hours_high']??null;
            if($lo!==null&&$hi!==null) echo wt_html(rtrim(rtrim((string)$lo,'0'),'.').'–'.rtrim(rtrim((string)$hi,'0'),'.').' hrs');
            elseif($lo!==null) echo wt_html(rtrim(rtrim((string)$lo,'0'),'.').'+ hrs');
            else echo 'Not specified';
            ?>
        </div>
    </div>
    <div class="today-plan-stat">
        <b>Expected workers</b>
        <div class="today-plan-big"><?=wt_html((string)($todayPlan['expected_worker_count']??1))?></div>
        <div class="muted"><?=wt_html($todayPlan['expected_workers_text']??'Mike only')?></div>
    </div>
</div>

<?php if(!empty($todayPlan['helper_roles'])):?>
<p><b>Helpers / roles:</b> <?=wt_html($todayPlan['helper_roles'])?></p>
<?php endif;?>

<?php if(!empty($todayPlan['planned_interruptions'])):?>
<div class="interruption-box">
<b>Planned interruptions / travel / other commitments</b><br>
<?=nl2br(wt_html($todayPlan['planned_interruptions']))?>
</div>
<?php endif;?>

<?php if(!empty($todayPlan['overall_plan_note'])):?>
<p><?=nl2br(wt_html($todayPlan['overall_plan_note']))?></p>
<?php endif;?>

<p class="muted"><b>Important:</b> the overall work window is not the same as billable time. Breaks, personal errands and unrelated customer work are excluded from this job's recorded activity time. Job-related supplier visits, sourcing and travel may be recorded separately where they form part of carrying out this job.</p>
<p class="muted">Last updated <?=wt_html(date('g:i a',strtotime($todayPlan['updated_at'])))?>.</p>
</div>
<?php endif;?>

<div class="card">
<h2>Where the recorded job time went</h2>
<p class="muted">This separates physical on-site time from supplier/store activity, job-related travel, and off-site preparation or planning.</p>
<div class="time-summary">
    <div><b>On site</b><br><?=wt_html(wt_public_duration_hm($timeBreakdown['onsite']))?></div>
    <div><b>Suppliers / stores</b><br><?=wt_html(wt_public_duration_hm($timeBreakdown['supplier']))?></div>
    <div><b>Job-related travel</b><br><?=wt_html(wt_public_duration_hm($timeBreakdown['travel']))?></div>
    <div><b>Off-site prep / planning</b><br><?=wt_html(wt_public_duration_hm($timeBreakdown['offsite']))?></div>
</div>
</div>

<?php if($complimentaryItems):?>
<div class="card goodwill">
<h2>Complimentary extras provided at no charge</h2>
<p>Mike has provided the following extras as goodwill. They are recorded so the additional value is transparent, but <b>they are not added to the amount payable.</b></p>
<div class="goodwill-total">Approx. complimentary value: <?=wt_money($complimentaryTotal)?></div>
<?php foreach($complimentaryItems as $ci):?>
<div style="border-top:1px solid #d9e9d2;padding:11px 0">
    <b><?=wt_html(ucwords(str_replace('_',' ',$ci['item_type'])))?>:</b>
    <?=wt_html($ci['description'])?>
    <?php if((float)$ci['estimated_value']>0):?> — approx. value <b><?=wt_money((float)$ci['estimated_value'])?></b><?php endif;?>
    <?php if(!empty($ci['note'])):?><br><span class="muted"><?=wt_html($ci['note'])?></span><?php endif;?>
</div>
<?php endforeach;?>
</div>
<?php endif;?>

<div class="card">
<h2>Activity timeline</h2>
<p class="muted">A clear record of when job activity started and stopped, where it occurred, why work paused, and when Mike expected to return.</p>

<?php if(!$sessions):?>
<p class="activity-empty">No job activity recorded yet.</p>
<?php endif;?>

<?php foreach($sessions as $s):
    $locLabel=$locationLabels[$s['start_location']??'']??($s['start_location']?:'Not specified');
    $stopLabel=$stopReasonLabels[$s['stop_reason']??'']??($s['stop_reason']?:'');
    $durationSeconds=!empty($s['ended_at'])?max(0,strtotime($s['ended_at'])-strtotime($s['started_at'])):null;

    if($durationSeconds!==null){
        $hours=intdiv($durationSeconds,3600);
        $mins=intdiv($durationSeconds%3600,60);
        if($hours>0){
            $durationText=$hours.' hr '.str_pad((string)$mins,2,'0',STR_PAD_LEFT).' min';
        } elseif($mins>0){
            $durationText=$mins.' min';
        } else {
            $durationText='< 1 min';
        }
    } else {
        $durationText='Currently running';
    }
?>
<div class="activity-card">

    <div class="activity-start <?=empty($s['ended_at'])?'running':''?>">
        <div class="activity-heading">
            <span class="activity-icon">🟢</span>
            <span class="activity-time"><?=wt_html(date('g:i a',strtotime($s['started_at'])))?></span>
            <span>— WORK STARTED</span>
            <?php if(empty($s['ended_at'])):?><span class="running-pill">CURRENTLY RUNNING</span><?php endif;?>
        </div>

        <div class="activity-grid">
            <div class="activity-label">Worker</div>
            <div class="activity-value"><?=wt_html($s['worker_name']?:'Mike')?></div>

            <div class="activity-label">Location</div>
            <div class="activity-value">
                <?=wt_html($locLabel)?>
                <?php if(!empty($s['location_detail'])):?> — <?=wt_html($s['location_detail'])?><?php endif;?>
            </div>

            <div class="activity-label">Work / activity</div>
            <div class="activity-value"><?=wt_html(ucwords(str_replace('_',' ',$s['category'])))?></div>
        </div>

        <?php if(!empty($s['notes'])):?>
        <div class="activity-note"><b>What Mike was doing:</b><br><?=nl2br(wt_html($s['notes']))?></div>
        <?php endif;?>
    </div>

    <?php if(!empty($s['ended_at'])):?>
    <div class="activity-stop">
        <div class="activity-heading">
            <span class="activity-icon">🟠</span>
            <span class="activity-time"><?=wt_html(date('g:i a',strtotime($s['ended_at'])))?></span>
            <span>— WORK PAUSED / STOPPED</span>
        </div>

        <div class="activity-grid">
            <div class="activity-label">Reason</div>
            <div class="activity-value"><?=wt_html($stopLabel ?: 'Not specified')?></div>
        </div>

        <?php if(!empty($s['stop_note'])):?>
        <div class="activity-note"><b>Explanation:</b><br><?=nl2br(wt_html($s['stop_note']))?></div>
        <?php endif;?>

        <?php if(!empty($s['expected_return'])):?>
        <div class="eta-box"><b>Expected return / next attendance:</b> <?=wt_html($s['expected_return'])?></div>
        <?php endif;?>
    </div>
    <?php endif;?>

    <div class="activity-footer">
        <b>Recorded activity time:</b> <?=wt_html($durationText)?>
    </div>
</div>
<?php endforeach;?>
</div>

<div class="card">
<h2>Daily reports</h2>
<?php if(!$reports):?><p>No daily reports yet.</p><?php endif;?>
<?php foreach($reports as $r):?>
<div style="border-top:1px solid #eee;padding:12px 0">
<h3><?=wt_html($r['report_date'])?></h3>
<p><?=nl2br(wt_html($r['work_summary']))?></p>
<?php if($r['issues_summary']):?><p><b>Issues / changed conditions:</b><br><?=nl2br(wt_html($r['issues_summary']))?></p><?php endif;?>
<p><b>Total to date:</b> <?=wt_money((float)$r['job_total_to_date'])?> · <b>Outstanding:</b> <?=wt_money((float)$r['outstanding_balance'])?></p>
<?php if(!$r['customer_acknowledged_at']):?>
<form method="post" action="../api/work/ack_report.php">
<input type="hidden" name="token" value="<?=wt_html($token)?>">
<input type="hidden" name="report_id" value="<?=$r['id']?>">
<textarea name="customer_comment" placeholder="Optional comment or question"></textarea><br>
<button class="btn">Acknowledge report</button>
</form>
<?php else:?>
<p class="muted">Acknowledged <?=wt_html($r['customer_acknowledged_at'])?><?php if($r['customer_comment']):?> — “<?=wt_html($r['customer_comment'])?>”<?php endif;?></p>
<?php endif;?>
</div>
<?php endforeach;?>
</div>
</div>

<script>
const c=document.getElementById('pad');
if(c){
 const ctx=c.getContext('2d'); let drawing=false, hasInk=false;
 function size(){
   const r=c.getBoundingClientRect();
   c.width=Math.floor(r.width*devicePixelRatio);
   c.height=Math.floor(r.height*devicePixelRatio);
   ctx.scale(devicePixelRatio,devicePixelRatio);
   ctx.lineWidth=2; ctx.lineCap='round';
 }
 size();
 function p(e){
   const r=c.getBoundingClientRect();
   const src=e.touches?e.touches[0]:e;
   return [src.clientX-r.left,src.clientY-r.top]
 }
 function start(e){drawing=true;hasInk=true;const[x,y]=p(e);ctx.beginPath();ctx.moveTo(x,y);e.preventDefault()}
 function move(e){if(!drawing)return;const[x,y]=p(e);ctx.lineTo(x,y);ctx.stroke();e.preventDefault()}
 function end(){drawing=false}
 c.addEventListener('mousedown',start);
 c.addEventListener('mousemove',move);
 window.addEventListener('mouseup',end);
 c.addEventListener('touchstart',start,{passive:false});
 c.addEventListener('touchmove',move,{passive:false});
 c.addEventListener('touchend',end);
 window.clearPad=()=>{ctx.clearRect(0,0,c.width,c.height);hasInk=false}
 window.prepareSignature=()=>{
   if(!hasInk){alert('Please sign in the signature box.');return false;}
   document.getElementById('signature').value=c.toDataURL('image/png');
   return true;
 }
}
</script>
</body>
</html>
