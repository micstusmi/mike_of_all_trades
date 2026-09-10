<?php
require_once __DIR__ . '/../../includes/work_tracker.php';

$id=(int)($_GET['id']??0);
if($id<=0){http_response_code(400);die('Invalid job ID.');}
try{$job=wt_job($pdo,$id);}catch(Throwable $e){http_response_code(404);die('Job not found.');}

$tasks=$pdo->prepare("SELECT id,title,status FROM work_tasks WHERE job_id=? AND customer_visible=1 ORDER BY task_order,id");
$tasks->execute([$id]);
$tasks=$tasks->fetchAll(PDO::FETCH_ASSOC);

$q=$pdo->prepare("
 SELECT m.*, t.title AS task_title
 FROM work_materials m
 LEFT JOIN work_tasks t ON t.id=m.task_id
 WHERE m.job_id=?
 ORDER BY m.id DESC
");
$q->execute([$id]);
$materials=$q->fetchAll(PDO::FETCH_ASSOC);

$labels=[
'already_on_site'=>'Already on site',
'customer_will_supply'=>'Customer will supply',
'mike_to_purchase'=>'Mike to purchase',
'mike_has_it'=>'Mike already has it',
'maybe_required'=>'Maybe required',
'not_required'=>'Not required',
];
$sourceLabels=[
'supplier_purchase'=>'Supplier purchase',
'mike_vehicle_stock'=>'Mike supplied from vehicle / stock',
'customer_supplied'=>'Customer supplied',
'already_on_site'=>'Already on site',
'other'=>'Other',
];
$reimbLabels=[
'not_applicable'=>'Not applicable',
'reimbursement_due'=>'Reimbursement due',
'reimbursed'=>'Reimbursed',
'no_reimbursement_due'=>'No reimbursement due',
];
$matModeLabels=[
'mike_all'=>'Mike to provide all materials',
'customer_all'=>'Customer says they already have all materials',
'shared'=>'Customer has some / Mike to provide some',
'labour_only'=>'Labour only / no materials required',
'mike_advise'=>'Not sure — Mike to advise',
];

$totalActual=0.0;$due=0.0;
foreach($materials as $m){
    $a=(float)($m['actual_cost']??$m['cost']??0);
    $totalActual+=$a;
    if(($m['reimbursement_status']??'')==='reimbursement_due')$due+=$a;
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Materials — <?=wt_html($job['customer_name'])?></title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;margin:0;color:#17202a}.wrap{max-width:1050px;margin:auto;padding:16px}
.card{background:#fff;padding:18px;border-radius:14px;margin:12px 0;box-shadow:0 2px 10px #0001}.row{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
input,select,textarea{padding:10px;border:1px solid #ccd1d5;border-radius:8px;font:inherit;box-sizing:border-box}.grow{flex:1;min-width:180px}
.btn{display:inline-block;border:0;border-radius:10px;padding:11px 14px;font-weight:800;color:#fff;background:#17202a;text-decoration:none;cursor:pointer}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.metric{background:#f2f4f5;padding:12px;border-radius:10px}.big{font-size:22px;font-weight:850}
.item{border:1px solid #e1e5ea;border-radius:12px;padding:14px;margin:10px 0}.muted{color:#667085;font-size:13px}.tag{display:inline-block;padding:4px 8px;border-radius:999px;background:#edf2f7;font-size:12px;font-weight:750}
label{font-size:13px;font-weight:750;display:block;margin-bottom:4px}textarea{width:100%}@media(max-width:700px){.grid{grid-template-columns:1fr}.row>*{width:100%}}
</style></head><body><div class="wrap">
<p><a href="manage_job.php?id=<?=$id?>">← Back to Manage Job</a></p>
<h1>Materials &amp; expenses — Job #<?=$id?></h1>
<p><b><?=wt_html($job['customer_name'])?></b> · <?=wt_html($job['job_address'])?></p>

<div class="card">
<h2>Customer materials instruction</h2>
<p><b><?=wt_html($matModeLabels[$job['materials_responsibility']??'mike_advise']??$matModeLabels['mike_advise'])?></b></p>
<?php if(trim((string)($job['materials_notes']??''))!==''):?><p><?=nl2br(wt_html($job['materials_notes']))?></p><?php endif;?>
</div>

<div class="grid">
<div class="metric">Recorded items<div class="big"><?=count($materials)?></div></div>
<div class="metric">Actual material cost<div class="big"><?=wt_money($totalActual)?></div></div>
<div class="metric">Reimbursement due<div class="big"><?=wt_money($due)?></div></div>
</div>

<div class="card">
<h2>Add material / expense</h2>
<form method="post" action="../../api/work/add_material_v8_4b.php">
<input type="hidden" name="job_id" value="<?=$id?>">
<div class="row">
<div class="grow"><label>Material / item</label><input class="grow" style="width:100%" name="description" required placeholder="e.g. Silicone, wall anchors, replacement tap"></div>
<div class="grow"><label>Related task</label><select name="task_id" style="width:100%"><option value="">Whole job / not assigned</option><?php foreach($tasks as $t):?><option value="<?=$t['id']?>"><?=wt_html($t['title'])?></option><?php endforeach;?></select></div>
<div><label>Status</label><select name="material_status"><?php foreach($labels as $v=>$l):?><option value="<?=$v?>"><?=wt_html($l)?></option><?php endforeach;?></select></div>
</div><br>
<div class="row">
<div class="grow"><label>Supplier / source name</label><input name="supplier" style="width:100%" placeholder="Bunnings / Reece / customer / etc"></div>
<div><label>Estimated $</label><input name="estimated_cost" type="number" step=".01" min="0" style="width:130px"></div>
<div><label>Actual $</label><input name="actual_cost" type="number" step=".01" min="0" style="width:130px"></div>
<div><label>Source</label><select name="source_type"><?php foreach($sourceLabels as $v=>$l):?><option value="<?=$v?>"><?=wt_html($l)?></option><?php endforeach;?></select></div>
</div><br>
<div class="row">
<div><label>Paid / supplied by</label><select name="paid_by"><option value="mike">Mike</option><option value="customer">Customer</option><option value="other">Other</option></select></div>
<div><label>Reimbursement</label><select name="reimbursement_status"><?php foreach($reimbLabels as $v=>$l):?><option value="<?=$v?>"><?=wt_html($l)?></option><?php endforeach;?></select></div>
</div><br>
<label>Notes</label><textarea name="notes" rows="2" placeholder="Size, colour, product code, why it was needed, replacement by customer, etc"></textarea><br>
<button class="btn">Add material</button>
</form>
</div>

<div class="card">
<h2>Recorded materials</h2>
<?php if(!$materials):?><p class="muted">No materials recorded yet.</p><?php endif;?>
<?php foreach($materials as $m):?>
<div class="item">
<form method="post" action="../../api/work/update_material_v8_4b.php">
<input type="hidden" name="job_id" value="<?=$id?>"><input type="hidden" name="material_id" value="<?=$m['id']?>">
<div class="row">
<div class="grow"><b><?=wt_html($m['description'])?></b><div class="muted"><?=wt_html($m['task_title']?:'Whole job / not assigned')?></div></div>
<span class="tag"><?=wt_html($labels[$m['material_status']??'mike_to_purchase']??$m['material_status'])?></span>
</div><br>
<div class="row">
<div class="grow"><label>Task</label><select name="task_id" style="width:100%"><option value="">Whole job / not assigned</option><?php foreach($tasks as $t):?><option value="<?=$t['id']?>" <?=$m['task_id']==$t['id']?'selected':''?>><?=wt_html($t['title'])?></option><?php endforeach;?></select></div>
<div><label>Status</label><select name="material_status"><?php foreach($labels as $v=>$l):?><option value="<?=$v?>" <?=($m['material_status']??'')===$v?'selected':''?>><?=wt_html($l)?></option><?php endforeach;?></select></div>
<div class="grow"><label>Supplier</label><input name="supplier" value="<?=wt_html($m['supplier']??'')?>" style="width:100%"></div>
</div><br>
<div class="row">
<div><label>Estimated $</label><input name="estimated_cost" type="number" step=".01" min="0" value="<?=wt_html((string)($m['estimated_cost']??''))?>" style="width:130px"></div>
<div><label>Actual $</label><input name="actual_cost" type="number" step=".01" min="0" value="<?=wt_html((string)($m['actual_cost']??$m['cost']??''))?>" style="width:130px"></div>
<div><label>Source</label><select name="source_type"><?php foreach($sourceLabels as $v=>$l):?><option value="<?=$v?>" <?=($m['source_type']??'')===$v?'selected':''?>><?=wt_html($l)?></option><?php endforeach;?></select></div>
<div><label>Paid by</label><select name="paid_by"><option value="mike" <?=$m['paid_by']==='mike'?'selected':''?>>Mike</option><option value="customer" <?=$m['paid_by']==='customer'?'selected':''?>>Customer</option><option value="other" <?=$m['paid_by']==='other'?'selected':''?>>Other</option></select></div>
<div><label>Reimbursement</label><select name="reimbursement_status"><?php foreach($reimbLabels as $v=>$l):?><option value="<?=$v?>" <?=($m['reimbursement_status']??'')===$v?'selected':''?>><?=wt_html($l)?></option><?php endforeach;?></select></div>
</div><br>
<label>Notes</label><textarea name="notes" rows="2"><?=wt_html($m['notes']??'')?></textarea><br>
<button class="btn">Save changes</button>
</form>
</div>
<?php endforeach;?>
</div>
</div></body></html>
