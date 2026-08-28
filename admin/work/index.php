<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$jobs = $pdo->query("SELECT * FROM work_jobs ORDER BY FIELD(status,'active','paused','awaiting_agreement','draft','completed','cancelled'), updated_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Work Tracker</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;margin:0;color:#17202a}.wrap{max-width:900px;margin:auto;padding:18px}.card{background:white;border-radius:14px;padding:18px;margin:12px 0;box-shadow:0 2px 10px #0001}.btn{display:inline-block;padding:12px 16px;border-radius:10px;background:#17202a;color:#fff;text-decoration:none;border:0;font-weight:700}.status{font-size:12px;text-transform:uppercase;font-weight:800}.money{font-size:22px;font-weight:800}
</style></head><body>

<?php require __DIR__ . '/../../includes/admin_nav.php'; ?>

<div class="wrap">
<h1>Work Tracker</h1><p><a class="btn" href="new.php">+ New / Current Job</a></p>
<?php foreach($jobs as $j): $t=wt_totals($pdo,(int)$j['id']); ?>
<div class="card"><div class="status"><?=wt_html($j['status'])?></div>
<h2><?=wt_html($j['customer_name'])?></h2><div><?=wt_html($j['job_address'])?></div>
<p class="money"><?=wt_money($t['outstanding'])?> outstanding</p>
<a class="btn" href="job.php?id=<?=$j['id']?>">Open job</a>
</div><?php endforeach; ?>
</div></body></html>
