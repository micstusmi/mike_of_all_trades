<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
$jobs = $pdo->query("
    SELECT *
    FROM work_jobs
    ORDER BY
        (last_opened_at IS NULL) ASC,
        last_opened_at DESC,
        updated_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
$initialFind=trim((string)($_GET['find']??''));
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Work Tracker</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;margin:0;color:#17202a}.wrap{max-width:900px;margin:auto;padding:18px}.card{background:white;border-radius:14px;padding:18px;margin:12px 0;box-shadow:0 2px 10px #0001}.btn{display:inline-block;padding:12px 16px;border-radius:10px;background:#17202a;color:#fff;text-decoration:none;border:0;font-weight:700}.status{font-size:12px;text-transform:uppercase;font-weight:800}.money{font-size:22px;font-weight:800}.job-search{width:100%;box-sizing:border-box;padding:13px;border:1px solid #aeb9c4;border-radius:10px;font:inherit;background:#fff}.no-results{display:none;padding:18px;text-align:center;color:#66717c}
</style></head><body>

<?php $adminPageTitle='Work Tracker';$adminBreadcrumbs=['Work Tracker'=>''];require __DIR__ . '/../../includes/admin_nav.php'; ?>

<div class="wrap">
<h1>Jobs and customers</h1><p><a class="btn" href="new.php">+ New / Current Job</a></p>
<label for="jobSearch"><b>Search jobs and customers</b></label><input id="jobSearch" class="job-search" type="search" value="<?=wt_html($initialFind)?>" placeholder="Name, company, phone, email, address or job number">
<p class="small">This search only filters your authorised Work Tracker records. Use “Find anything” above to find actions such as slideshow, receipt or pricing.</p>
<div id="jobList"><?php foreach($jobs as $j): $t=wt_totals($pdo,(int)$j['id']); ?>
<div class="card job-card" data-search="<?=wt_html(mb_strtolower(implode(' ',[(string)$j['id'],(string)$j['customer_name'],(string)($j['customer_company']??''),(string)($j['customer_email']??''),(string)($j['customer_phone']??''),(string)($j['job_address']??''),(string)$j['status']])) )?>"><div class="status"><?=wt_html($j['status'])?></div>
<h2><?=wt_html($j['customer_name'])?></h2><div><?=wt_html($j['job_address'])?></div>
<p class="money"><?=wt_money($t['outstanding'])?> outstanding</p>
<a class="btn" href="job.php?id=<?=$j['id']?>">Open job</a>
</div><?php endforeach; ?></div><div id="noJobResults" class="no-results">No jobs or customers match that search.</div>
<script>(()=>{const input=document.getElementById('jobSearch'),cards=[...document.querySelectorAll('.job-card')],empty=document.getElementById('noJobResults');function filter(){const words=input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);let shown=0;cards.forEach(card=>{const match=words.every(w=>card.dataset.search.includes(w));card.hidden=!match;if(match)shown++});empty.style.display=shown?'none':'block'}input.addEventListener('input',filter);filter()})();</script>
</div></body></html>
