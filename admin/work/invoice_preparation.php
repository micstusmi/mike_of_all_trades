<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_invoice.php';

$id = (int)($_GET['id'] ?? 0);
$job = wt_job($pdo, $id);
$zohoPackage = wt_invoice_package($pdo, $id);
$latestZohoSnapshot = null;
try {
    $zohoStmt = $pdo->prepare("SELECT * FROM work_closeout_snapshots WHERE job_id=? AND zoho_invoice_id IS NOT NULL ORDER BY id DESC LIMIT 1");
    $zohoStmt->execute([$id]);
    $latestZohoSnapshot = $zohoStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $error) {}
$defaultRate = (float)($job['agreed_hourly_rate'] ?? 0);

function ip_money(float $value): string
{
    return '$' . number_format($value, 2);
}

function ip_date(string $value): string
{
    $timestamp = strtotime($value);
    return $timestamp ? date('D j M Y', $timestamp) : $value;
}

function ip_short_text(array $values, string $fallback): string
{
    $clean = [];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '' && !in_array($value, $clean, true)) $clean[] = $value;
    }
    if (!$clean) return $fallback;
    $text = implode('; ', $clean);
    return mb_strlen($text) > 240 ? mb_substr($text, 0, 237) . '...' : $text;
}

$sessionStmt = $pdo->prepare("
    SELECT
        s.*,
        COALESCE(w.worker_name,'Mike') AS worker_name,
        COALESCE(w.hourly_rate, ?) AS effective_rate,
        t.title AS task_title,
        CASE
            WHEN s.ended_at IS NULL THEN 0
            WHEN s.session_source='retrospective'
                 AND s.retrospective_hours IS NOT NULL
                THEN s.retrospective_hours
            ELSE GREATEST(
                0,
                TIMESTAMPDIFF(SECOND,s.started_at,s.ended_at)
                - COALESCE((
                    SELECT SUM(TIMESTAMPDIFF(
                        SECOND,b.started_at,COALESCE(b.ended_at,s.ended_at)
                    ))
                    FROM work_session_breaks b
                    WHERE b.session_id=s.id
                ),0)
            ) / 3600
        END AS calculated_hours
    FROM work_sessions s
    LEFT JOIN work_workers w ON w.id=s.worker_id
    LEFT JOIN work_tasks t ON t.id=s.task_id
    WHERE s.job_id=?
    ORDER BY s.started_at,s.id
");
$sessionStmt->execute([$defaultRate, $id]);
$sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

$daily = [];
$unfinishedSessions = 0;
foreach ($sessions as $session) {
    if (empty($session['ended_at'])) {
        $unfinishedSessions++;
        continue;
    }
    if ((int)($session['billable'] ?? 0) !== 1) continue;
    $hours = max(0.0, (float)$session['calculated_hours']);
    if ($hours <= 0) continue;
    $day = date('Y-m-d', strtotime((string)$session['started_at']));
    if (!isset($daily[$day])) {
        $daily[$day] = [
            'hours' => 0.0,
            'amount' => 0.0,
            'rates' => [],
            'tasks' => [],
            'notes' => [],
            'sessions' => [],
        ];
    }
    $rate = (float)$session['effective_rate'];
    $daily[$day]['hours'] += $hours;
    $daily[$day]['amount'] += $hours * $rate;
    $daily[$day]['rates'][number_format($rate, 2, '.', '')] = true;
    if (!empty($session['task_title'])) $daily[$day]['tasks'][] = $session['task_title'];
    if (!empty($session['notes'])) $daily[$day]['notes'][] = $session['notes'];
    $daily[$day]['sessions'][] = $session;
}

$materialsStmt = $pdo->prepare("
    SELECT m.*,t.title AS task_title,
           (SELECT rl.receipt_id FROM work_receipt_lines rl
            WHERE rl.material_id=m.id ORDER BY rl.id LIMIT 1) AS scanned_receipt_id
    FROM work_materials m
    LEFT JOIN work_tasks t ON t.id=m.task_id
    WHERE m.job_id=?
      AND COALESCE(m.material_status,'')<>'not_required'
    ORDER BY COALESCE(m.purchase_date,DATE(m.purchased_at)),m.id
");
$materialsStmt->execute([$id]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

$receiptGroups = [];
foreach ($materials as $material) {
    $date = (string)($material['purchase_date'] ?: substr((string)($material['purchased_at'] ?? ''),0,10));
    $receipt = trim((string)($material['receipt_number'] ?? ''));
    $supplier = trim((string)($material['supplier'] ?? '')) ?: 'Supplier not recorded';
    $key = implode('|', [
        $date,
        mb_strtolower($supplier),
        $receipt !== '' ? mb_strtolower($receipt) : 'material-' . $material['id'],
        (string)($material['paid_by'] ?? ''),
        (string)($material['reimbursement_status'] ?? ''),
        (string)($material['financial_treatment'] ?? ''),
    ]);
    if (!isset($receiptGroups[$key])) {
        $receiptGroups[$key] = [
            'date' => $date,
            'supplier' => $supplier,
            'receipt' => $receipt,
            'paid_by' => (string)($material['paid_by'] ?? ''),
            'reimbursement' => (string)($material['reimbursement_status'] ?? 'not_applicable'),
            'treatment' => (string)($material['financial_treatment'] ?? 'charge_customer'),
            'amount' => 0.0,
            'gst_source' => 0.0,
            'items' => [],
            'ids' => [],
            'receipt_ids' => [],
        ];
    }
    $receiptGroups[$key]['amount'] += (float)($material['actual_cost'] ?? $material['cost'] ?? 0);
    $receiptGroups[$key]['gst_source'] += (float)($material['receipt_gst_amount'] ?? 0);
    $receiptGroups[$key]['items'][] = (string)$material['description'];
    $receiptGroups[$key]['ids'][] = (int)$material['id'];
    if (!empty($material['scanned_receipt_id'])) {
        $receiptGroups[$key]['receipt_ids'][(int)$material['scanned_receipt_id']] = true;
    }
}

$complimentaryStmt = $pdo->prepare("
    SELECT * FROM work_complimentary_items
    WHERE job_id=?
    ORDER BY created_at,id
");
$complimentaryStmt->execute([$id]);
$complimentary = $complimentaryStmt->fetchAll(PDO::FETCH_ASSOC);

$paymentsStmt = $pdo->prepare("
    SELECT * FROM work_payments WHERE job_id=? ORDER BY paid_at,id
");
$paymentsStmt->execute([$id]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheetImports = [];
try {
    $importStmt = $pdo->prepare("
        SELECT id,original_name,status,spreadsheet_gross_total,
               parsed_gross_total,reconciliation_difference,
               reconciliation_warnings
        FROM work_receipt_imports
        WHERE job_id=? AND status IN ('ready','applied')
        ORDER BY created_at DESC,id DESC
    ");
    $importStmt->execute([$id]);
    $spreadsheetImports = $importStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    $spreadsheetImports = [];
}

$labourTotal = (float)($job['work_already_value'] ?? 0);
$labourHours = 0.0;
foreach ($daily as $day) {
    $labourHours += $day['hours'];
    $labourTotal += $day['amount'];
}

$materialsChargeTotal = (float)($job['materials_already_value'] ?? 0);
$reimbursementTotal = 0.0;
$supplierGstTotal = 0.0;
$excludedMaterialTotal = 0.0;
foreach ($receiptGroups as &$group) {
    $isChargeable = $group['paid_by'] === 'mike'
        && $group['treatment'] === 'charge_customer'
        && in_array($group['reimbursement'], ['not_applicable','reimbursement_due'], true);
    $group['included'] = $isChargeable;
    // Mike-paid supplier purchases charged at their source-receipt cost are
    // reimbursements. The supplier GST is already inside the gross amount;
    // Mike does not add GST because Mike of All Trades is not GST registered.
    $isReimbursement = $isChargeable && $group['paid_by'] === 'mike';
    $group['invoice_type'] = $isReimbursement ? 'Reimbursement' : 'Materials';
    if ($isReimbursement) {
        $reimbursementTotal += $group['amount'];
        $supplierGstTotal += $group['gst_source'];
    } elseif ($isChargeable) {
        $materialsChargeTotal += $group['amount'];
    } else {
        $excludedMaterialTotal += $group['amount'];
    }
}
unset($group);

$paymentTotal = (float)($job['payments_received'] ?? 0);
foreach ($payments as $payment) $paymentTotal += (float)$payment['amount'];

$invoiceSubtotal = $labourTotal + $materialsChargeTotal + $reimbursementTotal;
$invoiceTotal = $invoiceSubtotal;
$balanceDue = $invoiceTotal - $paymentTotal;
$complimentaryTotal = 0.0;
foreach ($complimentary as $item) {
    $complimentaryTotal += (float)($item['estimated_value'] ?? 0);
}

$copyLines = [];
$copyLines[] = 'INVOICE PREPARATION — ' . (string)$job['customer_name'];
$copyLines[] = 'No GST has been charged by Mike of All Trades. Supplier GST shown on reimbursements is already included in the supplier receipt totals.';
$unresolvedImportDifference = 0.0;
foreach ($spreadsheetImports as $import) {
    $unresolvedImportDifference += abs((float)($import['reconciliation_difference'] ?? 0));
}
if ($unresolvedImportDifference > 0.009) {
    $copyLines[] = 'REVIEW WARNING: spreadsheet reconciliation differences total ' . ip_money($unresolvedImportDifference) . '.';
}
$copyLines[] = '';
if ((float)($job['work_already_value'] ?? 0) > 0) {
    $copyLines[] = 'Opening labour / previously recorded work — ' . ip_money((float)$job['work_already_value']);
}
foreach ($daily as $date => $day) {
    $description = ip_short_text(array_merge($day['tasks'],$day['notes']), 'General job labour');
    $copyLines[] = ip_date($date) . ' — ' . number_format($day['hours'],2) . ' hrs — ' . $description . ' — ' . ip_money($day['amount']);
}
foreach ($receiptGroups as $group) {
    if (!$group['included']) continue;
    $reference = $group['receipt'] !== '' ? ' — Receipt ' . $group['receipt'] : '';
    $gstText = $group['invoice_type'] === 'Reimbursement'
        ? ' (supplier GST included: ' . ($group['gst_source'] > 0 ? ip_money($group['gst_source']) : 'not recorded') . ')'
        : '';
    $copyLines[] = ip_date($group['date']) . ' — ' . $group['invoice_type'] . ': ' . $group['supplier'] . $reference . ' — ' . ip_money($group['amount']) . $gstText;
}
$copyLines[] = '';
$copyLines[] = 'Labour: ' . ip_money($labourTotal) . ' (' . number_format($labourHours,2) . ' tracked billable hrs)';
$copyLines[] = 'Materials: ' . ip_money($materialsChargeTotal);
$copyLines[] = 'Reimbursements: ' . ip_money($reimbursementTotal);
$copyLines[] = 'Supplier GST included in reimbursements: ' . ip_money($supplierGstTotal) . ' (not added again)';
$copyLines[] = 'TOTAL — NO GST: ' . ip_money($invoiceTotal);
$copyLines[] = 'Payments received: ' . ip_money($paymentTotal);
$copyLines[] = 'BALANCE DUE: ' . ip_money($balanceDue);
$copyText = implode("\n", $copyLines);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice preparation — <?=wt_html($job['customer_name'])?></title>
<style>
body{font-family:system-ui;background:#eef2f4;color:#17202a;margin:0}.wrap{max-width:1240px;margin:auto;padding:18px}.card{background:#fff;border-radius:14px;padding:17px;margin:13px 0;box-shadow:0 2px 10px #0001}.toplinks{display:flex;gap:14px;flex-wrap:wrap}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:9px}.metric{background:#f7f9fa;border:1px solid #dfe6ea;border-radius:10px;padding:12px}.metric b{display:block;font-size:22px;margin-top:4px}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:left;border-bottom:1px solid #e1e6ea;padding:9px;vertical-align:top}th{font-size:12px;text-transform:uppercase;color:#61707a}.money{text-align:right;white-space:nowrap}.included{color:#08742d;font-weight:800}.excluded{color:#9a3d00;font-weight:800}.notice{padding:12px;border-radius:10px;background:#fff4d6;border:1px solid #efc451}.good{background:#edf8f0;border-color:#96d0a4}.danger{background:#ffeded;border-color:#e69b9b}.btn{display:inline-block;background:#17202a;color:#fff;text-decoration:none;border:0;border-radius:9px;padding:10px 13px;font-weight:800;cursor:pointer}.secondary{background:#fff;color:#17202a;border:1px solid #cbd5dc}label{font-size:12px;font-weight:800;display:block;margin-bottom:4px}input,select,textarea{box-sizing:border-box;width:100%;padding:9px;border:1px solid #cbd5dc;border-radius:8px}.formgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.wide{grid-column:1/-1}.muted{color:#68757e;font-size:13px}.copybox{width:100%;min-height:260px;font-family:ui-monospace,monospace}.details{font-size:12px;color:#63717a}.no-gst{font-weight:800;background:#edf8f0;padding:12px;border-radius:10px}@media(max-width:760px){.wrap{padding:10px}.formgrid{grid-template-columns:1fr}.metrics{grid-template-columns:1fr 1fr}}@media print{body{background:#fff}.no-print{display:none!important}.card{box-shadow:none;border:1px solid #ddd}.wrap{max-width:none}}
</style>
</head>
<body>
<div class="wrap">
<div class="toplinks no-print"><a href="manage_job.php?id=<?=$id?>">← Manage job</a><a href="closeout.php?id=<?=$id?>">Close-out preview</a><a href="materials.php?id=<?=$id?>">Materials / receipts</a></div>
<h1>Invoice preparation</h1>
<p><b><?=wt_html($job['customer_name'])?></b><?=!empty($job['job_title'])?' — '.wt_html($job['job_title']):''?></p>
<p class="no-gst">No GST has been charged by Mike of All Trades. Supplier GST shown on reimbursements is copied from the original supplier tax invoices and is already included in the reimbursement totals.</p>

<?php $customerPreviewUrl=wt_base_url().'/work/invoice_preview.php?t='.urlencode((string)$job['public_token']);?>
<div class="card no-print"><h2>Customer read-only invoice preview</h2><p class="muted">This private link lets the customer review the invoice-style summary and source receipts. It cannot edit your records.</p><input id="customer-preview-url" readonly value="<?=wt_html($customerPreviewUrl)?>"><br><br><button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('customer-preview-url').value).then(()=>this.textContent='LINK COPIED')">Copy customer preview link</button> <a class="btn secondary" target="_blank" href="<?=wt_html($customerPreviewUrl)?>">Open preview</a></div>

<?php if(($_GET['free_added'] ?? '')==='1'):?><div class="notice good"><b>✓ Free material recorded.</b> It is shown as customer value but excluded from the amount payable.</div><?php endif;?>
<?php if(($_GET['group_excluded'] ?? '')==='1'):?><div class="notice good"><b>✓ Duplicate group excluded.</b> Every underlying item in that grouped charge was removed from the invoice total. Its audit history was preserved.</div><?php endif;?>
<?php if($unfinishedSessions>0):?><div class="notice danger"><b>Review required:</b> <?=$unfinishedSessions?> work session(s) are still running or missing a finish time and are not included below.</div><?php endif;?>
<?php foreach($spreadsheetImports as $import): $difference=(float)($import['reconciliation_difference'] ?? 0); if(abs($difference)<0.009) continue;?>
<div class="notice danger"><b>Do not invoice this reimbursement without checking it:</b> <?=wt_html((string)$import['original_name'])?> has a spreadsheet total of <?=ip_money((float)$import['spreadsheet_gross_total'])?>, but the approved parsed lines total <?=ip_money((float)$import['parsed_gross_total'])?>. Difference: <b><?=ip_money(abs($difference))?></b>. <a href="receipt_import_review.php?id=<?=$id?>">Open spreadsheet import record</a>.</div>
<?php endforeach;?>
<div class="notice"><b>Draft only:</b> Nothing on this page creates or sends a Zoho invoice. Review the underlying sessions and material records before billing.</div>

<div class="metrics">
<div class="metric">Billable labour<b><?=ip_money($labourTotal)?></b><span><?=number_format($labourHours,2)?> tracked hours</span></div>
<div class="metric">Chargeable materials<b><?=ip_money($materialsChargeTotal)?></b></div>
<div class="metric">Reimbursements due<b><?=ip_money($reimbursementTotal)?></b></div>
<div class="metric">Supplier GST included<b><?=ip_money($supplierGstTotal)?></b><span>Already inside reimbursements</span></div>
<div class="metric">Invoice total — no GST<b><?=ip_money($invoiceTotal)?></b></div>
<div class="metric">Payments recorded<b><?=ip_money($paymentTotal)?></b></div>
<div class="metric">Balance due<b><?=ip_money($balanceDue)?></b></div>
</div>

<div class="card">
<h2>Labour grouped by day</h2>
<div class="scroll"><table><thead><tr><th>Date</th><th>Invoice description</th><th class="money">Hours</th><th class="money">Rate</th><th class="money">Amount</th><th>Audit</th></tr></thead><tbody>
<?php if((float)($job['work_already_value'] ?? 0)>0):?><tr><td>Opening entry</td><td>Previously recorded work</td><td class="money">—</td><td class="money">—</td><td class="money"><?=ip_money((float)$job['work_already_value'])?></td><td>Review Pricing &amp; agreement</td></tr><?php endif;?>
<?php foreach($daily as $date=>$day): $rates=array_keys($day['rates']); $description=ip_short_text(array_merge($day['tasks'],$day['notes']),'General job labour');?>
<tr><td><?=wt_html(ip_date($date))?></td><td><?=wt_html($description)?><details><summary class="details">Show <?=count($day['sessions'])?> source session(s)</summary><?php foreach($day['sessions'] as $source):?><div class="details"><?=number_format((float)$source['calculated_hours'],2)?> hrs — <?=wt_html((string)($source['task_title'] ?: $source['category']))?><?=!empty($source['notes'])?' — '.wt_html($source['notes']):''?></div><?php endforeach;?></details></td><td class="money"><?=number_format($day['hours'],2)?></td><td class="money"><?=count($rates)===1?ip_money((float)$rates[0]):'Mixed'?></td><td class="money"><b><?=ip_money($day['amount'])?></b></td><td><a href="manage_job.php?id=<?=$id?>#work-history">Edit sessions</a></td></tr>
<?php endforeach;?>
<?php if(!$daily && (float)($job['work_already_value'] ?? 0)<=0):?><tr><td colspan="6">No completed billable labour has been recorded.</td></tr><?php endif;?>
</tbody><tfoot><tr><th colspan="2">Labour total</th><th class="money"><?=number_format($labourHours,2)?></th><th></th><th class="money"><?=ip_money($labourTotal)?></th><th></th></tr></tfoot></table></div>
</div>

<div class="card">
<h2>Materials and reimbursements grouped by receipt</h2>
<p class="muted">Supplier GST is copied from the original tax invoice and is already included in the gross reimbursement. It is not GST charged by Mike and is never added a second time.</p>
<div class="scroll"><table><thead><tr><th>Status</th><th>Date</th><th>Type</th><th>Supplier / receipt</th><th>Paid by</th><th class="money">Net</th><th class="money">Supplier GST</th><th class="money">Gross due</th><th>Actions</th></tr></thead><tbody>
<?php foreach($receiptGroups as $group):?>
<tr><td class="<?=$group['included']?'included':'excluded'?>"><?=$group['included']?'INCLUDED':'EXCLUDED'?></td><td><?=wt_html(ip_date($group['date']))?></td><td><?=wt_html($group['invoice_type'])?></td><td><b><?=wt_html($group['supplier'])?></b><?=($group['receipt']!=='')?'<br>Receipt '.wt_html($group['receipt']):''?><details><summary class="details"><?=count($group['items'])?> underlying item(s)</summary><div class="details"><?=wt_html(implode('; ',$group['items']))?></div></details></td><td><?=wt_html(ucfirst($group['paid_by']))?></td><td class="money"><?=ip_money(max(0,$group['amount']-$group['gst_source']))?></td><td class="money"><?=$group['gst_source']>0?ip_money($group['gst_source']):'<span class="excluded">Not recorded</span>'?></td><td class="money"><b><?=ip_money($group['amount'])?></b></td><td><?php foreach(array_keys($group['receipt_ids']) as $receiptId):?><a target="_blank" href="receipt_file.php?job_id=<?=$id?>&amp;receipt_id=<?=$receiptId?>">View receipt</a><br><?php endforeach;?><a href="materials.php?id=<?=$id?>#material-<?=$group['ids'][0]?>">Review/edit GST</a><?php if($group['included']):?><form class="no-print" method="post" action="../../api/work/exclude_material_group.php" style="margin-top:8px" onsubmit="return confirm('Exclude this entire grouped charge of <?=wt_html(ip_money($group['amount']))?> as a duplicate? All <?=count($group['ids'])?> underlying item(s) will be removed from the invoice total. The audit history and any receipt image will be preserved.');"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="material_ids" value="<?=wt_html(implode(',', $group['ids']))?>"><input type="hidden" name="group_label" value="<?=wt_html($group['supplier'].($group['receipt']!==''?' receipt '.$group['receipt']:'').' '.ip_money($group['amount']))?>"><button class="btn" style="background:#a31919;padding:7px 9px;font-size:12px">EXCLUDE DUPLICATE</button></form><?php endif;?></td></tr>
<?php endforeach;?>
<?php if(!$receiptGroups):?><tr><td colspan="9">No material records have been entered.</td></tr><?php endif;?>
</tbody><tfoot><tr><th colspan="5">Included totals</th><th class="money"><?=ip_money(($materialsChargeTotal+$reimbursementTotal)-$supplierGstTotal)?></th><th class="money"><?=ip_money($supplierGstTotal)?></th><th class="money"><?=ip_money($materialsChargeTotal+$reimbursementTotal)?></th><th></th></tr></tfoot></table></div>
<?php if($excludedMaterialTotal>0):?><p class="notice"><b><?=ip_money($excludedMaterialTotal)?> of material records are excluded</b> because they were customer-paid, already reimbursed, included in the agreed price, goodwill or rectification. Check these classifications before billing.</p><?php endif;?>
</div>

<div class="card" id="free-materials">
<h2>Add material supplied free from existing stock</h2>
<p class="muted">Use this for plaster, filler, screws, paint or other stock already in your vehicle. Enter only the estimated value of the quantity actually used—not the replacement cost of a full container.</p>
<form method="post" action="../../api/work/add_complimentary_item.php">
<input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="return_to" value="invoice_preparation"><input type="hidden" name="no_charge_reason" value="goodwill"><input type="hidden" name="item_type" value="material"><input type="hidden" name="labour_value" value="0">
<div class="formgrid"><div class="wide"><label>Description shown to customer</label><input name="description" required placeholder="Complimentary plaster materials supplied from existing vehicle stock"></div><div class="wide"><label>Materials used / supplied</label><textarea name="material_details" required rows="3" placeholder="Quick-drying plaster base coat; ultra-smooth top coat plaster"></textarea></div><div><label>Estimated value of quantity used $</label><input name="material_value" type="number" min="0" step="0.01" required></div><div><label>Explanation / context</label><input name="note" placeholder="Supplied from Mike's existing stock at no charge"></div></div><br><button class="btn">Add free stock material</button>
</form>
</div>

<div class="card">
<h2>Provided at no charge</h2>
<?php if(!$complimentary):?><p class="muted">No goodwill, free-extra or rectification value has been recorded.</p><?php else:?><div class="scroll"><table><thead><tr><th>Reason</th><th>Type</th><th>Description</th><th>Details</th><th class="money">Customer value</th></tr></thead><tbody><?php foreach($complimentary as $item):?><tr><td><?=wt_html(str_replace('_',' ',(string)$item['no_charge_reason']))?></td><td><?=wt_html((string)$item['item_type'])?></td><td><?=wt_html((string)$item['description'])?></td><td><?=wt_html((string)($item['material_details'] ?? $item['note'] ?? ''))?></td><td class="money"><?=ip_money((float)($item['estimated_value'] ?? 0))?><br><span class="excluded">NOT CHARGED</span></td></tr><?php endforeach;?></tbody><tfoot><tr><th colspan="4">Value supplied at no charge</th><th class="money"><?=ip_money($complimentaryTotal)?></th></tr></tfoot></table></div><?php endif;?>
</div>

<div class="card">
<h2>Invoice summary</h2>
<div class="scroll"><table><tr><td>Labour</td><td class="money"><?=ip_money($labourTotal)?></td></tr><tr><td>Materials</td><td class="money"><?=ip_money($materialsChargeTotal)?></td></tr><tr><td>Reimbursements (gross supplier receipts)</td><td class="money"><?=ip_money($reimbursementTotal)?></td></tr><tr><td>Supplier GST included in reimbursements (information only)</td><td class="money"><?=ip_money($supplierGstTotal)?></td></tr><tr><th>Invoice total — no GST added by Mike</th><th class="money"><?=ip_money($invoiceTotal)?></th></tr><tr><td>Payments received</td><td class="money">−<?=ip_money($paymentTotal)?></td></tr><tr><th>Balance due</th><th class="money"><?=ip_money($balanceDue)?></th></tr></table></div>
<p class="no-gst">No GST has been charged by Mike of All Trades. Supplier GST is disclosed from the original tax invoices and remains included in the reimbursement amounts.</p>
</div>

<div class="card no-print" id="zoho-invoice">
<h2>Zoho invoice</h2>
<p class="muted">Creating a draft does not email the customer. Review the draft in Zoho, then return here and use the separate send button.</p>
<?php if(($_GET['zoho_created']??'')==='1'):?><div class="notice good"><b>✓ Zoho draft created.</b> Nothing has been emailed yet.</div><?php endif;?>
<?php if(($_GET['zoho_existing']??'')==='1'):?><div class="notice"><b>An identical Zoho draft already exists.</b> A duplicate was not created.</div><?php endif;?>
<?php if(($_GET['zoho_sent']??'')==='1'):?><div class="notice good"><b>✓ Zoho invoice emailed.</b> The customer was sent the Zoho invoice and available supplier receipt attachments.</div><?php endif;?>
<?php if(($_GET['zoho_already_sent']??'')==='1'):?><div class="notice"><b>This invoice was already sent.</b> It was not emailed twice.</div><?php endif;?>
<?php if(isset($_GET['zoho_error'])):?><div class="notice danger"><b>Zoho action stopped:</b> <?=wt_html((string)$_GET['zoho_error'])?></div><?php endif;?>

<?php if($zohoPackage['warnings']['unfinished_sessions']>0):?><div class="notice danger">Finish <?=$zohoPackage['warnings']['unfinished_sessions']?> running or incomplete session(s) before creating the invoice.</div><?php endif;?>
<?php if($zohoPackage['warnings']['missing_supplier_gst']):?><div class="notice danger"><b>Supplier GST must be entered before emailing:</b> <?=wt_html(implode('; ',$zohoPackage['warnings']['missing_supplier_gst']))?>. You may create and inspect the draft now, but sending will remain blocked.</div><?php endif;?>
<?php if($zohoPackage['warnings']['reconciliation']):?><div class="notice danger"><b>Spreadsheet reconciliation remains unresolved.</b> Draft creation is allowed for inspection; sending is blocked until corrected.</div><?php endif;?>

<?php if($latestZohoSnapshot):
    $zohoMeta=json_decode((string)($latestZohoSnapshot['zoho_response_json']??''),true);if(!is_array($zohoMeta))$zohoMeta=[];
    $zohoNumber=(string)($zohoMeta['invoice_number']??$zohoMeta['create_response']['invoice']['invoice_number']??'');
?>
<p><b>Zoho draft:</b> <?=wt_html($zohoNumber!==''?$zohoNumber:(string)$latestZohoSnapshot['zoho_invoice_id'])?><br><b>Status:</b> <?=!empty($latestZohoSnapshot['sent_to_zoho_at'])?'EMAILED '.wt_html((string)$latestZohoSnapshot['sent_to_zoho_at']):'DRAFT — NOT EMAILED'?></p>
<a class="btn secondary" target="_blank" href="https://invoice.zoho.com.au/app#/invoices/<?=rawurlencode((string)$latestZohoSnapshot['zoho_invoice_id'])?>">Open draft in Zoho</a>
<?php if(empty($latestZohoSnapshot['sent_to_zoho_at'])):?><form method="post" action="../../api/work/send_zoho_invoice.php" style="display:inline" onsubmit="return confirm('EMAIL this Zoho invoice to <?=wt_html((string)$job['customer_email'])?> now? This action sends the invoice to the customer.');"><input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="snapshot_id" value="<?=(int)$latestZohoSnapshot['id']?>"><button class="btn" type="submit">SEND ZOHO INVOICE TO CUSTOMER</button></form><?php endif;?>
<?php else:?>
<form method="post" action="../../api/work/create_zoho_invoice.php" onsubmit="return confirm('Create a Zoho DRAFT from the figures shown above? This will not email the customer.');"><input type="hidden" name="job_id" value="<?=$id?>"><button class="btn" type="submit" <?=$zohoPackage['warnings']['unfinished_sessions']>0?'disabled':''?>>CREATE ZOHO DRAFT — DO NOT EMAIL</button></form>
<?php endif;?>
</div>

<div class="card no-print">
<h2>Copy-ready billing summary</h2>
<textarea id="copy-summary" class="copybox" readonly><?=wt_html($copyText)?></textarea><br><br><button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('copy-summary').value).then(()=>this.textContent='COPIED')">Copy billing summary</button> <button class="btn secondary" type="button" onclick="window.print()">Print / Save PDF</button>
</div>
</div>
</body>
</html>
