(function () {
  'use strict';
  const root = document.getElementById('analysisRoot');
  if (!root) { return; }
  const id = +document.getElementById('contractRoot').dataset.id;
  const h = TT.esc;
  let G = null, running = false, progress = null;
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const CLAUSE = t => TT.label(String(t || 'other').replace(/_/g, ' '));
  const REVIEW = { pending: ['Needs review', 'st-due'], reviewed: ['Reviewed', 'st-completed'], dismissed: ['Dismissed', 'st-cancelled'] };

  function stageText(j) {
    if (!j) { return ''; }
    if (j.stage === 'extracting') { return 'Preparing the document…'; }
    if (j.stage === 'saving_results') { return 'Saving results…'; }
    if (j.phase === 'summary') { return 'Writing the summary…'; }
    if (j.phase === 'embeddings') { return 'Indexing the document for Copilot…'; }
    if (j.phase === 'sections') { return 'Analyzing section ' + Math.min(j.total_chunks, j.next_chunk + 1) + ' of ' + j.total_chunks + '…'; }
    return 'Working…';
  }

  function progressHtml() {
    const j = progress || (G && G.job);
    if (!j || !['extracting', 'analyzing', 'saving_results'].includes(j.stage)) { return ''; }
    return '<div class="glass p-3 mb-3" role="status" aria-live="polite"><div class="d-flex justify-content-between small mb-1"><span>' + h(stageText(j)) + '</span><span>' + j.progress + '%</span></div>' +
      '<div class="progress" role="progressbar" aria-valuenow="' + j.progress + '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:' + j.progress + '%"></div></div>' +
      (j.truncated ? '<div class="small text-warning mt-2">This contract is very long: only the first part of the document is analyzed.</div>' : '') + '</div>';
  }

  function listHtml(title, items) {
    return items && items.length ? '<h3 class="h6 mt-3">' + h(title) + '</h3><ul class="mb-0">' + items.map(x => '<li>' + h(x) + '</li>').join('') + '</ul>' : '';
  }

  function summaryHtml(a) {
    const s = a && a.summary;
    if (!s) { return ''; }
    const kd = (s.key_dates || []).length ? '<h3 class="h6 mt-3">Key dates</h3><ul class="mb-0">' + s.key_dates.map(d => '<li>' + h(d.label) + ': ' + (d.date ? h(TT.fmtDate(d.date)) : '<span class="text-muted">not specified</span>') + (d.note ? ' <small class="text-muted">(' + h(d.note) + ')</small>' : '') + '</li>').join('') + '</ul>' : '';
    return '<div class="glass p-4 mb-3"><div class="d-flex justify-content-between align-items-start"><h2 class="h6 mb-2">AI summary</h2><span class="badge-status st-active">AI-generated</span></div>' +
      '<p class="mb-2">' + h(s.executive_summary) + '</p>' + (s.purpose ? '<p class="small text-muted mb-2"><strong>Purpose:</strong> ' + h(s.purpose) + '</p>' : '') +
      ((s.parties || []).length ? '<p class="small text-muted mb-2"><strong>Parties:</strong> ' + s.parties.map(h).join(', ') + '</p>' : '') +
      (s.detailed_explanation ? '<details class="mt-2"><summary>Detailed plain-English explanation</summary><div class="pre-wrap mt-2">' + h(s.detailed_explanation) + '</div></details>' : '') +
      kd + listHtml('Financial terms', s.financial_terms) + listHtml('Main commitments', s.main_commitments) + listHtml('Termination conditions', s.termination_conditions) +
      listHtml('Renewal terms', s.renewal_terms) + listHtml('Important limitations', s.important_limitations) + '</div>';
  }

  function riskHtml() {
    const r = G.risk;
    if (!r) { return ''; }
    const rows = r.breakdown.map(b => '<tr><td>' + h(b.title) + '</td><td>' + TT.riskBadge(b.severity) + '</td><td class="text-end">' + b.points + '</td></tr>').join('');
    return '<div class="glass p-4 mb-3"><div class="d-flex align-items-center gap-4 flex-wrap"><div class="risk-gauge rk-g-' + h(r.level) + '" aria-label="Risk score ' + r.score + ' of 100"><span>' + r.score + '</span><small>/100</small></div>' +
      '<div><h2 class="h6 mb-1">Heuristic risk indicator: ' + TT.riskBadge(r.level) + '</h2><div class="small text-muted">High ' + r.counts.high + ' · Medium ' + r.counts.medium + ' · Low ' + r.counts.low + ' open findings. This is a heuristic indicator, not a legally validated score.</div></div></div>' +
      '<details class="mt-3"><summary class="small">How is this calculated?</summary><p class="small text-muted mt-2">' + h(r.formula) + '</p>' +
      (rows ? '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Finding</th><th>Severity</th><th class="text-end">Points</th></tr></thead><tbody>' + rows + '</tbody></table></div>' : '') + '</details></div>';
  }

  function evidenceHtml(f) {
    if (!f.source_quote) { return '<div class="small mt-2"><span class="badge-status st-planned">AI assessment — no document quotation</span></div>'; }
    const ok = f.quote_verified == 1;
    return '<blockquote class="quote' + (ok ? '' : ' quote-unverified') + '">' + h(f.source_quote) + '</blockquote><div class="small mb-2">' +
      (ok ? '<span class="badge-status st-completed">Quotation verified in the document</span>' : '<span class="badge-status st-overdue">Quotation NOT found in the document — treat as unverified</span>') + '</div>';
  }

  function findingsHtml() {
    const fs = G.findings;
    if (!fs.length) { return G.analysis && G.analysis.mode === 'ai' ? '<div class="glass p-4 mb-3"><h2 class="h6">Risk findings</h2><p class="text-muted mb-0">No potentially risky, ambiguous or missing terms were reported.</p></div>' : ''; }
    return '<div class="glass p-4 mb-3"><h2 class="h6 mb-3">Risk findings <small class="text-muted">(' + fs.length + ')</small></h2>' + fs.map(f => {
      const rv = REVIEW[f.review_status] || REVIEW.pending;
      return '<div class="finding sev-' + h(f.severity) + '"><div class="d-flex justify-content-between gap-2 flex-wrap"><div>' + TT.riskBadge(f.severity) + ' <strong>' + h(f.title) + '</strong>' +
        (f.is_inferred == 1 ? ' <span class="badge-status st-planned">Inferred</span>' : '') + '</div><span class="badge-status ' + rv[1] + '">' + rv[0] + '</span></div>' +
        '<p class="mb-1 mt-2">' + h(f.description) + '</p>' + (f.reason ? '<p class="small text-muted mb-1"><strong>Why:</strong> ' + h(f.reason) + '</p>' : '') + evidenceHtml(f) +
        (f.suggested_action ? '<p class="small mb-1"><strong>Suggested follow-up:</strong> ' + h(f.suggested_action) + '</p>' : '') +
        '<div class="small text-muted">' + (f.clause_type ? 'Clause: ' + h(CLAUSE(f.clause_type)) : '') + (f.confidence != null ? ' · AI confidence ' + Math.round(f.confidence * 100) + '%' : '') + '</div>' +
        (f.reviewer_comment ? '<p class="small mt-2 mb-0"><em>' + h(f.reviewed_by_name || 'Reviewer') + ': ' + h(f.reviewer_comment) + '</em></p>' : '') +
        (G.can_review ? '<div class="mt-2 d-flex gap-2 flex-wrap">' + (f.review_status === 'pending'
          ? '<button class="btn btn-outline-secondary btn-sm" data-act="review" data-status="reviewed" data-id="' + f.id + '">Mark reviewed</button><button class="btn btn-outline-secondary btn-sm" data-act="review" data-status="dismissed" data-id="' + f.id + '">Dismiss</button>'
          : '<button class="btn btn-outline-secondary btn-sm" data-act="review" data-status="pending" data-id="' + f.id + '">Reopen</button>') + '</div>' : '') + '</div>';
    }).join('') + '</div>';
  }

  function clausesHtml() {
    if (!G.clauses.length) { return ''; }
    const groups = {}; G.clauses.forEach(c => { (groups[c.clause_type] = groups[c.clause_type] || []).push(c); });
    return '<div class="glass p-4 mb-3"><h2 class="h6 mb-3">Detected clauses <small class="text-muted">(' + G.clauses.length + ')</small></h2>' + Object.keys(groups).map(t =>
      '<details class="mb-2"><summary><strong>' + h(CLAUSE(t)) + '</strong> <span class="text-muted small">(' + groups[t].length + ')</span></summary>' + groups[t].map(c =>
        '<div class="ms-3 mt-2"><div>' + h(c.summary) + '</div>' + (c.source_quote ? '<blockquote class="quote' + (c.quote_verified == 1 ? '' : ' quote-unverified') + '">' + h(c.source_quote) + '</blockquote><div class="small text-muted">' +
        (c.quote_verified == 1 ? 'Verified' : 'Unverified quotation') + (c.chunk_index != null ? ' · Section ' + (+c.chunk_index + 1) + ' of the document' : '') + '</div>' : '') + '</div>').join('') + '</details>').join('') + '</div>';
  }

  function suggestionsHtml() {
    const ss = G.suggestions;
    if (!ss.length) { return ''; }
    const pending = ss.filter(s => s.status === 'pending'), done = ss.filter(s => s.status !== 'pending');
    const card = s => '<div class="finding"><div class="d-flex justify-content-between gap-2 flex-wrap"><div><span class="badge-status st-open">' + (s.kind === 'payment' ? 'Payment' : 'Obligation') + '</span> <strong>' + h(s.title) + '</strong></div>' +
      '<span class="badge-status ' + (s.status === 'approved' ? 'st-completed' : s.status === 'rejected' ? 'st-cancelled' : 'st-due') + '">' + h(TT.label(s.status === 'pending' ? 'awaiting review' : s.status)) + '</span></div>' +
      (s.description ? '<p class="mb-1 mt-2">' + h(s.description) + '</p>' : '') +
      '<div class="small text-muted">' + (s.amount ? h(TT.money(s.amount, s.currency || 'INR')) + ' · ' : '') + (s.due_date ? 'Due ' + h(TT.fmtDate(s.due_date)) : 'No due date found') + (s.recurrence ? ' · Repeats ' + h(s.recurrence) : '') + (s.responsible_party ? ' · ' + h(s.responsible_party) : '') + '</div>' +
      (s.source_quote ? '<blockquote class="quote' + (s.quote_verified == 1 ? '' : ' quote-unverified') + '">' + h(s.source_quote) + '</blockquote><div class="small mb-1">' + (s.quote_verified == 1 ? '<span class="badge-status st-completed">Quotation verified</span>' : '<span class="badge-status st-overdue">Quotation not verified</span>') + '</div>' : '') +
      (s.reject_reason ? '<div class="small text-muted">Reason: ' + h(s.reject_reason) + '</div>' : '') +
      (s.status === 'pending' && G.can_write ? '<div class="mt-2 d-flex gap-2"><button class="btn btn-primary btn-sm" data-act="approve" data-id="' + s.id + '">Review &amp; approve</button><button class="btn btn-outline-secondary btn-sm" data-act="reject" data-id="' + s.id + '">Reject</button></div>' : '') + '</div>';
    return '<div class="glass p-4 mb-3"><h2 class="h6 mb-1">Suggested obligations &amp; payments</h2><p class="small text-muted">Suggestions are not commitments. Only the ones you approve become obligations or payments.</p>' +
      (pending.length ? pending.map(card).join('') : '<p class="text-muted small">Nothing awaiting review.</p>') +
      (done.length ? '<details class="mt-2"><summary class="small">Decided suggestions (' + done.length + ')</summary>' + done.map(card).join('') + '</details>' : '') + '</div>';
  }

  function datesHtml() {
    const d = G.date_suggestions || [];
    if (!d.length) { return ''; }
    return '<div class="glass p-4 mb-3"><h2 class="h6 mb-1">Suggested key dates</h2><p class="small text-muted">Found in the document. Apply a date to copy it into the contract record.</p>' + d.map(x =>
      '<div class="finding"><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong>' + h(TT.label(x.field.replace(/_/g, ' '))) + ':</strong> ' + (x.field === 'notice_period_days' ? h(x.value) + ' days' : h(TT.fmtDate(x.value))) + '</div>' +
      '<div>' + (x.verified && G.can_write ? '<button class="btn btn-outline-secondary btn-sm" data-act="apply" data-field="' + h(x.field) + '">Apply to contract</button>' : (!x.verified ? '<span class="badge-status st-overdue">Unverified</span>' : '')) + '</div></div>' +
      (x.quote ? '<blockquote class="quote' + (x.verified ? '' : ' quote-unverified') + '">' + h(x.quote) + '</blockquote>' : '') + (x.note ? '<div class="small text-warning">' + h(x.note) + '</div>' : '') + '</div>').join('') + '</div>';
  }

  function headerHtml() {
    const a = G.analysis, j = G.job, canRun = G.can_write && G.has_text;
    const busy = running || (j && ['extracting', 'analyzing', 'saving_results'].includes(j.stage));
    let btns = '';
    if (canRun && !running) {
      if (j && ['extracting', 'analyzing', 'saving_results'].includes(j.stage)) { btns = '<button class="btn btn-primary" data-act="run" data-restart="0">Continue analysis</button>'; }
      else if (j && j.stage === 'failed') { btns = '<button class="btn btn-primary" data-act="run" data-restart="0">' + (j.resumable ? 'Resume analysis' : 'Retry analysis') + '</button><button class="btn btn-outline-secondary" data-act="run" data-restart="1">Start over</button>'; }
      else { btns = '<button class="btn btn-primary" data-act="run" data-restart="' + (a ? 1 : 0) + '">' + (a ? 'Re-run analysis' : (G.ai.configured ? 'Run AI analysis' : 'Run basic extraction')) + '</button>'; }
    }
    const state = a ? (a.mode === 'ai' ? '<span class="badge-status st-active">AI analysis · ' + h(a.model || '') + '</span>' : '<span class="badge-status st-planned">Basic extraction (no AI)</span>') : '';
    let banner = '';
    if (!G.has_text) { banner = '<div class="alert alert-warning mb-0">No text could be extracted from this contract' + (G.extraction_error ? ': ' + h(G.extraction_error) : '') + '. Upload a text-based PDF, DOCX or TXT file to analyze it.</div>'; }
    else if (!G.ai.configured) { banner = '<div class="alert alert-info mb-0">AI analysis is not configured (<code>GEMINI_API_KEY</code> is missing), so only basic date and amount suggestions are available. No summary, clause classification or risk score is generated.</div>'; }
    else if (j && j.stage === 'failed') { banner = '<div class="alert alert-danger mb-0" role="alert"><strong>Analysis failed:</strong> ' + h(j.error_message || 'Unknown error') + (a ? ' Previous results are shown below.' : '') + '</div>'; }
    else if (a && a.notes && a.notes.truncated) { banner = '<div class="alert alert-warning mb-0">This contract is very long, so only the first part of the document was analyzed.</div>'; }
    return '<div class="glass p-3 mb-3"><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div>' + state + (a ? ' <small class="text-muted">' + h(TT.fmtDateTime(a.created_at)) + '</small>' : '') + '</div><div class="d-flex gap-2">' + btns + '</div></div>' +
      (banner ? '<div class="mt-3">' + banner + '</div>' : '') + '</div>' +
      '<p class="small text-muted">AI findings are suggestions that require human review. TermTrail AI provides informational assistance, not legal advice.</p>';
  }

  function render() {
    if (!G) { return; }
    const a = G.analysis;
    root.innerHTML = headerHtml() + (G.stale ? '<div class="alert alert-warning" role="status">A newer document version was uploaded after this analysis. Re-run the analysis to refresh the results.</div>' : '') + progressHtml() +
      (a ? summaryHtml(a) + riskHtml() + findingsHtml() + suggestionsHtml() + datesHtml() + clausesHtml()
        : (G.has_text ? '<div class="empty-state"><i data-lucide="sparkles"></i><p class="mb-0">No analysis yet. ' + (G.can_write ? 'Run the analysis to see a summary, risks, key dates and suggested obligations.' : 'The contract owner can run the analysis.') + '</p></div>' : ''));
    const badge = document.getElementById('analysisBadge');
    const open = G.suggestions.filter(s => s.status === 'pending').length + G.findings.filter(f => f.review_status === 'pending').length;
    if (badge) { badge.textContent = open; badge.classList.toggle('d-none', open === 0); }
    if (window.lucide) { lucide.createIcons(); }
  }

  async function load() {
    try { G = await TT.api('analysis/get', { query: '&contract_id=' + id }); render(); }
    catch (e) { root.innerHTML = '<div class="alert alert-danger">' + h(e.message || 'Could not load the analysis.') + '</div>'; }
  }

  async function run(restart) {
    if (running) { return; }
    if (restart && G.analysis && !(await TT.confirm('Re-running replaces the current findings and their review status. Suggestions you already approved or rejected are kept. Continue?', { title: 'Re-run analysis?', okText: 'Re-run' }))) { return; }
    running = true; progress = { stage: 'extracting', progress: 0, phase: null, next_chunk: 0, total_chunks: 0 }; render();
    try {
      const r = await TT.api('analysis/start', { method: 'POST', body: { contract_id: id, restart: !!restart } });
      if (r.mode === 'fallback') { TT.toast('Basic extraction finished. AI analysis is not configured.'); return; }
      let job = r.job;
      while (job && !['completed', 'failed'].includes(job.stage)) {
        progress = job; render();
        const s = await TT.api('analysis/step', { method: 'POST', body: { contract_id: id } });
        job = s.job;
        if (job.busy) { await sleep(1500); }
      }
      if (job && job.stage === 'completed') { TT.toast('Analysis complete.'); } else if (job) { TT.toast('Analysis failed: ' + (job.error_message || 'unknown error'), 'err'); }
    } catch (e) { TT.toast(e.message || 'Analysis failed.', 'err'); }
    finally { running = false; progress = null; await load(); }
  }

  const fld = (name, label, val, type = 'text', extra = '') => '<div class="mb-3"><label class="form-label" for="m_' + name + '">' + label + '</label><input class="form-control" id="m_' + name + '" name="' + name + '" type="' + type + '" value="' + h(val == null ? '' : val) + '" ' + extra + '><div class="invalid-feedback" data-error-for="' + name + '"></div></div>';
  const sel = (name, label, opts, val) => '<div class="mb-3"><label class="form-label" for="m_' + name + '">' + label + '</label><select class="form-select" id="m_' + name + '" name="' + name + '">' + opts.map(o => '<option value="' + h(o[0]) + '"' + (o[0] === val ? ' selected' : '') + '>' + h(o[1]) + '</option>').join('') + '</select></div>';

  async function approve(s) {
    const quote = s.source_quote ? '<blockquote class="quote">' + h(s.source_quote) + '</blockquote>' : '';
    const body = s.kind === 'obligation'
      ? fld('title', 'Title', s.title, 'text', 'maxlength="255"') + fld('description', 'Description', s.description) + fld('responsible_party', 'Responsible party', s.responsible_party) +
        '<div class="row"><div class="col-md-6">' + fld('due_date', 'Due date', s.due_date, 'date') + '</div><div class="col-md-6">' + sel('recurrence', 'Recurrence', [['none', 'None'], ['weekly', 'Weekly'], ['monthly', 'Monthly'], ['quarterly', 'Quarterly'], ['yearly', 'Yearly']], s.recurrence || 'none') + '</div></div>' +
        sel('priority', 'Priority', [['low', 'Low'], ['medium', 'Medium'], ['high', 'High']], 'medium')
      : '<div class="row"><div class="col-md-8">' + fld('amount', 'Amount', s.amount, 'text', 'inputmode="decimal"') + '</div><div class="col-md-4">' + sel('currency', 'Currency', ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD', 'JPY'].map(c => [c, c]), s.currency || 'INR') + '</div></div>' +
        fld('due_date', 'Due date (required)', s.due_date, 'date') + fld('category', 'Category', s.title, 'text', 'maxlength="80"') + fld('reference_number', 'Reference number', '');
    await TT.formModal({
      title: 'Review & approve ' + (s.kind === 'payment' ? 'payment' : 'obligation'), okText: 'Approve and create',
      html: '<p class="small text-muted">Edit anything that is wrong before approving. Nothing is created until you approve.</p>' + quote + body,
      onSubmit: async v => { const r = await TT.api('suggestions/approve', { method: 'POST', body: { id: s.id, overrides: v } }); TT.toast(r.message); },
    });
    await load();
  }

  async function reject(s) {
    const done = await TT.formModal({ title: 'Reject suggestion', okText: 'Reject', html: '<p class="mb-2"><strong>' + h(s.title) + '</strong></p>' + fld('reason', 'Reason (optional)', '', 'text', 'maxlength="250"'),
      onSubmit: async v => { const r = await TT.api('suggestions/reject', { method: 'POST', body: { id: s.id, reason: v.reason } }); TT.toast(r.message); } });
    if (done) { await load(); }
  }

  async function review(f, status) {
    const title = status === 'dismissed' ? 'Dismiss finding' : status === 'reviewed' ? 'Mark finding as reviewed' : 'Reopen finding';
    const done = await TT.formModal({ title, okText: 'Save', html: '<p class="mb-2"><strong>' + h(f.title) + '</strong></p><div class="mb-1"><label class="form-label" for="m_comment">Comment (optional)</label><textarea class="form-control" id="m_comment" name="comment" rows="3" maxlength="1000"></textarea></div>' +
      (status === 'dismissed' ? '<p class="small text-muted mb-0">Dismissed findings are excluded from the risk score.</p>' : ''),
      onSubmit: async v => { const r = await TT.api('findings/review', { method: 'POST', body: { id: f.id, status, comment: v.comment } }); TT.toast(r.message); } });
    if (done) { await load(); }
  }

  async function apply(field) {
    const d = G.date_suggestions.find(x => x.field === field);
    if (!(await TT.confirm('Set ' + field.replace(/_/g, ' ') + ' on the contract to ' + d.value + '?', { title: 'Apply suggested date?', okText: 'Apply' }))) { return; }
    try { const r = await TT.api('analysis/apply_date', { method: 'POST', body: { contract_id: id, field } }); TT.toast(r.message); document.dispatchEvent(new CustomEvent('tt:contract-changed')); } catch (e) { TT.toast(e.message, 'err'); }
  }

  root.addEventListener('click', ev => {
    const b = ev.target.closest('[data-act]'); if (!b || !G) { return; }
    const act = b.dataset.act;
    if (act === 'run') { run(b.dataset.restart === '1'); }
    else if (act === 'approve') { approve(G.suggestions.find(s => s.id == b.dataset.id)); }
    else if (act === 'reject') { reject(G.suggestions.find(s => s.id == b.dataset.id)); }
    else if (act === 'review') { review(G.findings.find(f => f.id == b.dataset.id), b.dataset.status); }
    else if (act === 'apply') { apply(b.dataset.field); }
  });

  // Tabs: deep link via #analysis / #copilot
  const tabFor = { '#analysis': 'tabAnalysisBtn', '#copilot': 'tabCopilotBtn', '#versions': 'tabVersionsBtn' };
  const openFromHash = () => { const b = document.getElementById(tabFor[location.hash]); if (b && window.bootstrap) { new bootstrap.Tab(b).show(); } };
  document.querySelectorAll('#cTabs button[data-bs-toggle=tab]').forEach(b => b.addEventListener('shown.bs.tab', () => {
    const map = { tabAnalysisBtn: '#analysis', tabCopilotBtn: '#copilot', tabVersionsBtn: '#versions', tabOverviewBtn: ' ' };
    history.replaceState(null, '', location.pathname + location.search + (map[b.id].trim()));
  }));
  load().then(openFromHash);
})();
