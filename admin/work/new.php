<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $token = bin2hex(random_bytes(32));
        $pricingType = $_POST['original_pricing_type'] ?? 'unspecified';
        $variationRequired = ($pricingType === 'fixed_price' && !empty($_POST['variation_required'])) ? 1 : 0;

        $stmt=$pdo->prepare("INSERT INTO work_jobs
        (public_token,customer_name,customer_phone,customer_email,job_address,
         original_scope,original_pricing_type,current_scope,unforeseen_conditions,
         variation_required,variation_description,variation_pricing_method,
         variation_fixed_amount,variation_hourly_rate,variation_forecast_low,variation_forecast_high,
         original_estimate_amount,original_estimate_hours,agreed_hourly_rate,payment_mode,unpaid_balance_limit,
         work_already_value,materials_already_value,payments_received,revised_forecast_low,revised_forecast_high,status)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->execute([
            $token,
            trim($_POST['customer_name']),
            trim($_POST['customer_phone']),
            trim($_POST['customer_email'] ?: ''),
            trim($_POST['job_address']),
            trim($_POST['original_scope']),
            $pricingType,
            trim($_POST['current_scope']),
            trim($_POST['unforeseen_conditions']),
            $variationRequired,
            trim($_POST['variation_description'] ?? ''),
            $_POST['variation_pricing_method'] ?? 'not_applicable',
            ($_POST['variation_fixed_amount'] ?? '') !== '' ? $_POST['variation_fixed_amount'] : null,
            ($_POST['variation_hourly_rate'] ?? '') !== '' ? $_POST['variation_hourly_rate'] : null,
            ($_POST['variation_forecast_low'] ?? '') !== '' ? $_POST['variation_forecast_low'] : null,
            ($_POST['variation_forecast_high'] ?? '') !== '' ? $_POST['variation_forecast_high'] : null,
            ($_POST['original_estimate_amount'] ?? '') !== '' ? $_POST['original_estimate_amount'] : null,
            ($_POST['original_estimate_hours'] ?? '') !== '' ? $_POST['original_estimate_hours'] : null,
            ($_POST['agreed_hourly_rate'] ?? '') !== '' ? $_POST['agreed_hourly_rate'] : null,
            $_POST['payment_mode'] ?? 'daily',
            ($_POST['unpaid_balance_limit'] ?? '') !== '' ? $_POST['unpaid_balance_limit'] : null,
            $_POST['work_already_value'] ?: 0,
            $_POST['materials_already_value'] ?: 0,
            $_POST['payments_received'] ?: 0,
            ($_POST['revised_forecast_low'] ?? '') !== '' ? $_POST['revised_forecast_low'] : null,
            ($_POST['revised_forecast_high'] ?? '') !== '' ? $_POST['revised_forecast_high'] : null,
            'awaiting_agreement'
        ]);

        $id=(int)$pdo->lastInsertId();
        header("Location: job.php?id=".$id);
        exit;
    } catch(Throwable $e){
        $error=$e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Job</title>
<style>
body{font-family:system-ui;background:#f4f6f8;margin:0;color:#17202a}
.wrap{max-width:880px;margin:auto;padding:18px}
.card{background:#fff;padding:20px;border-radius:14px;box-shadow:0 2px 10px #0001}
label{display:block;font-weight:700;margin-top:14px}
input,textarea,select{box-sizing:border-box;width:100%;padding:12px;border:1px solid #ccd1d5;border-radius:9px;font:inherit}
textarea{min-height:90px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{margin-top:18px;background:#17202a;color:white;border:0;border-radius:10px;padding:14px 18px;font-weight:800}
.help{font-size:13px;color:#65707b;margin-top:5px}
.warning{background:#fff8d9;border:1px solid #e3c55a;padding:12px;border-radius:9px}
.variation{background:#fff4ef;border:1px solid #f0ad8b;padding:14px;border-radius:12px;margin-top:18px}
.inlinecheck{display:flex;gap:9px;align-items:flex-start;margin-top:12px}.inlinecheck input{width:auto;margin-top:4px}
.hidden{display:none}
@media(max-width:650px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap"><div class="card">
<h1>New / Current Job</h1>
<div class="warning"><b>Already underway?</b> Record the position honestly as it exists now. This agreement applies from the time the customer signs and does not backdate terms.</div>

<?php if($error):?><p style="color:#b00"><?=wt_html($error)?></p><?php endif;?>

<form method="post" id="jobForm">
<div class="grid">
<div><label>Customer</label><input name="customer_name" required></div>
<div><label>Mobile</label><input name="customer_phone" required placeholder="04..."></div>
</div>
<div class="grid">
<div><label>Email</label><input name="customer_email" type="email"></div>
<div><label>Job address</label><input name="job_address" required></div>
</div>

<label>Original pricing arrangement</label>
<select name="original_pricing_type" id="pricingType" required onchange="toggleVariation()">
<option value="unspecified">Select...</option>
<option value="fixed_price">Fixed-price quote</option>
<option value="estimate">Estimate / approximate budget</option>
<option value="hourly">Hourly rate</option>
<option value="no_price">No specific price was agreed</option>
</select>
<div class="help">Choose what was actually discussed originally.</div>

<label>Original work discussed</label>
<textarea name="original_scope" required></textarea>

<label>Current / expanded scope</label>
<textarea name="current_scope" placeholder="Include additions requested after work began"></textarea>

<label>Unforeseen conditions / reasons forecast changed</label>
<textarea name="unforeseen_conditions" placeholder="e.g. wet plaster behind tiles, concealed rotten timber, supplier delays"></textarea>

<div id="fixedVariation" class="variation hidden">
<h3>Fixed-price variation</h3>
<p>If the original job was fixed-price, use this section only where the scope or circumstances have changed and you need the customer to expressly authorise a variation.</p>

<div class="inlinecheck">
<input type="checkbox" name="variation_required" value="1" id="variationRequired" onchange="toggleVariationFields()">
<label for="variationRequired" style="margin:0"><b>A variation to the original fixed-price scope is required.</b></label>
</div>

<div id="variationFields" class="hidden">
<label>Describe the variation / changed work</label>
<textarea name="variation_description" placeholder="What changed, why it changed, and what extra/different work is now required"></textarea>

<label>How will this variation be priced?</label>
<select name="variation_pricing_method" id="variationMethod" onchange="toggleVariationPricing()">
<option value="not_applicable">Select...</option>
<option value="fixed_amount">Fixed additional amount</option>
<option value="hourly">Hourly for varied work</option>
<option value="estimate">Estimated range for varied work</option>
</select>

<div id="variationFixed" class="hidden">
<label>Fixed additional amount $</label>
<input name="variation_fixed_amount" type="number" step=".01">
</div>

<div id="variationHourly" class="hidden">
<label>Hourly rate for varied work $</label>
<input name="variation_hourly_rate" type="number" step=".01">
</div>

<div id="variationEstimate" class="hidden">
<div class="grid">
<div><label>Variation forecast low $</label><input name="variation_forecast_low" type="number" step=".01"></div>
<div><label>Variation forecast high $</label><input name="variation_forecast_high" type="number" step=".01"></div>
</div>
</div>
</div>
</div>

<div class="grid">
<div><label>Original price / estimate $</label><input name="original_estimate_amount" type="number" step=".01"></div>
<div><label>Original expected hours</label><input name="original_estimate_hours" type="number" step=".25"></div>
</div>

<h3>Agreement from the time it is signed</h3>
<div class="grid">
<div><label>Mike's agreed hourly rate $</label><input name="agreed_hourly_rate" type="number" step=".01"></div>
<div><label>Payment mode</label>
<select name="payment_mode">
<option value="daily">Daily progress payments</option>
<option value="balance_limit">Unpaid-balance limit</option>
<option value="completion">Payment on completion</option>
<option value="milestone">Milestones</option>
</select>
</div>
</div>

<div class="grid">
<div><label>Maximum unpaid balance $</label><input name="unpaid_balance_limit" type="number" step=".01"></div>
<div></div>
</div>

<h3>Position before this new agreement</h3>
<div class="grid">
<div><label>Labour/work already performed $</label><input name="work_already_value" type="number" step=".01" value="0"></div>
<div><label>Mike-paid materials/expenses already incurred $</label><input name="materials_already_value" type="number" step=".01" value="0"></div>
</div>
<div class="grid">
<div><label>Payments already received $</label><input name="payments_received" type="number" step=".01" value="0"></div>
<div></div>
</div>

<h3>Current revised forecast</h3>
<div class="grid">
<div><label>Low $</label><input name="revised_forecast_low" type="number" step=".01"></div>
<div><label>High $</label><input name="revised_forecast_high" type="number" step=".01"></div>
</div>

<button class="btn">Create job & agreement link</button>
</form>
</div></div>

<script>
function toggleVariation(){
 const isFixed=document.getElementById('pricingType').value==='fixed_price';
 document.getElementById('fixedVariation').classList.toggle('hidden',!isFixed);
 if(!isFixed){
   document.getElementById('variationRequired').checked=false;
   document.getElementById('variationFields').classList.add('hidden');
 }
}
function toggleVariationFields(){
 const on=document.getElementById('variationRequired').checked;
 document.getElementById('variationFields').classList.toggle('hidden',!on);
}
function toggleVariationPricing(){
 const v=document.getElementById('variationMethod').value;
 document.getElementById('variationFixed').classList.toggle('hidden',v!=='fixed_amount');
 document.getElementById('variationHourly').classList.toggle('hidden',v!=='hourly');
 document.getElementById('variationEstimate').classList.toggle('hidden',v!=='estimate');
}
toggleVariation();
</script>
</body>
</html>
