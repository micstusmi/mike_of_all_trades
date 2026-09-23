'use strict';
const $ = id => document.getElementById(id);
const safe = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let csrf = '';
let role = '';
let view = 'jobs';
function notice(message) { $('notice').textContent = message; }
async function api(path, payload) {
  const options = {credentials:'same-origin'};
  if (payload) {
    options.method = 'POST';
    options.headers = {'Content-Type':'application/x-www-form-urlencoded'};
    options.body = new URLSearchParams({...payload,csrf});
  }
  const response = await fetch(path, options);
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || 'The request could not be completed.');
  return result;
}
async function refreshSession() {
  const session = await api('/session');
  csrf = session.csrf;
  role = session.role || '';
  $('sign-in').hidden = !!session.authenticated;
  $('workspace').hidden = !session.authenticated;
  if (session.authenticated) {
    $('identity').textContent = `Business #${session.business_id} · ${role}`;
    await load(view);
  }
}
async function load(nextView) {
  view = nextView;
  document.querySelectorAll('nav button').forEach(button => button.setAttribute('aria-current',button.dataset.view === view ? 'page' : 'false'));
  const content = $('content');
  content.textContent = 'Loading…';
  if (view === 'customers') {
    const data = await api('/customers');
    content.innerHTML = '<h3>Customers</h3><p class="muted">Only this business’s customers appear here.</p>' + data.customers.map(x=>`<div class="card">${safe(x.name)} ${x.email ? '· '+safe(x.email):''}</div>`).join('') + '<form id="customer-form"><label>Customer name <input name="name" maxlength="190" required></label><button>Add customer</button></form>';
  } else if (view === 'jobs') {
    const [jobs,customers] = await Promise.all([api('/jobs'),api('/customers')]);
    content.innerHTML = '<h3>Jobs</h3>' + jobs.jobs.map(x=>`<div class="card"><strong>${safe(x.title)}</strong> · ${safe(x.status)} · Job #${safe(x.id)} <button type="button" data-job="${safe(x.id)}">Details</button><div id="job-detail-${safe(x.id)}"></div></div>`).join('') + '<details><summary>Add job</summary><form id="job-form"><label>Customer <select name="customer_id" required><option value="">Choose customer</option>' + customers.customers.map(x=>`<option value="${safe(x.id)}">${safe(x.name)}</option>`).join('') + '</select></label><label>Property <select name="property_id" required><option value="">Choose a customer first</option></select></label><label>Job title <input name="title" maxlength="190" required></label><button>Add draft job</button></form></details><details><summary>Add a property</summary><form id="property-form"><label>Customer <select name="customer_id" required><option value="">Choose customer</option>' + customers.customers.map(x=>`<option value="${safe(x.id)}">${safe(x.name)}</option>`).join('') + '</select></label><label>Address <input name="address" maxlength="500" required></label><button>Add property</button></form></details>';
  } else if (view === 'calendar') {
    const data = await api('/calendar/drafts');
    content.innerHTML = '<h3>Quick booking drafts</h3><p class="muted">Capture a booking now and complete the job details later. These drafts send no SMS and create no invoice. iPhone Calendar import is not connected yet.</p>' + data.drafts.map(x=>`<div class="card"><strong>${safe(x.title)}</strong> · ${safe(x.state)}<p>${safe(x.notes)}</p><small>${safe(x.starts_at_utc)} UTC · ${safe(x.timezone)}</small></div>`).join('') + '<form id="calendar-form"><label>Title <input name="title" maxlength="190" required></label><label>Notes <textarea name="notes" maxlength="10000"></textarea></label><label>Start <input name="start" type="datetime-local" required></label><label>End <input name="end" type="datetime-local" required></label><button>Save draft</button></form>';
  } else if (view === 'sms') {
    const [messages,customers,integrations] = await Promise.all([api('/sms/drafts'),api('/customers'),api('/integrations')]);
    content.innerHTML = `<h3>SMS drafts</h3><p class="muted">Sending is off for this business. These drafts are saved here only. Accounting choice: ${safe(integrations.accounting_provider)} (not connected).</p>` + messages.drafts.map(x=>`<div class="card"><strong>Customer #${safe(x.customer_id)}</strong> · ${safe(x.state)}<p>${safe(x.message)}</p></div>`).join('') + '<form id="sms-form"><label>Customer <select name="customer_id" required><option value="">Choose customer</option>' + customers.customers.map(x=>`<option value="${safe(x.id)}">${safe(x.name)}</option>`).join('') + '</select></label><label>Message <textarea name="message" maxlength="1600" required></textarea></label><button>Save SMS draft</button></form>';
  } else if (view === 'feedback') {
    const data = await api('/feedback');
    content.innerHTML = '<h3>Feature requests</h3><p class="muted">Your business can follow the status of its ideas here.</p>' + data.requests.map(x=>`<div class="card"><strong>${safe(x.title)}</strong> · ${safe(x.status)}<p>${safe(x.detail)}</p><small>${safe(x.area)}</small><p><button type="button" data-feedback="${safe(x.id)}">View updates</button></p><div id="feedback-updates-${safe(x.id)}"></div></div>`).join('') + '<form id="feedback-form"><label>Title <input name="title" maxlength="190" required></label><label>Area of the app <input name="area" maxlength="80" required></label><label>What would help? <textarea name="detail" maxlength="10000" required></textarea></label><button>Submit request</button></form>';
  } else if (view === 'usage') {
    const data = await api('/ai/usage');
    content.innerHTML = `<h3>AI usage</h3><p>Spent this month: A$${(data.spent_cents/100).toFixed(2)} · Reserved: A$${(data.reserved_cents/100).toFixed(2)} · Limit: A$${(data.monthly_limit_cents/100).toFixed(2)}</p><p class="muted">Paid AI actions are disabled while the alpha providers and billing are being built.</p>` + data.features.map(x=>`<div class="card">${safe(x.feature)} · ${safe(x.actions)} actions · A$${(Number(x.charge_cents)/100).toFixed(2)}</div>`).join('') + (role === 'owner' ? '<form id="budget-form"><label>Monthly AI limit in A$ <input name="aud_limit" type="number" min="0" max="10000" step="0.01" required></label><button>Save limit</button></form>' : '');
  } else if (view === 'members') {
    if (role === 'staff') { content.textContent = 'Member management is for owners and admins.'; return; }
    const data = await api('/members');
    content.innerHTML = '<h3>Members</h3><p class="muted">Disabling a member prevents their next request from accessing this business. Jobs remain owned by the business.</p>' + data.members.map(x=>`<div class="card">${safe(x.email)} · ${safe(x.role)}${x.disabled_at ? ' · disabled' : ''}${!x.disabled_at && (x.role === 'staff' || role === 'owner' && x.role === 'admin') && x.user_id != data.current_user_id ? ` <button data-disable="${safe(x.user_id)}" type="button">Disable access</button>` : ''}</div>`).join('');
  } else if (view === 'migration') {
    if (role !== 'owner') { content.textContent = 'Migration reports are for the business owner.'; return; }
    const data = await api('/migration/summary');
    const report = data.migration;
    content.innerHTML = report ? `<h3>Mike Work Tracker import</h3><p>Imported ${safe(report.imported.customer || 0)} customers, ${safe(report.imported.property || 0)} properties and ${safe(report.imported.job || 0)} jobs on ${safe(report.imported_at)} UTC.</p><p class="muted">This import is partial. Source tables below remain on Mike's site. Do not retire it yet.</p>` + Object.entries(report.inventory.deferred_counts).map(([name,count])=>`<div class="card">${safe(name)} · ${safe(count)} source records still to migrate or reconcile</div>`).join('') : '<h3>Migration</h3><p>No Mike Work Tracker data has been imported into this business.</p>';
  }
}
function values(form) { return Object.fromEntries(new FormData(form)); }
document.addEventListener('submit', async event => {
  const form = event.target;
  if (!form.id) return;
  event.preventDefault();
  try {
    if (form.id === 'login-form') { await api('/login',values(form)); notice('Signed in.'); await refreshSession(); }
    if (form.id === 'redeem-form') { await api('/redeem',values(form)); form.reset(); notice('Password set. Sign in with your business ID.'); }
    if (form.id === 'customer-form') { await api('/customers',values(form)); notice('Customer added.'); await load('customers'); }
    if (form.id === 'property-form') { await api('/properties',values(form)); notice('Property added.'); await load('jobs'); }
    if (form.id === 'job-form') { await api('/jobs',values(form)); notice('Draft job added.'); await load('jobs'); }
    if (form.id === 'feedback-form') { await api('/feedback',values(form)); notice('Feature request submitted.'); await load('feedback'); }
    if (form.id === 'calendar-form') {
      const fields = values(form);
      const start = new Date(fields.start), end = new Date(fields.end);
      if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) throw new Error('Enter valid start and end times.');
      await api('/calendar/drafts',{title:fields.title,notes:fields.notes,start:start.toISOString(),end:end.toISOString(),timezone:Intl.DateTimeFormat().resolvedOptions().timeZone});
      notice('Booking draft saved.'); await load('calendar');
    }
    if (form.id === 'sms-form') { await api('/sms/drafts',values(form)); notice('SMS draft saved. Nothing was sent.'); await load('sms'); }
    if (form.id === 'budget-form') {
      const amount = Number(form.elements.aud_limit.value);
      if (!Number.isSafeInteger(Math.round(amount*100)) || amount < 0) throw new Error('Enter a valid amount.');
      await api('/ai/budget',{monthly_limit_cents:String(Math.round(amount*100))}); notice('Limit saved.'); await load('usage');
    }
  } catch (error) { notice(error.message); }
});
document.addEventListener('change', async event => {
  if (event.target.matches('#job-form [name=customer_id]')) {
    const select = $('job-form').elements.property_id;
    select.innerHTML = '<option value="">Choose property</option>';
    if (event.target.value) {
      try {
        const data = await api('/properties?customer_id='+encodeURIComponent(event.target.value));
        data.properties.forEach(x=>select.add(new Option(x.address,x.id)));
      } catch (error) { notice(error.message); }
    }
  }
});
document.addEventListener('click', async event => {
  const button = event.target.closest('button');
  if (!button) return;
  try {
    if (button.dataset.view) await load(button.dataset.view);
    if (button.dataset.feedback) {
      const data = await api('/feedback/'+encodeURIComponent(button.dataset.feedback));
      const updates = $('feedback-updates-'+button.dataset.feedback);
      updates.innerHTML = data.request.updates.length ? data.request.updates.map(x=>`<p><strong>${safe(x.author_label)}:</strong> ${safe(x.message)} <small>${safe(x.created_at)} UTC</small></p>`).join('') : '<p class="muted">No updates yet.</p>';
    }
    if (button.dataset.job) {
      const data = await api('/jobs/'+encodeURIComponent(button.dataset.job));
      const job = data.job;
      $('job-detail-'+button.dataset.job).innerHTML = `<p>${job.mike_job_id ? 'Mike job #'+safe(job.mike_job_id)+' · ' : ''}Customer #${safe(job.customer_id)} · Property #${safe(job.property_id)}</p>${job.original_scope ? `<p>Original scope: ${safe(job.original_scope)}</p>` : ''}${job.current_scope ? `<p>Current scope: ${safe(job.current_scope)}</p>` : ''}${job.planned_start_at ? `<p>Planned start: ${safe(job.planned_start_at)}</p>` : ''}`;
    }
    if (button.id === 'logout') { await api('/logout',{}); notice('Signed out.'); await refreshSession(); }
    if (button.dataset.disable) {
      if (!confirm('Disable this member’s access to this business?')) return;
      await api('/members/disable',{user_id:button.dataset.disable}); notice('Member disabled.'); await load('members');
    }
  } catch (error) { notice(error.message); }
});
refreshSession().catch(error=>notice(error.message));
