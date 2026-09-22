<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
require_once __DIR__ . '/../../includes/work_invoice.php';
require_once __DIR__ . '/../../includes/zoho_functions.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}
$jobIds=array_values(array_filter(array_map('intval',explode(',',(string)($_POST['job_ids']??($_POST['job_id']??'')))),fn($id)=>$id>0));$jobId=$jobIds[0]??0;if($jobId<=0){http_response_code(400);exit('Invalid job.');}$returnUrl=count($jobIds)>1?'../../admin/work/combined_invoice_preparation.php?customer_id='.(int)($_POST['customer_id']??0).'&'.http_build_query(['job_ids'=>$jobIds]):'../../admin/work/invoice_preparation.php?id='.$jobId;
$lockName='work_zoho_invoice_'.$jobId;$lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$lockName]);if((int)$lock->fetchColumn()!==1){http_response_code(409);exit('Another invoice request is already running.');}

try{
    $package=wt_invoice_package_for_jobs($pdo,$jobIds);$job=$package['job'];
    if(trim($job['customer_email'])==='')throw new RuntimeException('Add the customer email address before creating the Zoho draft.');
    if($package['warnings']['unfinished_sessions']>0)throw new RuntimeException('Finish all running work timers before creating the invoice.');
    if($package['totals']['invoice_total']<=0)throw new RuntimeException('Invoice total must be greater than zero.');

    $q=$pdo->prepare("SELECT * FROM work_closeout_snapshots WHERE job_id=? AND status IN ('approved','sent_to_zoho') ORDER BY id DESC LIMIT 20");$q->execute([$jobId]);$snapshot=null;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $candidate){$saved=json_decode((string)$candidate['snapshot_json'],true);if(($saved['invoice_package']['fingerprint']??'')===$package['fingerprint']){$snapshot=$candidate;break;}}
    if($snapshot&&!empty($snapshot['zoho_invoice_id'])){header('Location: '.$returnUrl.'&zoho_existing=1#zoho-invoice');exit;}

    if(!$snapshot){
        $pdo->prepare("UPDATE work_closeout_snapshots SET status='superseded' WHERE job_id=? AND status='approved'")->execute([$jobId]);
        $snapshotJson=json_encode(['schema_version'=>3,'captured_at'=>date('c'),'invoice_package'=>$package],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $q=$pdo->prepare("INSERT INTO work_closeout_snapshots(job_id,status,labour_amount,materials_amount,other_amount,gross_job_amount,payments_amount,outstanding_amount,snapshot_json,correction_notes,created_by,reviewed_at,approved_at) VALUES(?,'approved',?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $q->execute([$jobId,$package['totals']['labour'],$package['totals']['reimbursements'],0,$package['totals']['invoice_total'],$package['totals']['payments'],$package['totals']['balance_due'],$snapshotJson,'Approved automatically from Invoice preparation before Zoho draft creation',(int)($_SESSION['user_id']??0)?:null]);
        $snapshotId=(int)$pdo->lastInsertId();
    }else{$snapshotId=(int)$snapshot['id'];}

    $contactId=wt_invoice_zoho_contact_id($package);
    $payload=wt_invoice_zoho_payload($package,(string)$contactId,getZohoZeroTaxId());
    $response=createZohoInvoice($payload);$invoice=$response['json']['invoice']??null;$invoiceId=(string)($invoice['invoice_id']??'');
    if($invoiceId==='')throw new RuntimeException('Zoho draft creation failed: '.mb_substr((string)($response['raw']??'Unknown response'),0,1000));
    wt_assert_zoho_invoice_matches($invoice,$package);
    $stored=json_encode(['create_response'=>$response['json'],'invoice_number'=>$invoice['invoice_number']??null,'created_from_fingerprint'=>$package['fingerprint']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $q=$pdo->prepare('UPDATE work_closeout_snapshots SET zoho_invoice_id=?,zoho_response_json=? WHERE id=? AND job_id=?');$q->execute([$invoiceId,$stored,$snapshotId,$jobId]);
    header('Location: '.$returnUrl.'&zoho_created=1#zoho-invoice');
}catch(Throwable $e){error_log('Zoho invoice creation failed: '.$e->getMessage());header('Location: '.$returnUrl.'&zoho_error='.urlencode($e->getMessage()).'#zoho-invoice');}
finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lockName]);}
