(function () {
  'use strict';
  const root = document.getElementById('contractRoot');
  const id = +root.dataset.id;
  let data = null;

  const dl = (k, v) => '<dt>' + TT.esc(k) + '</dt><dd>' + (v == null || v === '' ? '—' : v) + '</dd>';

  function render() {
    const c = data.contract;
    document.title = c.title + ' · TermTrail AI';
    document.getElementById('cTitle').textContent = c.title;
    document.getElementById('cBadges').innerHTML = TT.badge(c.status) + TT.riskBadge(c.risk_level) + (c.is_archived == 1 ? '<span class="badge-status st-archived">Archived</span>' : '');

    const act = document.getElementById('cActions'); act.innerHTML = '';
    const mk = (html, cls, fn, href) => {
      const el = document.createElement(href ? 'a' : 'button'); el.className = 'btn ' + cls; el.innerHTML = html;
      if (href) { el.href = href; } else { el.type = 'button'; el.addEventListener('click', fn); }
      act.append(el);
    };
    if (c.has_file) { mk('Download', 'btn-outline-secondary', null, TT.downloadUrl(c.id)); }
    if (data.can_write) {
      mk('Edit', 'btn-primary', null, TT.url('contract_edit', { id: c.id }));
      mk(c.is_archived == 1 ? 'Restore' : 'Archive', 'btn-outline-secondary', () => archive(c.is_archived != 1));
      mk('Delete', 'btn-outline-danger', del);
    }

    const cp = c.counterparty_id ? '<a href="' + TT.url('counterparty_view', { id: c.counterparty_id }) + '">' + TT.esc(c.counterparty_name) + '</a>' : '';
    document.getElementById('cDetails').innerHTML =
      dl('Type', TT.esc(c.contract_type)) + dl('Counterparty', cp) + dl('Owner', TT.esc(c.owner_name)) + dl('Reviewer', TT.esc(c.reviewer_name)) +
      dl('Effective date', TT.fmtDate(c.effective_date)) + dl('Start date', TT.fmtDate(c.start_date)) + dl('Expiry date', TT.fmtDate(c.expiry_date)) +
      dl('Renewal date', TT.fmtDate(c.renewal_date)) + dl('Notice period', c.notice_period_days != null ? TT.esc(c.notice_period_days) + ' days' : '') +
      dl('Value', TT.esc(TT.money(c.contract_value, c.currency))) + dl('Tags', TT.esc(c.tags)) + dl('Description', TT.esc(c.description)) +
      dl('Created', TT.esc(TT.fmtDateTime(c.created_at)));

    const doc = document.getElementById('docCard');
    if (c.has_file) {
      doc.innerHTML = '<div class="file-card"><i data-lucide="file-text"></i><div><div class="fw-semibold">' + TT.esc(c.original_filename) + '</div>' +
        '<small class="text-muted">' + TT.esc(String(c.mime_type)) + ' · ' + Math.max(1, Math.round(c.file_size / 1024)) + ' KB</small></div></div>' +
        (c.has_text ? '<p class="small text-muted mt-3 mb-2">Text extracted successfully.</p><button class="btn btn-outline-secondary btn-sm" id="showText">View extracted text</button><pre class="text-box d-none mt-3" id="textBox"></pre>'
          : '<div class="alert alert-warning mt-3 mb-0 small">' + TT.esc(c.extraction_error || 'No text could be extracted from this file.') + '</div>');
    } else {
      doc.innerHTML = '<div class="empty-state py-3"><i data-lucide="file-x"></i><p class="mb-0">No document uploaded yet.</p></div>';
    }
    if (data.can_write) {
      doc.insertAdjacentHTML('beforeend', '<hr><label class="form-label" for="replaceFile">' + (c.has_file ? 'Replace file' : 'Upload file') + '</label>' +
        '<input type="file" class="form-control mb-2" id="replaceFile" accept=".pdf,.docx,.txt"><button class="btn btn-primary btn-sm" id="uploadBtn" type="button">Upload</button>' +
        '<div class="small text-danger mt-2" id="uploadErr"></div>');
      document.getElementById('uploadBtn').addEventListener('click', upload);
    }
    const st = document.getElementById('showText'); if (st) { st.addEventListener('click', showText); }

    document.getElementById('noteForm').classList.toggle('d-none', !data.can_note);
    const nl = document.getElementById('notesList');
    nl.innerHTML = data.notes.length ? data.notes.map(n => '<li class="note-item"><div><strong>' + TT.esc(n.author) + '</strong> <small class="text-muted">' + TT.esc(TT.fmtDateTime(n.created_at)) + '</small><div>' + TT.esc(n.note) + '</div></div></li>').join('')
      : '<li class="text-muted">No notes yet.</li>';
    if (window.lucide) { lucide.createIcons(); }
  }

  async function related() {
    const ob = document.getElementById('relObs'), py = document.getElementById('relPays');
    const addOb = document.getElementById('addOb'), addPay = document.getElementById('addPay');
    addOb.classList.toggle('d-none', !data.can_write); addPay.classList.toggle('d-none', !data.can_write);
    addOb.href = TT.url('obligation_new', { contract_id: id }); addPay.href = TT.url('payments', { new: 1, contract_id: id });
    try {
      const [o, p] = await Promise.all([TT.api('obligations/list', { query: '&contract_id=' + id + '&per_page=50' }), TT.api('payments/list', { query: '&contract_id=' + id + '&per_page=50&sort=due_date&dir=asc' })]);
      ob.innerHTML = o.items.length ? o.items.map(x => '<li><span><a href="' + TT.url('obligation_view', { id: x.id }) + '">' + TT.esc(x.title) + '</a><br><small class="text-muted">Due ' + TT.fmtDate(x.due_date) + '</small></span>' + TT.badge(x.status) + '</li>').join('') : '<li class="text-muted">No obligations yet.</li>';
      py.innerHTML = p.items.length ? p.items.map(x => '<li><span><a href="' + TT.url('payments', { id: x.id }) + '">' + TT.esc(TT.money(x.amount, x.currency)) + '</a>' + (x.category ? ' · ' + TT.esc(x.category) : '') + '<br><small class="text-muted">Due ' + TT.fmtDate(x.due_date) + '</small></span>' + TT.badge(x.status) + '</li>').join('') : '<li class="text-muted">No payments yet.</li>';
    } catch (e) { ob.innerHTML = py.innerHTML = '<li class="text-danger">' + TT.esc(e.message) + '</li>'; }
  }

  async function load() {
    try {
      data = await TT.api('contracts/get', { query: '&id=' + id });
      document.getElementById('loading').classList.add('d-none');
      document.getElementById('content').classList.remove('d-none');
      render();
      related();
    } catch (e) {
      document.getElementById('loading').innerHTML = '<div class="empty-state"><i data-lucide="alert-circle"></i><h2 class="h5">' + TT.esc(e.status === 404 ? 'Contract not found' : 'Could not load contract') + '</h2><p>' + TT.esc(e.message || '') + '</p><a class="btn btn-primary" href="' + TT.url('contracts') + '">Back to contracts</a></div>';
      if (window.lucide) { lucide.createIcons(); }
    }
  }

  async function showText() {
    const box = document.getElementById('textBox'); const btn = document.getElementById('showText');
    if (!box.classList.contains('d-none')) { box.classList.add('d-none'); btn.textContent = 'View extracted text'; return; }
    try {
      const d = await TT.api('contracts/text', { query: '&id=' + id });
      box.textContent = d.text + (d.truncated ? '\n\n… (preview truncated)' : '');
      box.classList.remove('d-none'); btn.textContent = 'Hide extracted text';
    } catch (e) { TT.toast(e.message, 'err'); }
  }

  async function upload() {
    const input = document.getElementById('replaceFile'); const err = document.getElementById('uploadErr'); const btn = document.getElementById('uploadBtn');
    err.textContent = '';
    if (!input.files.length) { err.textContent = 'Choose a file first.'; return; }
    if (data.contract.has_file && !(await TT.confirm('This replaces the current file and its extracted text. Continue?', { okText: 'Replace' }))) { return; }
    const fd = new FormData(); fd.append('id', id); fd.append('file', input.files[0]);
    btn.disabled = true; btn.textContent = 'Uploading…';
    try { const r = await TT.api('contracts/file', { method: 'POST', form: fd }); TT.toast(r.message, r.extraction_error ? 'err' : 'ok'); await load(); }
    catch (e) { err.textContent = (e.errors && e.errors.file) || e.message || 'Upload failed.'; btn.disabled = false; btn.textContent = 'Upload'; }
  }

  async function archive(flag) {
    if (flag && !(await TT.confirm('Archived contracts are hidden from the main list. You can restore them later.', { title: 'Archive contract?', okText: 'Archive' }))) { return; }
    try { const r = await TT.api(flag ? 'contracts/archive' : 'contracts/restore', { method: 'POST', body: { id } }); TT.toast(r.message); await load(); }
    catch (e) { TT.toast(e.message, 'err'); }
  }

  async function del() {
    if (!(await TT.confirm('This permanently deletes the contract, its uploaded file, notes and related data. This cannot be undone.', { title: 'Delete contract?', okText: 'Delete', danger: true }))) { return; }
    try { await TT.api('contracts/delete', { method: 'POST', body: { id } }); sessionStorage.setItem('tt-flash', JSON.stringify({ m: 'Contract deleted.', t: 'ok' })); location.href = TT.url('contracts'); }
    catch (e) { TT.toast(e.message, 'err'); }
  }

  document.getElementById('noteForm').addEventListener('tt:saved', load);
  document.addEventListener('tt:contract-changed', load);
  load();
})();
