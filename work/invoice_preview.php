<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/work_tracker.php';

$token = trim((string)($_GET['t'] ?? ''));
try {
    $job = wt_job_by_token($pdo, $token);
} catch (Throwable $error) {
    http_response_code(404);
    exit('Invoice preview not found.');
}
$jobId = (int)$job['id'];
$defaultRate = (float)($job['agreed_hourly_rate'] ?? 0);

function cip_money(float $value): string { return '$' . number_format($value, 2); }
function cip_date(string $value): string { $t=strtotime($value); return $t?date('D j M Y',$t):$value; }
function cip_text(array $values): string {
    $clean=[];
    foreach($values as $value){$value=trim((string)$value);if($value!==''&&!in_array($value,$clean,true))$clean[]=$value;}
    $text=$clean?implode('; ',$clean):'General job labour';
    return mb_strlen($text)>240?mb_substr($text,0,237).'...':$text;
}

$sq=$pdo->prepare("SELECT s.*,COALESCE(w.hourly_rate,?) effective_rate,t.title task_title,
CASE WHEN s.ended_at IS NULL THEN 0 WHEN s.session_source='retrospective' AND s.retrospective_hours IS NOT NULL THEN s.retrospective_hours
ELSE GREATEST(0,TIMESTAMPDIFF(SECOND,s.started_at,s.ended_at)-COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND,b.started_at,COALESCE(b.ended_at,s.ended_at))) FROM work_session_breaks b WHERE b.session_id=s.id),0))/3600 END calculated_hours
FROM work_sessions s LEFT JOIN work_workers w ON w.id=s.worker_id LEFT JOIN work_tasks t ON t.id=s.task_id
WHERE s.job_id=? AND s.ended_at IS NOT NULL AND s.billable=1 ORDER BY s.started_at,s.id");
$sq->execute([$defaultRate,$jobId]);
$daily=[];
foreach($sq->fetchAll(PDO::FETCH_ASSOC) as $s){$h=max(0,(float)$s['calculated_hours']);if($h<=0)continue;$d=date('Y-m-d',strtotime((string)$s['started_at']));if(!isset($daily[$d]))$daily[$d]=['hours'=>0.0,'amount'=>0.0,'texts'=>[]];$daily[$d]['hours']+=$h;$daily[$d]['amount']+=$h*(float)$s['effective_rate'];$daily[$d]['texts'][]=(string)($s['task_title']?:$s['notes']?:$s['category']);}

$mq=$pdo->prepare("SELECT m.*,(SELECT rl.receipt_id FROM work_receipt_lines rl WHERE rl.material_id=m.id ORDER BY rl.id LIMIT 1) scanned_receipt_id FROM work_materials m WHERE m.job_id=? AND COALESCE(m.material_status,'')<>'not_required' ORDER BY COALESCE(m.purchase_date,DATE(m.purchased_at)),m.id");
$mq->execute([$jobId]);
$groups=[];
foreach($mq->fetchAll(PDO::FETCH_ASSOC) as $m){
    $date=(string)($m['purchase_date']?:substr((string)($m['purchased_at']??''),0,10));$receipt=trim((string)($m['receipt_number']??''));$supplier=trim((string)($m['supplier']??''))?:'Supplier not recorded';
    $key=implode('|',[$date,mb_strtolower($supplier),$receipt!==''?mb_strtolower($receipt):'material-'.$m['id'],(string)($m['paid_by']??''),(string)($m['financial_treatment']??'')]);
    if(!isset($groups[$key]))$groups[$key]=['date'=>$date,'supplier'=>$supplier,'receipt'=>$receipt,'amount'=>0.0,'gst'=>0.0,'paid_by'=>(string)($m['paid_by']??''),'treatment'=>(string)($m['financial_treatment']??''),'reimbursement'=>(string)($m['reimbursement_status']??''),'receipt_ids'=>[]];
    $groups[$key]['amount']+=(float)($m['actual_cost']??$m['cost']??0);$groups[$key]['gst']+=(float)($m['receipt_gst_amount']??0);
    if(!empty($m['scanned_receipt_id']))$groups[$key]['receipt_ids'][(int)$m['scanned_receipt_id']]=true;
}

$labour=(float)($job['work_already_value']??0);$hours=0.0;foreach($daily as $d){$labour+=$d['amount'];$hours+=$d['hours'];}
$reimbursements=0.0;$supplierGst=0.0;$included=[];
foreach($groups as $g){$ok=$g['paid_by']==='mike'&&$g['treatment']==='charge_customer'&&in_array($g['reimbursement'],['not_applicable','reimbursement_due'],true);if(!$ok)continue;$reimbursements+=$g['amount'];$supplierGst+=$g['gst'];$included[]=$g;}
$payments=(float)($job['payments_received']??0);$pq=$pdo->prepare('SELECT amount FROM work_payments WHERE job_id=?');$pq->execute([$jobId]);foreach($pq->fetchAll(PDO::FETCH_ASSOC) as $p)$payments+=(float)$p['amount'];
$total=$labour+$reimbursements;$balance=$total-$payments;
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Invoice preview — <?=wt_html((string)$job['customer_name'])?></title>
<style>body{font-family:system-ui;background:#eef2f4;color:#17202a;margin:0}.wrap{max-width:1050px;margin:auto;padding:18px}.card{background:#fff;border-radius:14px;padding:18px;margin:14px 0;box-shadow:0 2px 10px #0001}.notice{padding:13px;border-radius:10px;background:#edf8f0;border:1px solid #96d0a4}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:9px}.metric{background:#f7f9fa;border:1px solid #dfe6ea;border-radius:10px;padding:12px}.metric b{display:block;font-size:22px}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:720px}th,td{padding:9px;border-bottom:1px solid #e1e6ea;text-align:left;vertical-align:top}th{font-size:12px;text-transform:uppercase;color:#61707a}.money{text-align:right;white-space:nowrap}.btn{display:inline-block;padding:8px 11px;background:#17202a;color:#fff;border-radius:8px;text-decoration:none;font-weight:750}.muted{color:#68757e;font-size:13px}@media print{body{background:#fff}.no-print{display:none}.card{box-shadow:none;border:1px solid #ddd}}</style></head><body><div class="wrap">
<h1>Invoice review</h1><p><b><?=wt_html((string)$job['customer_name'])?></b><?=!empty($job['job_title'])?' — '.wt_html((string)$job['job_title']):''?></p>
<p class="notice"><b>No GST has been charged by Mike of All Trades.</b> Supplier GST below is copied from original supplier tax invoices, is already included in each reimbursement and has not been added again.</p>
<div class="metrics"><div class="metric">Labour<b><?=cip_money($labour)?></b><?=number_format($hours,2)?> hours</div><div class="metric">Reimbursements<b><?=cip_money($reimbursements)?></b></div><div class="metric">Supplier GST included<b><?=cip_money($supplierGst)?></b><span class="muted">Information only</span></div><div class="metric">Balance due<b><?=cip_money($balance)?></b></div></div>
<div class="card"><h2>Labour by day</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Work completed</th><th class="money">Hours</th><th class="money">Amount</th></tr></thead><tbody><?php foreach($daily as $date=>$d):?><tr><td><?=wt_html(cip_date($date))?></td><td><?=wt_html(cip_text($d['texts']))?></td><td class="money"><?=number_format($d['hours'],2)?></td><td class="money"><b><?=cip_money($d['amount'])?></b></td></tr><?php endforeach;?></tbody><tfoot><tr><th colspan="2">Labour total</th><th class="money"><?=number_format($hours,2)?></th><th class="money"><?=cip_money($labour)?></th></tr></tfoot></table></div></div>
<div class="card"><h2>Reimbursements by supplier receipt</h2><div class="scroll"><table><thead><tr><th>Date</th><th>Supplier / receipt</th><th class="money">Net</th><th class="money">Supplier GST</th><th class="money">Gross reimbursed</th><th>Receipt</th></tr></thead><tbody><?php foreach($included as $g):?><tr><td><?=wt_html(cip_date($g['date']))?></td><td><b><?=wt_html($g['supplier'])?></b><?=$g['receipt']!==''?'<br>Receipt '.wt_html($g['receipt']):''?></td><td class="money"><?=cip_money(max(0,$g['amount']-$g['gst']))?></td><td class="money"><?=$g['gst']>0?cip_money($g['gst']):'Not recorded'?></td><td class="money"><b><?=cip_money($g['amount'])?></b></td><td><?php foreach(array_keys($g['receipt_ids']) as $rid):?><a class="btn" target="_blank" href="invoice_receipt.php?t=<?=urlencode($token)?>&amp;receipt_id=<?=$rid?>">View receipt</a><?php endforeach;?><?php if(!$g['receipt_ids']):?><span class="muted">No image attached</span><?php endif;?></td></tr><?php endforeach;?></tbody><tfoot><tr><th colspan="2">Reimbursement totals</th><th class="money"><?=cip_money($reimbursements-$supplierGst)?></th><th class="money"><?=cip_money($supplierGst)?></th><th class="money"><?=cip_money($reimbursements)?></th><th></th></tr></tfoot></table></div></div>
<div class="card"><h2>Summary</h2><table style="min-width:0"><tr><td>Labour</td><td class="money"><?=cip_money($labour)?></td></tr><tr><td>Reimbursements, including supplier GST</td><td class="money"><?=cip_money($reimbursements)?></td></tr><tr><th>Total — no GST added by Mike</th><th class="money"><?=cip_money($total)?></th></tr><tr><td>Payments received</td><td class="money">−<?=cip_money($payments)?></td></tr><tr><th>Balance due</th><th class="money"><?=cip_money($balance)?></th></tr></table><p class="muted">This is a read-only review page, not a tax invoice and not a payment receipt. Please contact Mike if anything needs correction.</p><button class="btn no-print" onclick="window.print()">Print / Save PDF</button></div>
</div></body></html>
