
/* Mike of All Trades Work Tracker V8.3 — progressive enhancement only.
   No forms, buttons, endpoints, or backend behaviour are changed. */
(() => {
  'use strict';

  const path = location.pathname;
  if (!/\/admin\/work\/(?:manage_job|job)\.php$/i.test(path)) return;

  const params = new URLSearchParams(location.search);
  const jobId = params.get('id') || 'unknown';
  const storageKey = 'mot-workspace-v83-job-' + jobId;

  const norm = s => (s || '').replace(/\s+/g, ' ').trim();

  const sectionMeta = title => {
    const t = title.toLowerCase();
    if (t.includes('customer request') || t.includes('intake'))
      return ['Customer request / intake', 'Original request, imported details and AI task breakdown'];
    if (t.includes('pricing') || t.includes('agreement'))
      return ['Pricing & agreement', 'Scope, agreement status, rates and payment arrangement'];
    if (t.includes('task') && !t.includes('photo'))
      return ['Tasks & progress', 'Individual work items, estimates and completion status'];
    if (t.includes('photo'))
      return ['Before / after photos', 'Task photos and job evidence'];
    if (t.includes('material') || t.includes('expense'))
      return ['Materials & expenses', 'Materials, supplier costs and reimbursements'];
    if (t.includes('payment'))
      return ['Payments', 'Payments received, balance and progress payments'];
    if (t.includes('recent sessions') || t.includes('history') || t.includes('tracked time'))
      return ['Work history', 'Previous sessions, tracked time and activity'];
    if (t.includes('session') || t.includes('start work') || t.includes('work now'))
      return ['Current work session', 'Start, stop and active work'];
    if (t.includes('change'))
      return ['Customer changes', 'Customer updates and requests awaiting review'];
    if (t.includes('daily') || t.includes('report') || t.includes('update'))
      return ['Daily reports & updates', 'Progress reports and customer communications'];
    if (t.includes('job source') || t.includes('contact origin'))
      return ['Job source & contact', 'Where the enquiry came from and original contact notes'];
    if (t.includes('travel'))
      return ['Travel & arrival', 'Travel status, ETA and arrival controls'];
    return [title, 'Open to view and edit this section'];
  };

  function stateLoad() {
    try { return JSON.parse(localStorage.getItem(storageKey) || '{}'); }
    catch (_) { return {}; }
  }
  function stateSave(state) {
    try { localStorage.setItem(storageKey, JSON.stringify(state)); }
    catch (_) {}
  }

  function directHeading(el) {
    for (const child of Array.from(el.children || [])) {
      if (/^H[234]$/.test(child.tagName)) return child;
    }
    return null;
  }

  function isCandidate(el) {
    if (!(el instanceof HTMLElement)) return false;
    if (el.closest('.wt83-section')) return false;
    if (el.classList.contains('wt83-jumpbar')) return false;

    const h = directHeading(el);
    if (!h) return false;

    const title = norm(h.textContent);
    if (!title || /^manage job/i.test(title)) return false;

    // Avoid tiny widgets and alert strips.
    const rect = el.getBoundingClientRect();
    const text = norm(el.innerText);
    if (text.length < 65) return false;
    if (/customer change alerts/i.test(title)) return false;

    // Prefer card/panel-like containers; screenshots show these are the long-scroll blocks.
    const cs = getComputedStyle(el);
    const looksCardLike =
      el.className.toString().toLowerCase().match(/card|panel|box|section/) ||
      parseFloat(cs.borderTopWidth || '0') > 0 ||
      parseFloat(cs.borderRadius || '0') >= 6 ||
      text.length > 180;

    return !!looksCardLike;
  }

  function findCandidates() {
    const main = document.querySelector('main') || document.body;
    const all = Array.from(main.querySelectorAll('section, div'));
    return all.filter(isCandidate).filter(el => {
      // Keep only outermost candidate so nested boxes do not become double accordions.
      return !all.some(other => other !== el && isCandidate(other) && other.contains(el));
    });
  }

  function liveSummary(el, title) {
    const text = norm(el.innerText);
    const low = title.toLowerCase();

    if (low.includes('customer request') || low.includes('intake')) {
      const ai = text.match(/AI breakdown:\s*(pending|running|complete|failed)/i);
      const lines = el.querySelector('textarea')?.value?.split(/\r?\n/).filter(x => x.trim()).length;
      const bits = [];
      if (lines) bits.push(lines + ' request item' + (lines === 1 ? '' : 's'));
      if (ai) bits.push('AI ' + ai[1].toLowerCase());
      if (bits.length) return bits.join(' · ');
    }

    if (low.includes('pricing') || low.includes('agreement')) {
      const agreement = text.match(/Customer agreement:\s*([^\n]+)/i);
      const bits = [];
      if (agreement) bits.push(norm(agreement[1]).slice(0, 45));
      const money = text.match(/Outstanding\s*\$[\d,.]+/i);
      if (money) bits.push(norm(money[0]));
      if (bits.length) return bits.join(' · ');
    }

    if (low.includes('task')) {
      const completed = (text.match(/completed/ig) || []).length;
      const notStarted = (text.match(/not started/ig) || []).length;
      if (completed || notStarted) return `${completed} completed · ${notStarted} not started`;
    }

    return sectionMeta(title)[1];
  }

  const state = stateLoad();
  const sections = [];

  for (const el of findCandidates()) {
    const heading = directHeading(el);
    if (!heading) continue;

    const originalTitle = norm(heading.textContent);
    const [friendlyTitle] = sectionMeta(originalTitle);
    const slugBase = friendlyTitle.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';
    let slug = slugBase;
    let i = 2;
    while (document.getElementById('wt83-' + slug)) slug = slugBase + '-' + i++;

    const children = Array.from(el.childNodes);
    const body = document.createElement('div');
    body.className = 'wt83-section-body';

    heading.classList.add('wt83-original-heading');

    for (const node of children) {
      if (node !== heading) body.appendChild(node);
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'wt83-section-toggle';
    button.setAttribute('aria-controls', 'wt83-body-' + slug);

    const left = document.createElement('span');
    left.className = 'wt83-section-heading';

    const titleSpan = document.createElement('span');
    titleSpan.className = 'wt83-section-title';
    titleSpan.textContent = friendlyTitle;

    const summary = document.createElement('span');
    summary.className = 'wt83-section-summary';
    summary.textContent = liveSummary(el, originalTitle);

    left.append(titleSpan, summary);

    const chevron = document.createElement('span');
    chevron.className = 'wt83-chevron';
    chevron.textContent = '▶';

    button.append(chevron, left);

    body.id = 'wt83-body-' + slug;
    el.id = 'wt83-' + slug;
    el.classList.add('wt83-section');

    // Default: Customer request and current work open. Everything else compact.
    const defaultOpen =
      /customer request|intake|current work|start work/i.test(originalTitle);
    const open = Object.prototype.hasOwnProperty.call(state, slug) ? !!state[slug] : defaultOpen;

    el.dataset.wt83Open = open ? '1' : '0';
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    chevron.textContent = open ? '▼' : '▶';

    el.insertBefore(button, el.firstChild);
    el.appendChild(body);

    button.addEventListener('click', () => {
      const next = el.dataset.wt83Open !== '1';
      el.dataset.wt83Open = next ? '1' : '0';
      button.setAttribute('aria-expanded', next ? 'true' : 'false');
      chevron.textContent = next ? '▼' : '▶';
      state[slug] = next;
      stateSave(state);
    });

    sections.push({el, slug, title:friendlyTitle, summary});
  }

  if (!sections.length) return;

  // Add compact sticky jump bar immediately before the first managed section.
  const bar = document.createElement('nav');
  bar.className = 'wt83-jumpbar';
  bar.setAttribute('aria-label', 'Job workspace sections');

  const title = document.createElement('span');
  title.className = 'wt83-jumpbar-title';
  title.textContent = 'Job workspace';

  const note = document.createElement('span');
  note.className = 'wt83-toolbar-note';
  note.textContent = 'Open only what you need';

  const links = document.createElement('div');
  links.className = 'wt83-jumpbar-links';

  for (const s of sections) {
    const a = document.createElement('a');
    a.href = '#wt83-' + s.slug;
    a.textContent = s.title;
    a.addEventListener('click', () => {
      if (s.el.dataset.wt83Open !== '1') {
        s.el.querySelector(':scope > .wt83-section-toggle')?.click();
      }
    });
    links.appendChild(a);
  }

  bar.append(title, note, links);
  sections[0].el.parentNode.insertBefore(bar, sections[0].el);

  // Refresh summaries periodically because AI/task/session status can change on this page.
  setInterval(() => {
    for (const s of sections) {
      const hiddenHeading = s.el.querySelector(':scope > .wt83-original-heading');
      const originalTitle = hiddenHeading ? norm(hiddenHeading.textContent) : s.title;
      s.summary.textContent = liveSummary(s.el, originalTitle);
    }
  }, 4000);
})();
