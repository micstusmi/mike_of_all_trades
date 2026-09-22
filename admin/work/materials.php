<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$id = (int)($_GET['id'] ?? 0);
$job = wt_job($pdo, $id);
$tasks = wt_job_tasks($pdo, $id);
$captureSessionId=(int)($_GET['session_id']??0);
if($captureSessionId>0){$sq=$pdo->prepare('SELECT id FROM work_sessions WHERE id=? AND job_id=?');$sq->execute([$captureSessionId,$id]);if(!$sq->fetchColumn())$captureSessionId=0;}

$q = $pdo->prepare("
    SELECT
        m.*,
        t.title AS task_title,
        rl.receipt_id AS scanned_receipt_id
    FROM work_materials m
    LEFT JOIN work_tasks t ON t.id=m.task_id
    LEFT JOIN work_receipt_lines rl ON rl.material_id=m.id
    WHERE m.job_id=?
    ORDER BY
        COALESCE(m.purchase_date, DATE(m.purchased_at)) DESC,
        m.id DESC
");
$q->execute([$id]);
$materials = $q->fetchAll(PDO::FETCH_ASSOC);
$receiptCounts = ['processing'=>0,'ready'=>0,'applied'=>0,'failed'=>0];
try {
    $rq=$pdo->prepare('SELECT status,COUNT(*) total FROM work_receipts WHERE job_id=? GROUP BY status');
    $rq->execute([$id]);
    foreach($rq->fetchAll(PDO::FETCH_ASSOC) as $row)$receiptCounts[(string)$row['status']]=(int)$row['total'];
} catch(Throwable $e) {}

$labels = [
    'already_on_site' => 'Already on site',
    'customer_will_supply' => 'Customer will supply',
    'mike_to_purchase' => 'Mike to purchase',
    'mike_has_it' => 'Mike has it',
    'maybe_required' => 'Maybe required',
    'not_required' => 'Not required',
];

$sourceLabels = [
    'supplier_purchase' => 'Supplier purchase',
    'mike_vehicle_stock' => 'Mike vehicle / stock',
    'customer_supplied' => 'Customer supplied',
    'already_on_site' => 'Already on site',
    'other' => 'Other',
];

$reimbLabels = [
    'not_applicable' => 'Not applicable',
    'reimbursement_due' => 'Reimbursement due',
    'reimbursed' => 'Reimbursed',
    'no_reimbursement_due' => 'No reimbursement due',
];

$actualTotal = 0.0;
$mikePaidTotal = 0.0;
$customerPaidTotal = 0.0;
$reimbursementDue = 0.0;
$sourceGstTotal = 0.0;

foreach ($materials as $m) {
    $cost = (float)($m['actual_cost'] ?? $m['cost'] ?? 0);

    $actualTotal += $cost;

    if (($m['paid_by'] ?? '') === 'mike') {
        $mikePaidTotal += $cost;
    }

    if (($m['paid_by'] ?? '') === 'customer') {
        $customerPaidTotal += $cost;
    }

    if (($m['reimbursement_status'] ?? '') === 'reimbursement_due') {
        $reimbursementDue += $cost;
    }

    $sourceGstTotal += (float)($m['receipt_gst_amount'] ?? 0);
}
$sourceNetTotal = max(0.0, $actualTotal - $sourceGstTotal);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Materials, receipts &amp; reimbursements — <?=wt_html($job['customer_name'])?></title>
<style>
body{font-family:system-ui;background:#f4f6f8;color:#17202a;margin:0}
.wrap{max-width:1180px;margin:auto;padding:18px}
.card{background:#fff;border-radius:14px;padding:16px;margin:12px 0;box-shadow:0 2px 10px #0001}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.grow{flex:1 1 260px}
label{font-size:12px;font-weight:800;display:block;margin-bottom:4px}
input,select,textarea{box-sizing:border-box;padding:9px;border:1px solid #cbd5dc;border-radius:8px;max-width:100%}
textarea{width:100%}
.btn{display:inline-block;background:#17202a;color:#fff;border:0;border-radius:9px;padding:10px 13px;font-weight:800;text-decoration:none;cursor:pointer}
.secondary{background:#fff;color:#17202a;border:1px solid #ccd5db}
.muted{color:#66717c;font-size:13px}
.tag{background:#edf1f4;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:800}
.item{border-top:1px solid #e3e8eb;padding:16px 0}
.item:first-child{border-top:0}
.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:9px}
.metric{background:#f6f8fa;border:1px solid #e1e6ea;border-radius:10px;padding:11px}
.metric b{display:block;font-size:20px;margin-top:4px}
.receipt{background:#f8fafb;border:1px solid #dce3e8;border-radius:10px;padding:10px;margin-top:10px}
.notice{padding:10px;border-radius:9px;background:#edf8f0;margin:10px 0}
.warning{padding:10px;border-radius:9px;background:#fff6df;margin:10px 0}
@media(max-width:800px){.metrics{grid-template-columns:1fr 1fr}.wrap{padding:11px}.row>*{flex:1 1 100%}input,select{width:100%!important}}
</style>
</head>
<body><?php $adminPageTitle='Materials, Receipts &amp; Reimbursements';$adminBreadcrumbs=['Work Tracker'=>'index.php','Materials'=>''];$adminJob=$job??null;require __DIR__.'/../../includes/admin_nav.php';?>
<div class="wrap">

<p>
<a href="manage_job.php?id=<?=$id?>">← Manage job</a>
&nbsp; · &nbsp;
<a href="closeout.php?id=<?=$id?>">Job close-out preview</a>
</p>

<h1>Materials, receipts &amp; reimbursements</h1>
<p>
<b><?=wt_html($job['customer_name'])?></b>
<?php if(!empty($job['job_title'])):?>
 — <?=wt_html($job['job_title'])?>
<?php endif;?>
</p>

<?php if(isset($_GET['added'])):?><div class="notice"><b>✓ Material added.</b></div><?php endif;?>
<?php if(isset($_GET['saved'])):?><div class="notice"><b>✓ Material updated.</b></div><?php endif;?>
<?php if(isset($_GET['receipt_saved'])):?><div class="notice"><b>✓ Receipt stored.</b></div><?php endif;?>
<?php if(isset($_GET['receipts_queued'])):?><div class="notice"><b>✓ <?=max(0,(int)$_GET['receipts_queued'])?> receipt(s) securely uploaded for background OCR.</b> You can leave this page and return later.<?php if((int)($_GET['receipt_duplicates']??0)>0):?> <?=max(0,(int)$_GET['receipt_duplicates'])?> duplicate(s) were safely skipped.<?php endif;?></div><?php endif;?>
<?php if(($_GET['receipt_worker']??'1')==='0'):?><div class="warning"><b>The receipts are safely stored, but the automatic OCR worker could not start.</b> Check WORKTRACKER_PHP_BIN or run the receipt worker manually; no receipt data has been lost.</div><?php endif;?>

<div class="metrics">
<div class="metric">Recorded actual cost<b><?=wt_money($actualTotal)?></b></div>
<div class="metric">Paid by Mike<b><?=wt_money($mikePaidTotal)?></b></div>
<div class="metric">Paid by customer<b><?=wt_money($customerPaidTotal)?></b></div>
<div class="metric">Reimbursement due<b><?=wt_money($reimbursementDue)?></b></div>
<div class="metric">GST shown on source receipts<b><?=wt_money($sourceGstTotal)?></b></div>
<div class="metric">Materials excluding shown GST<b><?=wt_money($sourceNetTotal)?></b></div>
</div>

<div class="warning">
<b>GST note:</b>
GST shown here is information copied from third-party supplier receipts.
It is not GST added by Mike Of All Trades to the customer's charge.
</div>

<div class="card" id="receipts">
<h2>📄 Scan or bulk upload receipts</h2>
<p><a class="btn" href="receipt_images.php?id=<?=$id?>">VIEW ALL RECEIPT IMAGES</a> <a class="btn secondary" href="receipt_review.php?id=<?=$id?>">REVIEW SCANNED RECEIPTS</a></p>
<p>Take receipt photos while shopping, choose several existing photos/PDFs, or upload a ZIP containing up to 60 receipt images/PDFs. OCR runs in the background and <b>nothing affects job totals until you review and approve it.</b></p>
<form method="post" action="../../api/work/upload_receipts_bulk.php" enctype="multipart/form-data">
<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="session_id" value="<?=$captureSessionId?>">
<div class="row"><div class="grow"><label>Link these receipts to a task (optional)</label><select name="task_id" style="width:100%"><option value="0">Whole job / decide during review</option><?php foreach($tasks as $t):?><option value="<?=$t['id']?>"><?=wt_html($t['title'])?></option><?php endforeach;?></select></div><div class="grow"><label>Receipt photos, PDFs or ZIP</label><input type="file" name="receipts[]" accept="image/*,.heic,.heif,.HEIC,.HEIF,application/pdf,.pdf,application/zip,.zip" multiple required style="width:100%"></div></div>
<p class="muted">Duplicates are detected using the original file contents. Each receipt stays private and must be checked against the original before approval.</p><button class="btn" type="submit">UPLOAD &amp; SCAN RECEIPTS</button>
</form>
<p id="receiptOcrStatus"><b>OCR status:</b> <?=$receiptCounts['processing']?> processing · <?=$receiptCounts['ready']?> ready to review · <?=$receiptCounts['failed']?> failed · <?=$receiptCounts['applied']?> approved</p>
<?php if($receiptCounts['ready']+$receiptCounts['failed']+$receiptCounts['applied']>0):?><p><a class="btn secondary" href="receipt_review.php?id=<?=$id?>">REVIEW SCANNED RECEIPTS</a> <a class="btn secondary" href="receipt_images.php?id=<?=$id?>">BROWSE RECEIPT IMAGES</a></p><?php endif;?>
</div>

<div class="card">
<h2>Add a material, purchase or expense</h2>

<form method="post" action="../../api/work/add_material_v8_4b.php">
<input type="hidden" name="job_id" value="<?=$id?>">

<div class="row">
<div class="grow">
<label>Description</label>
<input name="description" required style="width:100%" placeholder="Paint, timber, screws, sealant, etc">
</div>

<div class="grow">
<label>Task</label>
<select name="task_id" style="width:100%">
<option value="">Whole job / not assigned</option>
<?php foreach($tasks as $t):?>
<option value="<?=$t['id']?>"><?=wt_html($t['title'])?></option>
<?php endforeach;?>
</select>
</div>

<div>
<label>Status</label>
<select name="material_status">
<?php foreach($labels as $v=>$l):?>
<option value="<?=$v?>"><?=wt_html($l)?></option>
<?php endforeach;?>
</select>
</div>
</div>

<br>

<div class="row">
<div class="grow">
<label>Supplier / source</label>
<input name="supplier" style="width:100%" placeholder="Bunnings / Reece / customer / etc">
</div>

<div>
<label>Purchase date</label>
<input name="purchase_date" type="date">
</div>

<div>
<label>Receipt / invoice number</label>
<input name="receipt_number" style="width:160px">
</div>
</div>

<br>

<div class="row">
<div>
<label>Estimated $</label>
<input name="estimated_cost" type="number" step=".01" min="0" style="width:130px">
</div>

<div>
<label>Actual gross $</label>
<input name="actual_cost" type="number" step=".01" min="0" style="width:130px">
</div>

<div>
<label>GST shown on supplier receipt $</label>
<input name="receipt_gst_amount" type="number" step=".01" min="0" style="width:150px">
</div>

<div>
<label>Source</label>
<select name="source_type">
<?php foreach($sourceLabels as $v=>$l):?>
<option value="<?=$v?>"><?=wt_html($l)?></option>
<?php endforeach;?>
</select>
</div>
</div>

<br>

<div class="row">
<div>
<label>Paid / supplied by</label>
<select name="paid_by">
<option value="mike">Mike</option>
<option value="customer">Customer</option>
<option value="other">Other</option>
</select>
</div>

<div>
<label>Reimbursement</label>
<select name="reimbursement_status">
<?php foreach($reimbLabels as $v=>$l):?>
<option value="<?=$v?>"><?=wt_html($l)?></option>
<?php endforeach;?>
</select>
</div>

<div>
<label>Financial treatment</label>
<select name="financial_treatment">
<option value="charge_customer">Charge customer</option>
<option value="included_in_price">Included in agreed price</option>
<option value="goodwill">Goodwill — supplied at no charge</option>
<option value="rectification">Rectification — absorbed by Mike</option>
</select>
</div>
</div>

<br>

<label>Notes</label>
<textarea name="notes" rows="2" placeholder="Product code, colour, why required, replacement, etc"></textarea>

<br>
<button class="btn">Add material</button>
</form>

<p class="muted">
After adding the material, attach its receipt to the saved record below.
JPG, PNG, WEBP and PDF are supported.
</p>
</div>

<div class="card">
<h2>Materials, purchases &amp; reimbursement ledger</h2>

<?php if(!$materials):?>
<p class="muted">No materials recorded yet.</p>
<?php endif;?>

<?php foreach($materials as $m):?>
<div class="item" id="material-<?=$m['id']?>">

<form method="post" action="../../api/work/update_material_v8_4b.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="material_id" value="<?=$m['id']?>">

<div class="row">
<div class="grow">
<b><?=wt_html($m['description'])?></b>
<div class="muted">
<?=wt_html($m['task_title'] ?: 'Whole job / not assigned')?>
</div>
</div>

<span class="tag">
<?=wt_html($labels[$m['material_status'] ?? 'mike_to_purchase'] ?? $m['material_status'])?>
</span>
</div>

<br>

<div class="row">
<div class="grow">
<label>Task</label>
<select name="task_id" style="width:100%">
<option value="">Whole job / not assigned</option>
<?php foreach($tasks as $t):?>
<option
value="<?=$t['id']?>"
<?=$m['task_id']==$t['id']?'selected':''?>
>
<?=wt_html($t['title'])?>
</option>
<?php endforeach;?>
</select>
</div>

<div>
<label>Status</label>
<select name="material_status">
<?php foreach($labels as $v=>$l):?>
<option
value="<?=$v?>"
<?=($m['material_status']??'')===$v?'selected':''?>
>
<?=wt_html($l)?>
</option>
<?php endforeach;?>
</select>
</div>

<div class="grow">
<label>Supplier</label>
<input
name="supplier"
value="<?=wt_html($m['supplier']??'')?>"
style="width:100%"
>
</div>
</div>

<br>

<div class="row">
<div>
<label>Purchase date</label>
<input
name="purchase_date"
type="date"
value="<?=wt_html((string)($m['purchase_date']??''))?>"
>
</div>

<div>
<label>Receipt / invoice number</label>
<input
name="receipt_number"
value="<?=wt_html((string)($m['receipt_number']??''))?>"
>
</div>

<div>
<label>Estimated $</label>
<input
name="estimated_cost"
type="number"
step=".01"
min="0"
value="<?=wt_html((string)($m['estimated_cost']??''))?>"
style="width:130px"
>
</div>

<div>
<label>Actual gross $</label>
<input
name="actual_cost"
type="number"
step=".01"
min="0"
value="<?=wt_html((string)($m['actual_cost']??$m['cost']??''))?>"
style="width:130px"
>
</div>

<div>
<label>GST on source receipt $</label>
<input
name="receipt_gst_amount"
type="number"
step=".01"
min="0"
value="<?=wt_html((string)($m['receipt_gst_amount']??''))?>"
style="width:150px"
>
</div>
</div>

<br>

<div class="row">
<div>
<label>Source</label>
<select name="source_type">
<?php foreach($sourceLabels as $v=>$l):?>
<option
value="<?=$v?>"
<?=($m['source_type']??'')===$v?'selected':''?>
>
<?=wt_html($l)?>
</option>
<?php endforeach;?>
</select>
</div>

<div>
<label>Paid by</label>
<select name="paid_by">
<option value="mike" <?=$m['paid_by']==='mike'?'selected':''?>>Mike</option>
<option value="customer" <?=$m['paid_by']==='customer'?'selected':''?>>Customer</option>
<option value="other" <?=$m['paid_by']==='other'?'selected':''?>>Other</option>
</select>
</div>

<div>
<label>Reimbursement</label>
<select name="reimbursement_status">
<?php foreach($reimbLabels as $v=>$l):?>
<option
value="<?=$v?>"
<?=($m['reimbursement_status']??'')===$v?'selected':''?>
>
<?=wt_html($l)?>
</option>
<?php endforeach;?>
</select>
</div>

<div>
<label>Financial treatment</label>
<select name="financial_treatment">
<option
value="charge_customer"
<?=($m['financial_treatment']??'charge_customer')==='charge_customer'?'selected':''?>
>Charge customer</option>

<option
value="included_in_price"
<?=($m['financial_treatment']??'')==='included_in_price'?'selected':''?>
>Included in agreed price</option>

<option
value="goodwill"
<?=($m['financial_treatment']??'')==='goodwill'?'selected':''?>
>Goodwill — supplied at no charge</option>

<option
value="rectification"
<?=($m['financial_treatment']??'')==='rectification'?'selected':''?>
>Rectification — absorbed by Mike</option>
</select>
</div>
</div>

<br>

<label>Notes</label>
<textarea name="notes" rows="2"><?=wt_html($m['notes']??'')?></textarea>

<br>
<button class="btn">Save changes</button>
</form>

<div class="receipt">
<?php if(!empty($m['receipt_path'])):?>
<b>Receipt attached ✓</b><br>
<a
class="btn secondary"
target="_blank"
href="material_receipt.php?job_id=<?=$id?>&material_id=<?=$m['id']?>"
>
View receipt
</a>
<span class="muted">
Uploading another file will replace this receipt.
</span>
<?php elseif(!empty($m['scanned_receipt_id'])):?>
<b>Approved scanned receipt attached ✓</b><br>
<a class="btn secondary" target="_blank" href="receipt_file.php?job_id=<?=$id?>&receipt_id=<?=(int)$m['scanned_receipt_id']?>">View source receipt</a>
<?php else:?>
<b>No receipt attached</b>
<?php endif;?>

<form
method="post"
action="../../api/work/upload_material_receipt.php"
enctype="multipart/form-data"
style="margin-top:10px"
>
<input type="hidden" name="job_id" value="<?=$id?>">
<input type="hidden" name="material_id" value="<?=$m['id']?>">

<label>Attach / replace receipt</label>
<input
type="file"
name="receipt"
accept="image/*,.heic,.heif,.HEIC,.HEIF,application/pdf,.pdf"
required
>

<button class="btn" style="margin-top:7px">
Upload receipt
</button>
</form>

<div class="muted">
Choose a photo from your phone/library/files or take a new one.
Maximum 12 MB.
</div>
</div>

</div>
<?php endforeach;?>
</div>

</div>
</body>
</html>
<?php if($receiptCounts['processing']>0):?><script>
(()=>{const initialReady=<?=$receiptCounts['ready']?>;const poll=async()=>{try{const r=await fetch('../../api/work/receipt_status.php?job_id=<?=$id?>',{cache:'no-store'});const d=await r.json();if(!d.ok)return;const c=d.counts||{};const el=document.getElementById('receiptOcrStatus');if(el)el.innerHTML='<b>OCR status:</b> '+(c.processing||0)+' processing · '+(c.ready||0)+' ready to review · '+(c.failed||0)+' failed · '+(c.applied||0)+' approved';if((c.processing||0)===0||(c.ready||0)>initialReady)window.location.reload();}catch(e){}};setInterval(poll,4000);})();
</script><?php endif;?>
