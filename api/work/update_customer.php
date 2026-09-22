<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';
require_once __DIR__.'/../../includes/work_tracker.php';
require_once __DIR__.'/../../includes/zoho_functions.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required.');}
$customerId=(int)($_POST['customer_id']??0);if($customerId<=0){http_response_code(400);exit('Invalid customer.');}
try{
    $customer=wt_customer($pdo,$customerId);$action=(string)($_POST['action']??'save');
    if($action==='link_zoho'){
        $contactId=trim((string)($_POST['zoho_contact_id']??''));$contact=getZohoCustomerById($contactId);
        $name=trim((string)($contact['contact_name']??$contact['company_name']??''));
        $q=$pdo->prepare('SELECT id,display_name FROM work_customers WHERE zoho_contact_id=? AND id<>? LIMIT 1');$q->execute([$contactId,$customerId]);$owner=$q->fetch(PDO::FETCH_ASSOC);if($owner)throw new RuntimeException('That Zoho contact is already linked to Work Tracker customer "'.$owner['display_name'].'" (#'.$owner['id'].'). Open that customer and link this job to it instead.');
        $pdo->prepare('UPDATE work_customers SET zoho_contact_id=?,zoho_contact_name=? WHERE id=?')->execute([$contactId,$name,$customerId]);
        header('Location: ../../admin/work/customer.php?id='.$customerId.'&zoho_linked=1');exit;
    }
    if($action==='link_job'){
        $jobId=(int)($_POST['job_id']??0);$job=wt_job($pdo,$jobId);
        if(!empty($job['property_id']))$pdo->prepare('UPDATE work_properties SET customer_id=? WHERE id=?')->execute([$customerId,(int)$job['property_id']]);
        $pdo->prepare('UPDATE work_jobs SET customer_id=?,customer_email=?,customer_phone=? WHERE id=?')->execute([$customerId,$customer['email']?:null,$customer['phone']?:null,$jobId]);
        header('Location: ../../admin/work/customer.php?id='.$customerId.'&job_linked=1');exit;
    }
    $name=trim((string)($_POST['display_name']??''));if($name==='')throw new InvalidArgumentException('Customer name is required.');
    $email=strtolower(trim((string)($_POST['email']??'')));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email.');
    $phoneRaw=trim((string)($_POST['phone']??''));$phone=$phoneRaw!==''?wt_normalise_phone($phoneRaw):'';
    $days=(int)($_POST['payment_terms_days']??0);if($days<0||$days>365)throw new InvalidArgumentException('Payment terms must be between 0 and 365 days.');
    $q=$pdo->prepare('UPDATE work_customers SET display_name=?,source_alias=?,organisation=?,email=?,phone=?,billing_address=?,payment_terms_days=? WHERE id=?');
    $q->execute([$name,trim((string)($_POST['source_alias']??''))?:null,trim((string)($_POST['organisation']??''))?:null,$email?:null,$phone?:null,trim((string)($_POST['billing_address']??''))?:null,$days,$customerId]);
    $pdo->prepare('UPDATE work_jobs SET customer_email=?,customer_phone=? WHERE customer_id=?')->execute([$email?:null,$phone?:null,$customerId]);
    $labels=$_POST['property_label']??[];$addresses=$_POST['property_address']??[];
    foreach($addresses as $propertyId=>$address){$propertyId=(int)$propertyId;$address=trim((string)$address);if($propertyId<=0||$address==='')continue;$pdo->prepare('UPDATE work_properties SET label=?,address=? WHERE id=? AND customer_id=?')->execute([trim((string)($labels[$propertyId]??''))?:null,$address,$propertyId,$customerId]);$pdo->prepare('UPDATE work_jobs SET job_address=? WHERE property_id=? AND customer_id=?')->execute([$address,$propertyId,$customerId]);}
    header('Location: ../../admin/work/customer.php?id='.$customerId.'&saved=1');
}catch(Throwable $e){http_response_code(400);echo 'Could not update customer: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
