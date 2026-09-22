<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';

$jobId = (int)($_GET['id'] ?? 0);
$job = wt_job($pdo, $jobId);
$tasks = wt_job_tasks($pdo, $jobId, false);
$query = $pdo->prepare('SELECT * FROM work_receipt_imports WHERE job_id=? ORDER BY created_at DESC,id DESC');
$query->execute([$jobId]);
$imports = $query->fetchAll(PDO::FETCH_ASSOC);
$hasProcessing = false;
foreach ($imports as $importRow) {
    if ((string)$importRow['status'] === 'processing') {
        $hasProcessing = true;
        break;
    }
}

function irh(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function irm(mixed $value): string
{
    return $value === null || $value === '' ? '—' : '$' . number_format((float)$value, 2);
}

function irdate(mixed $value): string
{
    $date = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
}

function irpaid(mixed $value): string
{
    return in_array($value, ['mike', 'customer', 'other'], true) ? (string)$value : 'mike';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if($hasProcessing):?><meta http-equiv="refresh" content="4"><?php endif;?>
<title>Review spreadsheet receipt imports</title>
<style>
body{font-family:system-ui;background:#f4f6f8;color:#17202a;margin:0}.wrap{max-width:1180px;margin:auto;padding:16px}.card{background:#fff;border-radius:14px;padding:16px;margin:12px 0;box-shadow:0 2px 10px #0001}.receipt{border:2px solid #d7e1e8;border-radius:12px;padding:14px;margin:14px 0}.receipt.credit{border-color:#b56363;background:#fffafa}.line{display:grid;grid-template-columns:34px minmax(240px,1fr) 145px 145px;gap:8px;align-items:end;border-top:1px solid #e1e7eb;padding:10px 0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.field label{display:block;font-size:12px;font-weight:850;margin-bottom:4px}input,select{box-sizing:border-box;width:100%;padding:9px;border:1px solid #cbd5dc;border-radius:8px}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}.metric{padding:10px;background:#f5f7f8;border-radius:8px}.warning{padding:11px;background:#fff4d4;border:1px solid #e3bf55;border-radius:9px}.bad{padding:11px;background:#fde8e8;border:1px solid #ce7777;border-radius:9px}.good{padding:12px;background:#e9f8ee;border:1px solid #71b888;border-radius:9px}.btn{display:inline-block;padding:11px 14px;border:0;border-radius:8px;background:#17202a;color:#fff;text-decoration:none;font-weight:850;cursor:pointer}.secondary{background:#fff;color:#17202a;border:1px solid #cbd5dc}.muted{font-size:13px;color:#66717c}.check{font-size:17px;font-weight:850}.check input{width:23px;height:23px;vertical-align:middle}.confirm{display:block;margin:12px 0;font-weight:800}.confirm input{width:24px;height:24px;vertical-align:middle}.applied{border:2px solid #65a979;background:#f0faf3}.money-note{font-size:12px;color:#66717c;margin-top:4px}.progress{height:18px;background:#e4e9ed;border-radius:999px;overflow:hidden}.progress span{display:block;height:100%;background:#218749;color:#fff;text-align:center;font-size:12px;font-weight:900;min-width:3em}@media(max-width:760px){.wrap{padding:9px}.grid{grid-template-columns:1fr 1fr}.line{grid-template-columns:34px 1fr}.line .money{grid-column:2}.btn{width:100%;box-sizing:border-box;text-align:center;margin:4px 0}}
</style>
</head>
<body><?php $adminPageTitle='Receipt Import Review';$adminBreadcrumbs=['Work Tracker'=>'index.php','Receipt review'=>''];$adminJob=$job??null;require __DIR__.'/../../includes/admin_nav.php';?>
<div class="wrap">
<p><a href="materials.php?id=<?=$jobId?>#spreadsheet-imports">← Materials / receipts</a></p>
<h1>Spreadsheet receipt imports</h1>
<p><b><?=irh($job['customer_name'])?></b></p>

<?php if(isset($_GET['applied_import'])):?>
<div class="good"><b>✓ Spreadsheet import approved.</b> <?=max(0,(int)($_GET['materials_added']??0))?> selected purchase line(s) were added to the materials ledger. The import is now locked against duplicate application.</div>
<?php endif;?>

<div class="warning"><b>Direct spreadsheet import is a draft.</b> The website copies structured rows without an AI delay. Compare suppliers, receipt numbers, dates, payer, card last four digits, line amounts, GST, credits, returns and duplicates with the original spreadsheet. Only ticked receipts and ticked lines are added. Nothing changes job totals until you press the final approval button.</div>

<?php if(!$imports):?><div class="card">No spreadsheet imports have been uploaded.</div><?php endif;?>

<?php foreach($imports as $import):
    $importId=(int)$import['id'];
    $proposal=json_decode((string)($import['proposal_json']??''),true);
    $warnings=json_decode((string)($import['reconciliation_warnings']??''),true);
    $approved=json_decode((string)($import['approved_json']??''),true);
?>
<div class="card <?=((string)$import['status']==='applied')?'applied':''?>">
<h2><?=irh($import['original_name'])?></h2>
<p>Status: <b><?=irh($import['status'])?></b> · <a class="btn secondary" href="receipt_import_file.php?job_id=<?=$jobId?>&amp;import_id=<?=$importId?>">DOWNLOAD ORIGINAL</a></p>

<?php if($import['status']==='failed'):?>
<div class="bad"><?=irh($import['error_message'])?></div>
<form method="post" action="../../api/work/retry_receipt_spreadsheet.php"><input type="hidden" name="job_id" value="<?=$jobId?>"><input type="hidden" name="import_id" value="<?=$importId?>"><button class="btn" type="submit">RETRY DIRECT IMPORT</button></form>
<?php elseif($import['status']==='processing'):?>
<?php $progress=max(0,min(100,(int)($import['progress_percent']??0)));?>
<div class="progress"><span style="width:<?=$progress?>%"><?=$progress?>%</span></div>
<p><b><?=irh($import['processing_stage']??'Waiting')?></b> — <?=irh($import['processing_detail']??'No active processing details were recorded.')?></p>
<p class="muted">This page refreshes automatically every four seconds. If the percentage does not move, process the privately stored spreadsheet immediately using the button below.</p>
<form method="post" action="../../api/work/retry_receipt_spreadsheet.php"><input type="hidden" name="job_id" value="<?=$jobId?>"><input type="hidden" name="import_id" value="<?=$importId?>"><button class="btn" type="submit">RECOVER &amp; PROCESS NOW</button></form>
<?php elseif($import['status']==='applied'):?>
<div class="good"><b>Already approved and added to the ledger.</b><br><?=count((array)($approved['receipts']??[]))?> receipt group(s), <?=count((array)json_decode((string)($import['applied_material_ids_json']??'[]'),true))?> material line(s), approved <?=irh($import['reviewed_at']??'')?>.</div>
<?php elseif(is_array($proposal)):?>
<div class="metrics">
<div class="metric">Sheets<b><br><?=(int)$import['sheet_count']?></b></div>
<div class="metric">Receipts / credits<b><br><?=(int)$import['receipt_count']?></b></div>
<div class="metric">Purchase lines<b><br><?=(int)$import['line_count']?></b></div>
<div class="metric">Spreadsheet total<b><br><?=irm($import['spreadsheet_gross_total'])?></b></div>
<div class="metric">Parsed gross total<b><br><?=irm($import['parsed_gross_total'])?></b></div>
<div class="metric">Parsed GST<b><br><?=irm($import['parsed_gst_total'])?></b></div>
<div class="metric">Parsed excluding GST<b><br><?=irm($import['parsed_net_total'])?></b></div>
<div class="metric">Difference<b><br><?=irm($import['reconciliation_difference'])?></b></div>
</div>

<?php if($import['confidence_note']):?><p><b>Import notes:</b> <?=irh($import['confidence_note'])?></p><?php endif;?>
<?php if(is_array($warnings)&&$warnings):?><div class="warning"><b>Reconciliation warnings</b><ul><?php foreach($warnings as $warning):?><li><?=irh($warning)?></li><?php endforeach;?></ul></div><?php endif;?>

<form method="post" action="../../api/work/apply_receipt_import.php">
<input type="hidden" name="job_id" value="<?=$jobId?>">
<input type="hidden" name="import_id" value="<?=$importId?>">

<?php foreach(array_values((array)($proposal['receipts']??[])) as $receiptIndex=>$receipt):
    if(!is_array($receipt))continue;
    $paidBy=irpaid($receipt['paid_by']??'mike');
    $cardLast4=preg_match('/^\d{4}$/',(string)($receipt['payment_card_last4']??''))?(string)$receipt['payment_card_last4']:'';
    $reimbursement=$paidBy==='mike'?'reimbursement_due':($paidBy==='customer'?'no_reimbursement_due':'not_applicable');
    $isCredit=!empty($receipt['is_credit_or_return']);
    $lines=array_values(array_filter((array)($receipt['lines']??[]),'is_array'));
    if(!$lines){$lines=[['description'=>trim((string)($receipt['source_label']??''))?:'Receipt purchase','gross_amount'=>$receipt['gross_total']??null,'gst_amount'=>$receipt['gst_total']??null,'is_credit_or_return'=>$isCredit]];}
?>
<div class="receipt <?=$isCredit?'credit':''?>">
<label class="check"><input type="checkbox" name="receipts[<?=$receiptIndex?>][include]" value="1" checked> Include receipt / credit <?=($receiptIndex+1)?>: <?=irh($receipt['source_label']??$receipt['supplier']??'Unlabelled')?></label>
<input type="hidden" name="receipts[<?=$receiptIndex?>][is_credit_or_return]" value="<?=$isCredit?'1':'0'?>">
<?php if($isCredit):?><p class="bad"><b>Credit / return:</b> retained as negative values. Positive amounts will be rejected rather than silently changed.</p><?php endif;?>

<div class="grid">
<div class="field"><label>Supplier</label><input name="receipts[<?=$receiptIndex?>][supplier]" value="<?=irh($receipt['supplier']??'')?>"></div>
<div class="field"><label>Receipt / reference number</label><input name="receipts[<?=$receiptIndex?>][receipt_number]" value="<?=irh($receipt['receipt_number']??'')?>"></div>
<div class="field"><label>Purchase date</label><input type="date" name="receipts[<?=$receiptIndex?>][purchase_date]" value="<?=irh(irdate($receipt['purchase_date']??''))?>"></div>
<div class="field"><label>Task</label><select name="receipts[<?=$receiptIndex?>][task_id]"><option value="0">Whole job / not assigned</option><?php foreach($tasks as $task):?><option value="<?=(int)$task['id']?>" <?=((int)($import['task_id']??0)===(int)$task['id'])?'selected':''?>><?=irh($task['title'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Paid by</label><select name="receipts[<?=$receiptIndex?>][paid_by]"><option value="mike" <?=$paidBy==='mike'?'selected':''?>>Mike</option><option value="customer" <?=$paidBy==='customer'?'selected':''?>>Customer</option><option value="other" <?=$paidBy==='other'?'selected':''?>>Other</option></select></div>
<div class="field"><label>Card last 4 digits (optional)</label><input name="receipts[<?=$receiptIndex?>][payment_card_last4]" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" value="<?=irh($cardLast4)?>" placeholder="1234"><div class="money-note">Never enter the full card number.</div></div>
<div class="field"><label>Reimbursement</label><select name="receipts[<?=$receiptIndex?>][reimbursement_status]"><option value="not_applicable" <?=$reimbursement==='not_applicable'?'selected':''?>>Not applicable</option><option value="reimbursement_due" <?=$reimbursement==='reimbursement_due'?'selected':''?>>Reimbursement due</option><option value="reimbursed">Reimbursed</option><option value="no_reimbursement_due" <?=$reimbursement==='no_reimbursement_due'?'selected':''?>>No reimbursement due</option></select></div>
<div class="field"><label>Financial treatment</label><select name="receipts[<?=$receiptIndex?>][financial_treatment]"><option value="charge_customer">Charge customer</option><option value="included_in_price">Included in agreed price</option><option value="goodwill">Goodwill</option><option value="rectification">Rectification</option></select></div>
</div>

<p class="muted">Spreadsheet receipt total: <?=irm($receipt['gross_total']??null)?> · GST: <?=irm($receipt['gst_total']??null)?><?php if(!empty($receipt['confidence_note'])):?> · <?=irh($receipt['confidence_note'])?><?php endif;?></p>
<h3>Purchase lines</h3>
<?php foreach($lines as $lineIndex=>$line):$lineCredit=!empty($line['is_credit_or_return'])||((float)($line['gross_amount']??0)<0);?>
<div class="line">
<input type="checkbox" name="receipts[<?=$receiptIndex?>][lines][<?=$lineIndex?>][include]" value="1" <?=array_key_exists('include_default',$line)&&empty($line['include_default'])?'':'checked'?>>
<div class="field"><label>Description / job use</label><input name="receipts[<?=$receiptIndex?>][lines][<?=$lineIndex?>][description]" value="<?=irh(trim((string)($line['description']??'')) . (!empty($line['job_use'])?' — '.trim((string)$line['job_use']):''))?>"></div>
<div class="field money"><label>Gross incl. GST</label><input type="number" step=".01" name="receipts[<?=$receiptIndex?>][lines][<?=$lineIndex?>][gross_amount]" value="<?=irh($line['gross_amount']??'')?>"><div class="money-note">Negative for credit/return</div></div>
<div class="field money"><label>GST</label><input type="number" step=".01" name="receipts[<?=$receiptIndex?>][lines][<?=$lineIndex?>][gst_amount]" value="<?=irh($line['gst_amount']??'')?>"><input type="hidden" name="receipts[<?=$receiptIndex?>][lines][<?=$lineIndex?>][is_credit_or_return]" value="<?=$lineCredit?'1':'0'?>"></div>
</div>
<?php if(!empty($line['exclusion_reason'])):?><p class="muted" style="margin:0 0 8px 42px">Unticked automatically: <?=irh($line['exclusion_reason'])?>. Tick it only if it is genuinely a job material.</p><?php endif;?>
<?php endforeach;?>
</div>
<?php endforeach;?>

<div class="warning">
<label class="confirm"><input type="checkbox" name="confirm_original" value="1" required> I compared the selected entries with the original spreadsheet.</label>
<label class="confirm"><input type="checkbox" name="confirm_reconciliation" value="1" required> I reviewed totals, GST, duplicate warnings, credits and returns.</label>
<b>This approval is one-time.</b> It creates materials from selected lines and locks this import against being applied twice.
</div>
<p><button class="btn" type="submit">APPROVE SELECTED LINES &amp; ADD MATERIALS</button></p>
</form>
<?php endif;?>
</div>
<?php endforeach;?>
</div>
</body>
</html>
