(function () {
  'use strict';
  const root = document.getElementById('versionsRoot');
  if (!root) { return; }
  const id = +document.getElementById('contractRoot').dataset.id;
  const h = TT.esc;
  let L = null, busy = false;
  const AREA = { payment: 'Payment', renewal: 'Renewal', termination: 'Termination', liability: 'Liability', other: 'Other' };
  const IMPACT = { favourable: 'st-completed', unfavourable: 'st-overdue', neutral: 'st-planned', unclear: 'st-due' };
  const size = b => b == null ? '' : b < 1024 ? b + ' B' : b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB';

  function listHtml() {
    const vs = L.versions;
    const opts = sel => vs.map(v => '<option value="' + v.id + '"' + (sel === v.version_number ? ' selected' : '') + '>Version ' + v.version_number + ' — ' + h(v.original_filename) + '</option>').join('');
    const newest = vs.length ? vs[0].version_number : 0;
    return '<div class="glass p-4 mb-3"><h2 class="h6 mb-3">Document versions</h2>' + (vs.length ? '<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Version</th><th>File</th><th>Uploaded</th><th>Notes</th><th>Text</th><th></th></tr></thead><tbody>' +
      vs.map(v => '<tr><td><strong>v' + v.version_number + '</strong>' + (v.version_number === newest ? ' <span class="badge-status st-active">Current</span>' : '') + '</td><td>' + h(v.original_filename) + '<br><small class="text-muted">' + h(size(v.file_size)) + '</small></td>' +
        '<td>' + h(v.uploaded_by_name) + '<br><small class="text-muted">' + h(TT.fmtDateTime(v.created_at)) + '</small></td><td>' + h(v.notes || '—') + '</td>' +
        '<td>' + (v.has_text == 1 ? '<span class="badge-status st-completed">Extracted</span>' : '<span class="badge-status st-overdue" title="No text could be extracted (OCR is not supported)">Not extracted</span>') + '</td>' +
        '<td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="' + TT.APP_URL + '/backend/api/index.php?r=versions/download&id=' + v.id + '">Download</a></td></tr>').join('') + '</tbody></table></div>'
      : '<div class="empty-state py-3"><i data-lucide="files"></i><p class="mb-0">No document has been uploaded for this contract yet.</p></div>') + '</div>' +
      (vs.length >= 2 ? '<div class="glass p-4 mb-3"><h2 class="h6 mb-3">Compare versions</h2><div class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label" for="vFrom">From (older)</label><select class="form-select" id="vFrom">' + opts(newest - 1) + '</select></div>' +
        '<div class="col-md-5"><label class="form-label" for="vTo">To (newer)</label><select class="form-select" id="vTo">' + opts(newest) + '</select></div><div class="col-md-2"><button class="btn btn-primary w-100" data-act="compare" type="button">Compare</button></div></div></div>' : '') +
      (L.can_write ? '<div class="glass p-4 mb-3"><h2 class="h6 mb-2">Upload a new version</h2><p class="small text-muted">The new file becomes the contract\'s current document; older versions are kept. Run the AI analysis again afterwards.</p>' +
        '<div class="row g-2"><div class="col-md-5"><label class="form-label" for="vFile">File (PDF, DOCX or TXT)</label><input type="file" class="form-control" id="vFile" accept=".pdf,.docx,.txt"></div>' +
        '<div class="col-md-5"><label class="form-label" for="vNotes">Version notes</label><input class="form-control" id="vNotes" maxlength="1000" placeholder="e.g. Client redline of 12 Nov"></div>' +
        '<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" data-act="upload" type="button">Upload</button></div></div><div class="small text-danger mt-2" id="vErr" role="alert"></div></div>' : '') +
      '<div id="vResult"></div>' +
      (L.comparisons.length ? '<div class="glass p-4 mt-3"><h2 class="h6 mb-3">Comparison history</h2><ul class="list-clean">' + L.comparisons.map(c => '<li><span>v' + c.from_version + ' → v' + c.to_version + ' <small class="text-muted">· +' + c.added_count + ' / −' + c.removed_count + ' paragraphs · ' + h(c.compared_by_name) + ' · ' + h(TT.fmtDateTime(c.created_at)) + '</small></span><button class="btn btn-outline-secondary btn-sm" data-act="open" data-id="' + c.id + '">View</button></li>').join('') + '</ul></div>' : '');
  }

  function segs(list, hl) { return list.map(s => s.t === 'eq' ? h(s.s) : '<span class="' + hl + '">' + h(s.s) + '</span>').join(''); }

  function diffHtml(r) {
    const d = r.diff, s = r.summary;
    let out = '<div class="glass p-4"><h2 class="h6 mb-2">Version ' + r.from_version + ' → version ' + r.to_version + '</h2>';
    if (d.identical) { return out + '<div class="alert alert-info mb-0">The two versions have identical text. No differences were found.</div></div>'; }
    out += '<div class="d-flex gap-2 flex-wrap mb-3"><span class="badge-status st-completed">+' + d.stats.added_lines + ' paragraphs added (' + d.stats.added_words + ' words)</span><span class="badge-status st-overdue">−' + d.stats.removed_lines + ' paragraphs removed (' + d.stats.removed_words + ' words)</span></div>';
    if (s.ai) {
      out += '<div class="finding mb-3"><div class="d-flex justify-content-between"><strong>Summary of changes</strong><span class="badge-status st-active">AI-generated</span></div><p class="mb-2 mt-2">' + h(s.ai.summary) + '</p>' +
        (s.ai.changes.length ? '<ul class="mb-1">' + s.ai.changes.map(c => '<li><span class="badge-status st-open">' + h(AREA[c.area] || 'Other') + '</span> ' + h(c.change) + ' <span class="badge-status ' + (IMPACT[c.impact] || 'st-due') + '">' + h(TT.label(c.impact)) + '</span></li>').join('') + '</ul>' : '') +
        '<div class="small text-muted">AI summaries can be wrong. Check the highlighted text below.</div></div>';
    } else if (s.ai_error) { out += '<div class="alert alert-warning small">The AI change summary was unavailable (' + h(s.ai_error) + '). The text comparison below is still complete.</div>'; }
    if (s.flags.length) {
      out += '<div class="finding sev-medium mb-3"><strong>Possible changes to sensitive terms</strong> <span class="small text-muted">— found by keyword matching in the changed paragraphs; please review</span><ul class="mb-0 mt-2">' +
        s.flags.map(f => '<li><strong>' + h(f.label) + '</strong> (' + f.count + ')<ul>' + f.examples.map(e => '<li class="small">' + h(TT.label(e.kind)) + ': ' + (e.old ? '<del class="diff-del">' + h(e.old) + '</del> ' : '') + (e.new ? '<ins class="diff-ins">' + h(e.new) + '</ins>' : '') + '</li>').join('') + '</ul></li>').join('') + '</ul></div>';
    }
    out += '<div class="diff-legend small mb-2"><span class="diff-ins">Added</span> <span class="diff-del">Removed</span> Unchanged</div><div class="diff">';
    d.hunks.forEach(hk => {
      if (hk.gap_before > 0) { out += '<div class="diff-gap">… ' + hk.gap_before + ' unchanged paragraph' + (hk.gap_before === 1 ? '' : 's') + ' …</div>'; }
      hk.lines.forEach(l => {
        if (l.t === 'eq') { out += '<div class="dl eq">' + h(l.s) + '</div>'; }
        else if (l.t === 'del') { out += '<div class="dl del"><span class="sign">−</span>' + h(l.s) + '</div>'; }
        else if (l.t === 'ins') { out += '<div class="dl ins"><span class="sign">+</span>' + h(l.s) + '</div>'; }
        else { out += '<div class="dl del"><span class="sign">−</span>' + segs(l.old, 'w-del') + '</div><div class="dl ins"><span class="sign">+</span>' + segs(l.new, 'w-ins') + '</div>'; }
      });
    });
    if (d.gap_after > 0) { out += '<div class="diff-gap">… ' + d.gap_after + ' unchanged paragraph' + (d.gap_after === 1 ? '' : 's') + ' …</div>'; }
    return out + '</div>' + (d.truncated ? '<div class="small text-warning mt-2">Only the first part of the differences is shown.</div>' : '') + '</div>';
  }

  function render() { root.innerHTML = listHtml(); if (window.lucide) { lucide.createIcons(); } }

  async function load() {
    try { L = await TT.api('versions/list', { query: '&contract_id=' + id }); render(); }
    catch (e) { root.innerHTML = '<div class="alert alert-danger">' + h(e.message || 'Could not load versions.') + '</div>'; }
  }

  async function show(promise) {
    const box = document.getElementById('vResult'); box.innerHTML = '<div class="text-muted small py-2">Comparing…</div>';
    try { const r = await promise; box.innerHTML = diffHtml(r); if (r.comparison_id && !r.created_at) { await refreshHistory(); } if (typeof box.scrollIntoView === 'function') { box.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
    catch (e) { box.innerHTML = '<div class="alert alert-danger" role="alert">' + h(e.message || 'Comparison failed.') + '</div>'; }
  }
  async function refreshHistory() { try { const keep = document.getElementById('vResult').innerHTML; L = await TT.api('versions/list', { query: '&contract_id=' + id }); render(); document.getElementById('vResult').innerHTML = keep; } catch (e) { /* ignore */ } }

  async function upload() {
    const f = document.getElementById('vFile'), err = document.getElementById('vErr'); err.textContent = '';
    if (!f.files.length) { err.textContent = 'Choose a file first.'; return; }
    const fd = new FormData(); fd.append('contract_id', id); fd.append('file', f.files[0]); fd.append('notes', document.getElementById('vNotes').value);
    if (busy) { return; } busy = true;
    try { const r = await TT.api('versions/upload', { method: 'POST', form: fd }); TT.toast(r.message, r.extraction_error ? 'err' : 'ok'); await load(); document.dispatchEvent(new CustomEvent('tt:contract-changed')); }
    catch (e) { err.textContent = (e.errors && e.errors.file) || e.message || 'Upload failed.'; } finally { busy = false; }
  }

  root.addEventListener('click', ev => {
    const b = ev.target.closest('[data-act]'); if (!b || !L) { return; }
    if (b.dataset.act === 'upload') { upload(); }
    else if (b.dataset.act === 'compare') { show(TT.api('versions/compare', { method: 'POST', body: { contract_id: id, from_version_id: +document.getElementById('vFrom').value, to_version_id: +document.getElementById('vTo').value } })); }
    else if (b.dataset.act === 'open') { show(TT.api('versions/comparison', { query: '&id=' + b.dataset.id })); }
  });
  document.addEventListener('tt:contract-changed', () => { if (L) { load(); } });
  load();
})();
