<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/work_tracker.php';
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Import Customer Job List — Mike of All Trades</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f4f6f8;color:#17202a;margin:0}.wrap{max-width:900px;margin:auto;padding:22px}.card{background:#fff;border:1px solid #dfe5e9;border-radius:14px;padding:20px;margin:14px 0;box-shadow:0 2px 8px rgba(0,0,0,.04)}h1{margin:.2em 0}.small{color:#5d6973;font-size:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}label{display:block;font-weight:800;font-size:13px;margin:12px 0 5px}input,textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5dc;border-radius:9px;font:inherit}textarea{min-height:110px}.drop{border:2px dashed #9fb8ca;background:#f7fbfd;border-radius:14px;padding:24px;text-align:center}.btn{display:inline-block;background:#17202a;color:#fff;border:0;border-radius:10px;padding:13px 18px;font-weight:850;text-decoration:none;cursor:pointer}.btn.blue{background:#1769aa}.note{background:#eef7ff;border:1px solid #a9d0ee;border-radius:10px;padding:12px}.warning{background:#fff5d8;border:1px solid #e7c45f;border-radius:10px;padding:12px}.spinner{display:none;margin-top:12px;font-weight:800}@media(max-width:650px){.grid{grid-template-columns:1fr}}
</style></head><body><div class="wrap">
<p><a href="new.php">← New Job</a></p>
<h1>Import customer job list</h1>
<p class="small">Use screenshots, photos, a PDF or pasted text. The job is logged first, the original files are preserved, and AI then extracts the customer's requested items. The customer can use the live job link immediately.</p>
<div class="note"><b>For your Fontaine test:</b> enter <b>Fontaine Industries</b> as the organisation and <b>Niddrie</b> as the site, then upload the screenshots exactly as the customer sent them.</div>
<form id="intakeForm" class="card" method="post" enctype="multipart/form-data" action="../../api/work/create_intake_job.php">
<h2>Customer / site</h2>
<div class="grid"><div><label>Organisation / customer</label><input name="customer_organisation" placeholder="e.g. Fontaine Industries"></div><div><label>Contact name</label><input name="customer_name" placeholder="Customer contact name (optional)"></div></div>
<div class="grid"><div><label>Mobile for live-link SMS</label><input name="customer_phone" placeholder="04..."></div><div><label>Email</label><input name="customer_email" type="email"></div></div>
<div class="grid"><div><label>Site / showroom</label><input name="site_name" placeholder="e.g. Niddrie"></div><div><label>Street address (optional for draft)</label><input name="job_address" placeholder="Can be confirmed later"></div></div>
<h2>Customer's original material</h2>
<div class="drop"><b>Upload screenshots, photos or PDFs</b><br><span class="small">JPEG, PNG, WEBP or PDF. Up to 10 files. Originals are retained with the job.</span><br><br><input id="intakeFiles" type="file" name="intake_files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></div>
<label>Or paste / type the customer's list</label><textarea name="pasted_text" placeholder="Optional — paste any accompanying message or task list here."></textarea>
<div class="warning"><b>What happens next:</b> the draft job is created immediately. AI extracts the individual requested items without inventing missing details, flags ambiguous items, and then you review the extracted list before generating the granular task breakdown.</div>
<button class="btn blue" type="submit">LOG JOB & ANALYSE CUSTOMER LIST</button>
<div id="working" class="spinner">Logging job and analysing the supplied files… this can take a little while.</div>

<div class="card" style="margin-top:16px">
  <h2>Materials for this job</h2>
  <p class="muted">Tell the AI and Mike who is expected to provide the materials. This can be changed later.</p>

  <label for="materials_responsibility"><b>Who will provide the materials?</b></label>
  <select name="materials_responsibility" id="materials_responsibility" required style="width:100%;margin-top:6px">
    <option value="mike_advise" selected>Not sure — Mike to advise</option>
    <option value="mike_all">Mike to provide all materials</option>
    <option value="customer_all">We already have all materials</option>
    <option value="shared">We have some materials / Mike to provide some</option>
    <option value="labour_only">Labour only / no materials required</option>
  </select>

  <label for="materials_notes" style="display:block;margin-top:12px"><b>Materials notes (optional)</b></label>
  <textarea name="materials_notes" id="materials_notes" rows="3"
    placeholder="Example: We have the replacement tapware and vanity. Mike may need fixings and silicone."
    style="width:100%;margin-top:6px"></textarea>
</div>
</form>
</div>
<script>
document.getElementById('intakeForm').addEventListener('submit',function(){document.getElementById('working').style.display='block';const b=this.querySelector('button[type=submit]');b.disabled=true;b.textContent='WORKING…';});
</script></body></html>
