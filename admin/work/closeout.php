<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_GET['id'] ?? 0);
$job = wt_job($pdo, $id);
$tot = wt_totals($pdo, $id);
$tasks = wt_job_tasks($pdo, $id);

$q = $pdo->prepare("
    SELECT
        m.*,
        t.title AS task_title
    FROM work_materials m
    LEFT JOIN work_tasks t ON t.id=m.task_id
    WHERE m.job_id=?
    ORDER BY
        COALESCE(m.purchase_date, DATE(m.purchased_at)),
        m.id
");
$q->execute([$id]);
$materials = $q->fetchAll(PDO::FETCH_ASSOC);

$q = $pdo->prepare("
    SELECT *
    FROM work_payments
    WHERE job_id=?
    ORDER BY paid_at,id
");
$q->execute([$id]);
$payments = $q->fetchAll(PDO::FETCH_ASSOC);

$q = $pdo->prepare("
    SELECT
        s.*,
        COALESCE(w.worker_name,'Mike') AS worker_name,
        t.title AS task_title
    FROM work_sessions s
    LEFT JOIN work_workers w ON w.id=s.worker_id
    LEFT JOIN work_tasks t ON t.id=s.task_id
    WHERE s.job_id=?
    ORDER BY s.started_at,s.id
");
$q->execute([$id]);
$sessions = $q->fetchAll(PDO::FETCH_ASSOC);

$q = $pdo->prepare("
    SELECT
        p.*,
        t.title AS task_title
    FROM work_task_photos p
    LEFT JOIN work_tasks t ON t.id=p.task_id
    WHERE p.job_id=?
    ORDER BY p.created_at,p.id
");
$q->execute([$id]);
$photos = $q->fetchAll(PDO::FETCH_ASSOC);

$sourceGst = 0.0;
$reimbursementDue = 0.0;

foreach ($materials as $m) {
    $sourceGst += (float)($m['receipt_gst_amount'] ?? 0);

    if (($m['reimbursement_status'] ?? '') === 'reimbursement_due') {
        $reimbursementDue +=
            (float)($m['actual_cost'] ?? $m['cost'] ?? 0);
    }
}

$taskCounts = [
    'total' => 0,
    'completed' => 0,
    'active' => 0,
];

foreach ($tasks as $t) {
    if (($t['status'] ?? '') === 'cancelled') continue;

    $taskCounts['total']++;

    if (($t['status'] ?? '') === 'completed') {
        $taskCounts['completed']++;
    } else {
        $taskCounts['active']++;
    }
}

function co_money(float $v): string {
    return '$' . number_format($v, 2);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Job close-out preview</title>
<style>
body{font-family:system-ui;background:#eef2f4;color:#17202a;margin:0}
.wrap{max-width:1180px;margin:auto;padding:18px}
.card{background:#fff;border-radius:14px;padding:16px;margin:12px 0;box-shadow:0 2px 10px #0001}
.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.metric{background:#f7f9fa;border:1px solid #e0e6ea;border-radius:10px;padding:12px}
.metric b{font-size:22px;display:block;margin-top:4px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;border-bottom:1px solid #e1e6ea;padding:9px;vertical-align:top}
th{font-size:12px;text-transform:uppercase;color:#61707a}
.btn{display:inline-block;background:#17202a;color:#fff;text-decoration:none;border:0;border-radius:9px;padding:10px 13px;font-weight:800}
.secondary{background:#fff;color:#17202a;border:1px solid #ccd5db}
.warn{background:#fff6df}
.good{background:#edf8f0}
.muted{color:#68757e;font-size:13px}
.money{text-align:right;white-space:nowrap}
.status{font-weight:800}
@media(max-width:800px){.metrics{grid-template-columns:1fr 1fr}.wrap{padding:10px}.scroll{overflow:auto}table{min-width:700px}}
@media print{body{background:#fff}.no-print{display:none!important}.card{box-shadow:none;border:1px solid #ddd;break-inside:avoid}.wrap{max-width:none}}
</style>
</head>
<body>
<div class="wrap">

<div class="no-print">
<a href="manage_job.php?id=<?=$id?>">← Manage job</a>
&nbsp; · &nbsp;
<a href="materials.php?id=<?=$id?>">Materials / receipts</a>
</div>

<h1>Job Close-Out Preview</h1>

<p>
<b><?=wt_html($job['customer_name'])?></b>
<?php if(!empty($job['job_title'])):?>
 — <?=wt_html($job['job_title'])?>
<?php endif;?>
</p>

<p class="muted">
Review this record before creating or sending any final invoice.
Nothing on this page sends anything to Zoho.
</p>

<div class="metrics">
<div class="metric">
Labour to date
<b><?=co_money((float)$tot['labour'])?></b>
</div>

<div class="metric">
Materials charged
<b><?=co_money((float)$tot['materials'])?></b>
</div>

<div class="metric">
Payments / credits
<b><?=co_money((float)$tot['payments'])?></b>
</div>

<div class="metric">
Current outstanding
<b><?=co_money((float)$tot['outstanding'])?></b>
</div>
</div>

<div class="card">
<h2>Close-out readiness</h2>

<p>
Tasks:
<b><?=$taskCounts['completed']?> / <?=$taskCounts['total']?> completed</b>
<?php if($taskCounts['active'] > 0):?>
 — <span class="status"><?=$taskCounts['active']?> still incomplete</span>
<?php endif;?>
</p>

<p>
Recorded sessions: <b><?=count($sessions)?></b><br>
Material records: <b><?=count($materials)?></b><br>
Payments recorded: <b><?=count($payments)?></b><br>
Before/after photos: <b><?=count($photos)?></b>
</p>

<?php if($taskCounts['active'] > 0):?>
<div class="warn card">
<b>Review required:</b>
the job still contains incomplete tasks.
This does not prevent review, but it should be resolved before final invoicing.
</div>
<?php else:?>
<div class="good card">
<b>✓ All non-cancelled tasks are marked complete.</b>
</div>
<?php endif;?>

<button class="btn secondary no-print" onclick="window.print()">
Print / Save PDF
</button>
</div>

<div class="card">
<h2>Accounting reconciliation</h2>

<div class="scroll">
<table>
<tr>
<th>Component</th>
<th class="money">Amount</th>
<th>Explanation</th>
</tr>

<tr>
<td>Opening / pre-tracker labour</td>
<td class="money"><?=co_money((float)($job['work_already_value'] ?? 0))?></td>
<td>Work value entered before or outside detailed Work Tracker sessions.</td>
</tr>

<tr>
<td>Tracked labour + opening labour</td>
<td class="money"><?=co_money((float)$tot['labour'])?></td>
<td>Billable recorded sessions using the applicable worker/hourly rate.</td>
</tr>

<tr>
<td>Opening / pre-tracker materials</td>
<td class="money"><?=co_money((float)($job['materials_already_value'] ?? 0))?></td>
<td>Material value entered before detailed material records.</td>
</tr>

<tr>
<td>Materials charged + opening materials</td>
<td class="money"><?=co_money((float)$tot['materials'])?></td>
<td>Mike-paid material records plus any opening material value.</td>
</tr>

<tr>
<td><b>Job total to date</b></td>
<td class="money"><b><?=co_money((float)$tot['total'])?></b></td>
<td>Labour plus chargeable materials.</td>
</tr>

<tr>
<td>Opening / legacy payments</td>
<td class="money"><?=co_money((float)($job['payments_received'] ?? 0))?></td>
<td>Payments entered before detailed payment records.</td>
</tr>

<tr>
<td><b>Total payments / credits</b></td>
<td class="money"><b><?=co_money((float)$tot['payments'])?></b></td>
<td>Includes deposits and progress payments recorded against this job.</td>
</tr>

<tr>
<td><b>Outstanding</b></td>
<td class="money"><b><?=co_money((float)$tot['outstanding'])?></b></td>
<td>Current job total less payments/credits.</td>
</tr>
</table>
</div>

<p class="muted">
GST appearing on supplier receipts:
<b><?=co_money($sourceGst)?></b>.
This is source-receipt information only and is not GST added to Mike Of All Trades charges.
</p>

<?php if($reimbursementDue > 0):?>
<p>
Materials currently marked reimbursement due:
<b><?=co_money($reimbursementDue)?></b>
</p>
<?php endif;?>
</div>

<div class="card">
<h2>Payments / credits</h2>

<?php if(!$payments):?>
<p class="muted">No detailed payments recorded.</p>
<?php else:?>

<div class="scroll">
<table>
<tr>
<th>Date</th>
<th>Type</th>
<th>Method</th>
<th>Reference</th>
<th>Notes</th>
<th class="money">Amount</th>
</tr>

<?php foreach($payments as $p):?>
<tr>
<td><?=wt_html($p['paid_at'])?></td>
<td><?=wt_html(str_replace('_',' ',(string)$p['payment_type']))?></td>
<td><?=wt_html((string)($p['method'] ?? ''))?></td>
<td><?=wt_html((string)($p['source_reference'] ?? ''))?></td>
<td><?=wt_html((string)($p['notes'] ?? ''))?></td>
<td class="money"><?=co_money((float)$p['amount'])?></td>
</tr>
<?php endforeach;?>
</table>
</div>
<?php endif;?>

<h3>Add payment / credit</h3>

<form method="post" action="../../api/work/add_payment.php">
<input type="hidden" name="job_id" value="<?=$id?>">

<p>
<label>Amount $<br>
<input name="amount" type="number" min=".01" step=".01" required>
</label>
</p>

<p>
<label>Type<br>
<select name="payment_type">
<option value="deposit">Deposit / credit</option>
<option value="progress">Progress payment</option>
<option value="final">Final payment</option>
<option value="other">Other</option>
</select>
</label>
</p>

<p>
<label>Method / source<br>
<input name="method" placeholder="Airtasker / bank transfer / cash / card">
</label>
</p>

<p>
<label>Source reference<br>
<input name="source_reference" placeholder="Airtasker task ID / transfer reference / receipt number">
</label>
</p>

<p>
<label>Paid at<br>
<input name="paid_at" type="datetime-local">
</label>
</p>

<p>
<label>Notes<br>
<textarea name="notes" rows="2" placeholder="e.g. Original Airtasker deposit credited against final job balance"></textarea>
</label>
</p>

<button class="btn">Record payment / credit</button>
</form>
</div>

<div class="card">
<h2>Tasks</h2>

<div class="scroll">
<table>
<tr>
<th>Task</th>
<th>Status</th>
<th>Estimate</th>
<th>Tracked</th>
</tr>

<?php foreach($tasks as $t):
$low = (float)($t['mike_estimate_low'] ?? 0);
$high = (float)($t['mike_estimate_high'] ?? 0);

if($low <= 0 && $high <= 0){
    $low = (float)($t['ai_estimate_low'] ?? 0);
    $high = (float)($t['ai_estimate_high'] ?? 0);
}
?>
<tr>
<td><?=wt_html($t['title'])?></td>
<td><?=wt_html(str_replace('_',' ',(string)$t['status']))?></td>
<td>
<?php if($low > 0 || $high > 0):?>
<?=number_format($low,2)?>–<?=number_format($high,2)?> h
<?php else:?>
—
<?php endif;?>
</td>
<td><?=number_format((float)($t['tracked_hours'] ?? 0),2)?> h</td>
</tr>
<?php endforeach;?>
</table>
</div>
</div>

<div class="card">
<h2>Materials / receipts</h2>

<?php if(!$materials):?>
<p class="muted">No materials recorded.</p>
<?php else:?>

<div class="scroll">
<table>
<tr>
<th>Date</th>
<th>Description</th>
<th>Supplier</th>
<th>Task</th>
<th>Paid by</th>
<th>Receipt</th>
<th class="money">Gross</th>
<th class="money">GST on source receipt</th>
</tr>

<?php foreach($materials as $m):?>
<tr>
<td><?=wt_html((string)($m['purchase_date'] ?: date('Y-m-d',strtotime($m['purchased_at']))))?></td>
<td><?=wt_html($m['description'])?></td>
<td><?=wt_html((string)($m['supplier'] ?? ''))?></td>
<td><?=wt_html((string)($m['task_title'] ?? ''))?></td>
<td><?=wt_html((string)$m['paid_by'])?></td>
<td>
<?php if(!empty($m['receipt_path'])):?>
<a
target="_blank"
href="material_receipt.php?job_id=<?=$id?>&material_id=<?=$m['id']?>"
>View</a>
<?php else:?>
—
<?php endif;?>
</td>
<td class="money">
<?=co_money((float)($m['actual_cost'] ?? $m['cost'] ?? 0))?>
</td>
<td class="money">
<?=co_money((float)($m['receipt_gst_amount'] ?? 0))?>
</td>
</tr>
<?php endforeach;?>
</table>
</div>
<?php endif;?>
</div>

<div class="card">
<h2>Recorded work sessions</h2>

<div class="scroll">
<table>
<tr>
<th>Started</th>
<th>Ended</th>
<th>Worker</th>
<th>Category</th>
<th>Task</th>
<th>Billable</th>
<th>Notes</th>
</tr>

<?php foreach($sessions as $s):?>
<tr>
<td><?=wt_html(wt_melbourne_time($s['started_at']))?></td>
<td>
<?=!empty($s['ended_at'])
    ? wt_html(wt_melbourne_time($s['ended_at']))
    : 'Still running'?>
</td>
<td><?=wt_html($s['worker_name'])?></td>
<td><?=wt_html((string)$s['category'])?></td>
<td><?=wt_html((string)($s['task_title'] ?? ''))?></td>
<td><?=$s['billable'] ? 'Yes' : 'No'?></td>
<td><?=wt_html((string)($s['notes'] ?? ''))?></td>
</tr>
<?php endforeach;?>
</table>
</div>
</div>

<div class="card">
<h2>Before / after photo record</h2>

<p>
Before photos:
<b><?=count(array_filter($photos,fn($p)=>$p['photo_type']==='before'))?></b>
&nbsp; · &nbsp;
After photos:
<b><?=count(array_filter($photos,fn($p)=>$p['photo_type']==='after'))?></b>
</p>

<p class="muted">
Original task photos remain stored separately from this accounting preview.
</p>
</div>


<div class="card">
<h2>Close-out snapshot</h2>

<p>
Create a frozen record of the figures and supporting job information
you have reviewed.
</p>

<p class="muted">
If something is wrong, correct the original Work Tracker record first
—for example a work session, material purchase, receipt or payment—
then return here and create a fresh snapshot.
</p>

<form
    method="post"
    action="../../api/work/save_closeout_snapshot.php"
>
<input
    type="hidden"
    name="job_id"
    value="<?=$id?>"
>

<label for="correction_notes">
    <b>Review / correction notes</b>
</label>

<textarea
    id="correction_notes"
    name="correction_notes"
    rows="4"
    style="width:100%;box-sizing:border-box;margin:8px 0 14px"
    placeholder="Example: Added missing Bunnings receipt; corrected Tuesday finish time; recorded Airtasker deposit."
></textarea>

<div style="display:flex;gap:8px;flex-wrap:wrap">

<button
    class="btn"
    type="submit"
    name="action"
    value="save_draft"
>
Save draft snapshot
</button>

<button
    class="btn"
    type="submit"
    name="action"
    value="mark_reviewed"
>
Mark reviewed
</button>

<button
    class="btn"
    type="submit"
    name="action"
    value="approve"
    onclick="return confirm(
        'Approve this close-out snapshot? ' +
        'This freezes the current reviewed figures as an approved record.'
    )"
>
Approve close-out
</button>

</div>
</form>

<?php
$coSnapshots = $pdo->prepare("
    SELECT
        id,
        status,
        labour_amount,
        materials_amount,
        gross_job_amount,
        payments_amount,
        outstanding_amount,
        correction_notes,
        created_at,
        reviewed_at,
        approved_at,
        sent_to_zoho_at
    FROM work_closeout_snapshots
    WHERE job_id=?
    ORDER BY id DESC
    LIMIT 20
");
$coSnapshots->execute([$id]);
$coSnapshots = $coSnapshots->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if($coSnapshots):?>

<h3 style="margin-top:22px">Snapshot history</h3>

<div class="scroll">
<table>
<tr>
<th>#</th>
<th>Status</th>
<th>Created</th>
<th class="money">Labour</th>
<th class="money">Materials</th>
<th class="money">Job total</th>
<th class="money">Payments</th>
<th class="money">Outstanding</th>
<th>Notes</th>
</tr>

<?php foreach($coSnapshots as $snap):?>
<tr>
<td><?= (int)$snap['id'] ?></td>

<td>
<b><?=wt_html(
    strtoupper(
        str_replace(
            '_',
            ' ',
            (string)$snap['status']
        )
    )
)?></b>
</td>

<td><?=wt_html(
    wt_melbourne_time($snap['created_at'])
)?></td>

<td class="money">
<?=co_money((float)$snap['labour_amount'])?>
</td>

<td class="money">
<?=co_money((float)$snap['materials_amount'])?>
</td>

<td class="money">
<?=co_money((float)$snap['gross_job_amount'])?>
</td>

<td class="money">
<?=co_money((float)$snap['payments_amount'])?>
</td>

<td class="money">
<?=co_money((float)$snap['outstanding_amount'])?>
</td>

<td>
<?=wt_html(
    (string)($snap['correction_notes'] ?? '')
)?>
</td>
</tr>
<?php endforeach;?>

</table>
</div>

<?php else:?>

<p class="muted">
No close-out snapshots have been created yet.
</p>

<?php endif;?>

<p class="muted" style="margin-top:14px">
Approving a snapshot does not create an invoice or contact Zoho.
</p>

</div>

<div class="card warn">
<h2>Zoho invoice</h2>

<p>
<b>Preview only.</b>
No Zoho invoice has been created or sent from this page.
</p>

<p>
The eventual Zoho invoice should contain a concise commercial summary,
while this Work Tracker remains the detailed supporting job record.
</p>

<button class="btn" disabled>
Send to Zoho — not enabled yet
</button>
</div>

</div>
</body>
</html>
