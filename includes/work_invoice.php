<?php
declare(strict_types=1);

/**
 * Build the authoritative Work Tracker invoice package.
 * All monetary amounts are gross AUD amounts. Supplier GST is informational
 * and remains included in reimbursements; it is never added as Mike's GST.
 */
function wt_invoice_package(PDO $pdo, int $jobId): array
{
    $job = wt_job($pdo, $jobId);
    $defaultRate = (float)($job['agreed_hourly_rate'] ?? 0);

    $q = $pdo->prepare("SELECT s.*,COALESCE(w.hourly_rate,?) effective_rate,t.title task_title,
        CASE WHEN s.ended_at IS NULL THEN 0
        WHEN s.session_source='retrospective' AND s.retrospective_hours IS NOT NULL THEN s.retrospective_hours
        ELSE GREATEST(0,TIMESTAMPDIFF(SECOND,s.started_at,s.ended_at)-COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND,b.started_at,COALESCE(b.ended_at,s.ended_at))) FROM work_session_breaks b WHERE b.session_id=s.id),0))/3600 END calculated_hours
        FROM work_sessions s LEFT JOIN work_workers w ON w.id=s.worker_id LEFT JOIN work_tasks t ON t.id=s.task_id
        WHERE s.job_id=? ORDER BY s.started_at,s.id");
    $q->execute([$defaultRate,$jobId]);
    $sessions=$q->fetchAll(PDO::FETCH_ASSOC);$daily=[];$unfinished=0;
    foreach($sessions as $s){
        if(empty($s['ended_at'])){$unfinished++;continue;}
        if((int)($s['billable']??0)!==1)continue;
        $hours=max(0.0,(float)$s['calculated_hours']);if($hours<=0)continue;
        $date=date('Y-m-d',strtotime((string)$s['started_at']));
        if(!isset($daily[$date]))$daily[$date]=['date'=>$date,'hours'=>0.0,'amount'=>0.0,'texts'=>[],'session_ids'=>[]];
        $daily[$date]['hours']+=$hours;$daily[$date]['amount']+=$hours*(float)$s['effective_rate'];
        foreach([(string)($s['task_title']??''),(string)($s['notes']??'')] as $text){$text=trim($text);if($text!==''&&!in_array($text,$daily[$date]['texts'],true))$daily[$date]['texts'][]=$text;}
        $daily[$date]['session_ids'][]=(int)$s['id'];
    }
    foreach($daily as &$day){$text=$day['texts']?implode('; ',$day['texts']):'General property maintenance and handyman labour';$day['description']=mb_strlen($text)>500?mb_substr($text,0,497).'...':$text;$day['rate']=$day['hours']>0?$day['amount']/$day['hours']:0.0;}
    unset($day);

    $q=$pdo->prepare("SELECT m.*,(SELECT rl.receipt_id FROM work_receipt_lines rl WHERE rl.material_id=m.id ORDER BY rl.id LIMIT 1) scanned_receipt_id
        FROM work_materials m WHERE m.job_id=? AND COALESCE(m.material_status,'')<>'not_required'
        ORDER BY COALESCE(m.purchase_date,DATE(m.purchased_at)),m.id");
    $q->execute([$jobId]);$groups=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $m){
        $date=(string)($m['purchase_date']?:substr((string)($m['purchased_at']??''),0,10));$receipt=trim((string)($m['receipt_number']??''));$supplier=trim((string)($m['supplier']??''))?:'Supplier not recorded';
        $key=implode('|',[$date,mb_strtolower($supplier),$receipt!==''?mb_strtolower($receipt):'material-'.$m['id'],(string)($m['paid_by']??''),(string)($m['financial_treatment']??'')]);
        if(!isset($groups[$key]))$groups[$key]=['key'=>$key,'date'=>$date,'supplier'=>$supplier,'receipt'=>$receipt,'paid_by'=>(string)($m['paid_by']??''),'treatment'=>(string)($m['financial_treatment']??''),'reimbursement'=>(string)($m['reimbursement_status']??''),'amount'=>0.0,'gst'=>0.0,'items'=>[],'material_ids'=>[],'receipt_ids'=>[]];
        $groups[$key]['amount']+=(float)($m['actual_cost']??$m['cost']??0);$groups[$key]['gst']+=(float)($m['receipt_gst_amount']??0);$groups[$key]['items'][]=(string)$m['description'];$groups[$key]['material_ids'][]=(int)$m['id'];
        if(!empty($m['scanned_receipt_id']))$groups[$key]['receipt_ids'][(int)$m['scanned_receipt_id']]=true;
    }
    $reimbursements=[];$reimbursementTotal=0.0;$supplierGst=0.0;$missingGst=[];
    foreach($groups as $g){
        $included=$g['paid_by']==='mike'&&$g['treatment']==='charge_customer'&&in_array($g['reimbursement'],['not_applicable','reimbursement_due'],true);
        if(!$included)continue;$g['receipt_ids']=array_keys($g['receipt_ids']);$reimbursements[]=$g;$reimbursementTotal+=$g['amount'];$supplierGst+=$g['gst'];
        if($g['amount']>0&&$g['gst']<=0)$missingGst[]=$g['supplier'].($g['receipt']!==''?' receipt '.$g['receipt']:'');
    }

    $labourTotal=(float)($job['work_already_value']??0);$labourHours=0.0;foreach($daily as $d){$labourTotal+=$d['amount'];$labourHours+=$d['hours'];}
    $payments=(float)($job['payments_received']??0);$q=$pdo->prepare('SELECT amount FROM work_payments WHERE job_id=?');$q->execute([$jobId]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $p)$payments+=(float)$p['amount'];
    $reconciliation=[];try{$q=$pdo->prepare("SELECT id,original_name,reconciliation_difference FROM work_receipt_imports WHERE job_id=? AND status IN ('ready','applied') AND ABS(COALESCE(reconciliation_difference,0))>=0.01");$q->execute([$jobId]);$reconciliation=$q->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
    $total=$labourTotal+$reimbursementTotal;$balance=$total-$payments;
    $package=['schema_version'=>2,'job'=>['id'=>$jobId,'customer_name'=>(string)($job['customer_name']??''),'customer_email'=>(string)($job['customer_email']??''),'customer_phone'=>(string)($job['customer_phone']??''),'job_address'=>(string)($job['job_address']??''),'job_title'=>(string)($job['job_title']??$job['title']??'Property maintenance')],'labour_days'=>array_values($daily),'reimbursements'=>$reimbursements,'totals'=>['labour_hours'=>round($labourHours,4),'labour'=>round($labourTotal,2),'reimbursements'=>round($reimbursementTotal,2),'supplier_gst_included'=>round($supplierGst,2),'payments'=>round($payments,2),'invoice_total'=>round($total,2),'balance_due'=>round($balance,2)],'warnings'=>['unfinished_sessions'=>$unfinished,'missing_supplier_gst'=>$missingGst,'reconciliation'=>$reconciliation]];
    $fingerprintData=$package;unset($fingerprintData['totals']['payments'],$fingerprintData['totals']['balance_due']);$package['fingerprint']=hash('sha256',json_encode($fingerprintData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    return $package;
}

function wt_invoice_receipt_attachments(PDO $pdo,array $package): array
{
    $ids=[];foreach($package['reimbursements'] as $g)foreach($g['receipt_ids'] as $id)$ids[(int)$id]=true;if(!$ids)return[];
    $marks=implode(',',array_fill(0,count($ids),'?'));$params=array_keys($ids);array_unshift($params,(int)$package['job']['id']);
    $q=$pdo->prepare("SELECT * FROM work_receipts WHERE job_id=? AND id IN ($marks)");$q->execute($params);$files=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$path=dirname(__DIR__).'/'.ltrim((string)$r['relative_path'],'/');if(is_file($path))$files[]=['tmp_name'=>$path,'name'=>(string)$r['original_name'],'mime'=>(string)$r['mime_type']];}
    return $files;
}

function wt_invoice_zoho_payload(array $package,string $contactId): array
{
    $job=$package['job'];$lines=[];
    // An empty tax_id explicitly overrides any taxable item default in Zoho.
    // tax_name and tax_percentage alone are not reliable write controls.
    $noTax=['tax_id'=>'','tax_name'=>'','tax_percentage'=>0];
    foreach($package['labour_days'] as $day)$lines[]=array_merge(['name'=>'Labour - '.date('j M Y',strtotime($day['date'])),'description'=>$day['description'],'quantity'=>round((float)$day['hours'],2),'rate'=>round((float)$day['rate'],2)],$noTax);
    if((float)($package['totals']['labour']??0)>0&&!$package['labour_days'])$lines[]=array_merge(['name'=>'Labour','description'=>'Previously recorded property maintenance labour','quantity'=>1,'rate'=>$package['totals']['labour']],$noTax);
    foreach($package['reimbursements'] as $g){$gst=$g['gst']>0?'Supplier GST included in this gross reimbursement: $'.number_format($g['gst'],2).'.':'Supplier GST is not yet recorded in the Work Tracker.';$description=implode('; ',array_filter([$g['receipt']!==''?'Supplier receipt '.$g['receipt']:'',$gst,'Original supplier receipt retained and provided where attached.']));$lines[]=array_merge(['name'=>'Reimbursement - '.$g['supplier'],'description'=>$description,'quantity'=>1,'rate'=>round((float)$g['amount'],2)],$noTax);}
    $gstTotal=(float)$package['totals']['supplier_gst_included'];
    $lines[]=array_merge(['name'=>'Supplier GST included in reimbursements','description'=>'Information only: $'.number_format($gstTotal,2).' supplier GST is already included in the gross reimbursement lines above. It is not GST charged by Mike Of All Trades and is not added again. Refer to the attached original supplier tax invoices.','quantity'=>1,'rate'=>0],$noTax);
    $subject=trim($job['job_title'].($job['job_address']!==''?' - '.$job['job_address']:''));
    return ['customer_id'=>$contactId,'date'=>date('Y-m-d'),'due_date'=>date('Y-m-d'),'payment_terms'=>0,'reference_number'=>'Work Tracker job #'.$job['id'],'is_inclusive_tax'=>false,'line_items'=>$lines,'notes'=>"Thanks for your business.\n\nNo GST has been charged by Mike Of All Trades. Total supplier GST already included in reimbursements: $".number_format($gstTotal,2).". This is copied from the original supplier tax invoices and is not added again.\n\nJob: ".$subject,'terms'=>"If you notice any discrepancies on this invoice, please contact Mike as soon as possible. Payment is due on receipt. Mike Of All Trades' Terms & Conditions are available at https://mikeofalltrades.com.au/terms.php"];
}

/** Refuse approval/email when Zoho has changed the GST-free figures. */
function wt_assert_zoho_invoice_no_gst(array $invoice,float $expectedTotal): void
{
    $tax=(float)($invoice['tax_total']??$invoice['total_tax']??0);$lineTax=0.0;
    foreach(($invoice['line_items']??[]) as $line)$lineTax+=max(0,(float)($line['tax_amount']??0));
    $tax=max($tax,$lineTax);
    $actual=(float)($invoice['total']??0);
    if($tax>0.004)throw new RuntimeException('Safety stop: Zoho added $'.number_format($tax,2).' GST. This draft cannot be emailed.');
    if($actual>0&&abs($actual-$expectedTotal)>0.01)throw new RuntimeException('Safety stop: Zoho total $'.number_format($actual,2).' does not match the GST-free Work Tracker total $'.number_format($expectedTotal,2).'. This draft cannot be emailed.');
}
