<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_invoice.php';
require_once __DIR__ . '/../../includes/zoho_functions.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}
$jobIds=array_values(array_filter(array_map('intval',explode(',',(string)($_POST['job_ids']??($_POST['job_id']??'')))),fn($id)=>$id>0));$jobId=$jobIds[0]??0;$snapshotId=(int)($_POST['snapshot_id']??0);$returnUrl=count($jobIds)>1?'../../admin/work/combined_invoice_preparation.php?customer_id='.(int)($_POST['customer_id']??0).'&'.http_build_query(['job_ids'=>$jobIds]):'../../admin/work/invoice_preparation.php?id='.$jobId;if($jobId<=0||$snapshotId<=0){http_response_code(400);exit('Invalid request.');}
$lockName='work_zoho_invoice_'.$jobId;$lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$lockName]);if((int)$lock->fetchColumn()!==1){http_response_code(409);exit('Another invoice request is already running.');}
try{
    $q=$pdo->prepare('SELECT * FROM work_closeout_snapshots WHERE id=? AND job_id=?');$q->execute([$snapshotId,$jobId]);$snapshot=$q->fetch(PDO::FETCH_ASSOC);if(!$snapshot||empty($snapshot['zoho_invoice_id']))throw new RuntimeException('Create the Zoho draft first.');if(!empty($snapshot['sent_to_zoho_at'])){header('Location: '.$returnUrl.'&zoho_already_sent=1#zoho-invoice');exit;}
    $saved=json_decode((string)$snapshot['snapshot_json'],true);$package=$saved['invoice_package']??null;if(!is_array($package))throw new RuntimeException('The approved invoice snapshot is invalid.');
    wt_invoice_zoho_contact_id($package);
    if(($package['warnings']['unfinished_sessions']??0)>0)throw new RuntimeException('The approved snapshot contains unfinished work sessions.');
    if(!empty($package['warnings']['missing_supplier_gst']))throw new RuntimeException('Enter supplier GST for every reimbursement before sending: '.implode(', ',$package['warnings']['missing_supplier_gst']));
    if(!empty($package['warnings']['reconciliation']))throw new RuntimeException('Resolve the spreadsheet reconciliation warning before sending this invoice.');
    $email=trim((string)$package['job']['customer_email']);if($email==='')throw new RuntimeException('Customer email is missing.');
    $fresh=getZohoInvoice((string)$snapshot['zoho_invoice_id']);$freshInvoice=$fresh['json']['invoice']??null;if(!is_array($freshInvoice))throw new RuntimeException('Safety stop: the Zoho draft could not be checked before emailing.');wt_assert_zoho_invoice_matches($freshInvoice,$package);
    $attachments=wt_invoice_receipt_attachments($pdo,$package);$response=sendZohoInvoiceWithAttachments((string)$snapshot['zoho_invoice_id'],$email,$attachments);
    if(($response['code']??500)>=400||(($response['json']['code']??0)!==0))throw new RuntimeException('Zoho email failed: '.mb_substr((string)($response['raw']??'Unknown response'),0,1000));
    $prior=json_decode((string)($snapshot['zoho_response_json']??''),true);if(!is_array($prior))$prior=[];$prior['email_response']=$response['json'];$prior['receipt_attachment_count']=count($attachments);$prior['emailed_at']=date('c');
    $q=$pdo->prepare("UPDATE work_closeout_snapshots SET status='sent_to_zoho',sent_to_zoho_at=NOW(),zoho_response_json=? WHERE id=? AND job_id=? AND sent_to_zoho_at IS NULL");$q->execute([json_encode($prior,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$snapshotId,$jobId]);
    header('Location: '.$returnUrl.'&zoho_sent=1#zoho-invoice');
}catch(Throwable $e){error_log('Zoho invoice email failed: '.$e->getMessage());header('Location: '.$returnUrl.'&zoho_error='.urlencode($e->getMessage()).'#zoho-invoice');}
finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lockName]);}
