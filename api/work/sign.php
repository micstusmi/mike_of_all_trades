<?php
require_once __DIR__ . '/../../includes/work_tracker.php';

$token=$_POST['token']??'';
$job=wt_job_by_token($pdo,$token);

if($job['agreement_signed_at']){
    header("Location: ../../work/job.php?t=".urlencode($token));
    exit;
}

$name=trim($_POST['agreement_name']??'');
$sig=$_POST['signature']??'';
$ack=($_POST['ack_balance']??'')==='1';
$authorise=($_POST['authorise_continue']??'')==='1';

$isFixed=(($job['original_pricing_type']??'')==='fixed_price');
$variationRequired=$isFixed && !empty($job['variation_required']);
$variationAuthorised=($_POST['authorise_variation']??'')==='1';

if(!$name || !$ack || !$authorise){
    die('Name and required acknowledgements are required.');
}
if($variationRequired && !$variationAuthorised){
    die('The fixed-price variation must be explicitly authorised before continuation.');
}
if(!str_starts_with($sig,'data:image/png;base64,')){
    die('Signature is required.');
}

$data=base64_decode(substr($sig,22),true);
if($data===false || strlen($data)>2_000_000){
    die('Invalid signature.');
}

$tot=wt_totals($pdo,(int)$job['id']);

$workersStmt=$pdo->prepare("SELECT worker_name,hourly_rate FROM work_workers WHERE job_id=? AND active=1 ORDER BY id");
$workersStmt->execute([$job['id']]);
$workers=$workersStmt->fetchAll(PDO::FETCH_ASSOC);

$snapshot=[
    'agreement_version'=>'3.0',
    'signed_at'=>date('c'),
    'customer_name'=>$job['customer_name'],
    'job_address'=>$job['job_address'],
    'original_pricing_type'=>$job['original_pricing_type'] ?? 'unspecified',
    'original_scope'=>$job['original_scope'],
    'current_scope'=>$job['current_scope'],
    'unforeseen_conditions'=>$job['unforeseen_conditions'],
    'original_estimate_amount'=>$job['original_estimate_amount'],
    'original_estimate_hours'=>$job['original_estimate_hours'],

    'variation_required'=>(bool)$variationRequired,
    'variation_description'=>$job['variation_description'],
    'variation_pricing_method'=>$job['variation_pricing_method'],
    'variation_fixed_amount'=>$job['variation_fixed_amount'],
    'variation_hourly_rate'=>$job['variation_hourly_rate'],
    'variation_forecast_low'=>$job['variation_forecast_low'],
    'variation_forecast_high'=>$job['variation_forecast_high'],
    'variation_authorised'=>$variationRequired ? true : false,

    'work_already_value'=>$job['work_already_value'],
    'materials_already_value'=>$job['materials_already_value'],
    'payments_received_before_agreement'=>$job['payments_received'],
    'current_recorded_total'=>$tot['total'],
    'current_outstanding'=>$tot['outstanding'],

    'mike_hourly_rate'=>$job['agreed_hourly_rate'],
    'additional_workers'=>$workers,
    'payment_mode'=>$job['payment_mode'],
    'unpaid_balance_limit'=>$job['unpaid_balance_limit'],
    'revised_forecast_low'=>$job['revised_forecast_low'],
    'revised_forecast_high'=>$job['revised_forecast_high'],

    'current_account_reviewed'=>true,
    'authorised_continuation'=>true,
    'signatory_name'=>$name
];

$dir=__DIR__.'/../../uploads/job_signatures';
if(!is_dir($dir)) mkdir($dir,0755,true);

$file='job_'.$job['id'].'_'.time().'.png';
file_put_contents($dir.'/'.$file,$data);
$path='/uploads/job_signatures/'.$file;

$stmt=$pdo->prepare("UPDATE work_jobs SET
 agreement_name=?,
 agreement_signature_path=?,
 agreement_signed_at=NOW(),
 agreement_ip=?,
 status='active',
 agreement_version='3.0',
 acknowledged_current_balance=1,
 authorised_continuation=1,
 variation_authorised=?,
 variation_authorised_at=?,
 agreement_snapshot_json=?
 WHERE id=?");

$stmt->execute([
    $name,
    $path,
    $_SERVER['REMOTE_ADDR']??null,
    $variationRequired ? 1 : 0,
    $variationRequired ? date('Y-m-d H:i:s') : null,
    json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    $job['id']
]);

$msg="Mike of All Trades: thank you. Your job agreement".
     ($variationRequired ? " and fixed-price variation" : "").
     " were recorded at ".date('g:i a j M Y').
     ". Current recorded outstanding balance: ".wt_money($tot['outstanding']).
     ". Job record: ".wt_public_url($job);

wt_send_sms($pdo,(int)$job['id'],$job['customer_phone'],$msg,'agreement_signed');

header("Location: ../../work/job.php?t=".urlencode($token)."&signed=1");
