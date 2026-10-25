(function () {
  'use strict';
  const S = new URLSearchParams(location.search);
  const form = document.getElementById('filters');
  const FIELDS = ['q', 'status', 'category', 'contract_id', 'date_from', 'date_to'];
  const state = { page: Math.max(1, +S.get('page') || 1), sort: S.get('sort') || 'due_date', dir: S.get('dir') === 'asc' ? 'asc' : 'desc' };
  const addBtn = document.getElementById('addBtn');
  const payEl = document.getElementById('payModal'), paidEl = document.getElementById('paidModal');
  const payModal = window.bootstrap ? new bootstrap.Modal(payEl) : null, paidModal = window.bootstrap ? new bootstrap.Modal(paidEl) : null;
  const payForm = document.getElementById('payForm'), paidForm = document.getElementById('paidForm');
  let seq = 0, writable = [];

  const readFilters = () => { const p = {}; FIELDS.forEach(k => { const v = form.elements[k].value.trim(); if (v) { p[k] = v; } }); return p; };

  async function summary() {
    try {
      const d = await TT.api('payments/summary');
      document.querySelectorAll('[data-sum]').forEach(el => {
        const k = el.dataset.sum;
        const parts = d.currencies.filter(c => +c[k] > 0).map(c => TT.money(c[k], c.currency));
        el.innerHTML = parts.length ? parts.map(x => '<div>' + TT.esc(x) + '</div>').join('') : '—';
      });
      const sel = form.elements.category, cur = sel.value;
      sel.innerHTML = '<option value="">All</option>' + d.categories.map(c => '<option>' + TT.esc(c) + '</option>').join(''); sel.value = cur || S.get('category') || '';
    } catch (e) { /* summary is optional */ }
  }

  function actions(p) {
    if (!p.can_edit) { return ''; }
    const b = (a, t, cls) => '<button class="btn btn-sm ' + (cls || 'btn-outline-secondary') + ' me-1" data-act="' + a + '" data-id="' + p.id + '">' + t + '</button>';
    let h = '';
    if (['planned', 'due', 'overdue'].includes(p.status)) { h += b('paid', 'Mark paid', 'btn-primary') + b('edit', 'Edit') + b('cancel', 'Cancel'); }
    else { h += b('edit', 'Edit') + b('reopen', 'Reopen'); }
    return h + b('delete', 'Delete', 'btn-outline-danger');
  }

  async function load() {
    const mine = ++seq;
    const qs = new URLSearchParams(Object.assign(readFilters(), { page: state.page, sort: state.sort, dir: state.dir, per_page: 10 })).toString();
    history.replaceState(null, '', location.pathname + '?page=payments&' + qs);
    document.querySelectorAll('th.sortable').forEach(th => { th.classList.toggle('sorted', th.dataset.sort === state.sort); th.setAttribute('aria-sort', th.dataset.sort === state.sort ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none'); });
    const body = document.getElementById('rows');
    try {
      const d = await TT.api('payments/list', { query: '&' + qs });
      if (mine !== seq) { return; }
      state.page = d.page;
      body.innerHTML = d.items.length ? d.items.map(p =>
        '<tr data-id="' + p.id + '"><td><a href="' + TT.url('contract_view', { id: p.contract_id }) + '">' + TT.esc(p.contract_title) + '</a>' + (p.reference_number ? '<br><small class="text-muted">Ref ' + TT.esc(p.reference_number) + '</small>' : '') + '</td>' +
        '<td>' + TT.esc(p.category || '—') + '</td><td class="text-end">' + TT.esc(TT.money(p.amount, p.currency)) + '</td><td>' + TT.fmtDate(p.due_date) + '</td><td>' + TT.badge(p.status) + '</td><td>' + TT.fmtDate(p.paid_date) + '</td>' +
        '<td class="text-end text-nowrap">' + actions(p) + '</td></tr>').join('')
        : '<tr><td colspan="7"><div class="empty-state"><i data-lucide="wallet"></i><p class="mb-0">No payments match your filters.</p></div></td></tr>';
      if (window.lucide) { lucide.createIcons(); }
      TT.pager(document.getElementById('pager'), d, p => { state.page = p; load(); });
      body.querySelectorAll('[data-act]').forEach(b => b.addEventListener('click', () => act(b.dataset.act, +b.dataset.id, d.items.find(i => i.id == b.dataset.id))));
    } catch (e) { if (mine === seq) { body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + TT.esc(e.message) + '</td></tr>'; } }
  }

  const refresh = () => { load(); summary(); };

  async function act(type, id, p) {
    try {
      if (type === 'edit') { return openForm(id); }
      if (type === 'paid') { return openPaid(p); }
      if (type === 'delete') { if (!(await TT.confirm('Delete this payment record permanently?', { title: 'Delete payment?', okText: 'Delete', danger: true }))) { return; } const r = await TT.api('payments/delete', { method: 'POST', body: { id } }); TT.toast(r.message); return refresh(); }
      if (type === 'cancel') { if (!(await TT.confirm('Cancel this payment? It will no longer count towards totals.', { title: 'Cancel payment?', okText: 'Cancel payment' }))) { return; } }
      const r = await TT.api('payments/status', { method: 'POST', body: { id, status: type === 'cancel' ? 'cancelled' : 'planned' } }); TT.toast(r.message); refresh();
    } catch (e) { TT.toast(e.message, 'err'); }
  }

  function resetForm() {
    payForm.reset(); payForm.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
    payForm.querySelectorAll('[data-error-for]').forEach(e => e.textContent = ''); payForm.querySelector('[data-form-error]').classList.add('d-none');
    payForm.elements.id.value = ''; payForm.elements.contract_id.disabled = false;
    Array.from(payForm.elements).forEach(el => { if (el.name) { el.disabled = false; } });
    document.getElementById('paySave').classList.remove('d-none');
  }

  async function openForm(id, preContract) {
    resetForm();
    if (id) {
      let p;
      try { p = (await TT.api('payments/get', { query: '&id=' + id })).payment; } catch (e) { TT.toast(e.message, 'err'); return; }
      payForm.dataset.api = 'payments/update'; document.getElementById('payModalTitle').textContent = p.can_edit ? 'Edit payment' : 'Payment details';
      if (!Array.from(payForm.elements.contract_id.options).some(o => o.value == p.contract_id)) { payForm.elements.contract_id.add(new Option(p.contract_title, p.contract_id)); }
      payForm.elements.id.value = p.id; payForm.elements.contract_id.value = p.contract_id;
      ['amount', 'currency', 'due_date', 'category', 'reference_number', 'notes'].forEach(k => { payForm.elements[k].value = p[k] || ''; });
      payForm.elements.contract_id.disabled = true;
      if (!p.can_edit) { Array.from(payForm.elements).forEach(el => { if (el.name) { el.disabled = true; } }); document.getElementById('paySave').classList.add('d-none'); }
    } else {
      payForm.dataset.api = 'payments/create'; document.getElementById('payModalTitle').textContent = 'Add payment';
      if (preContract) { payForm.elements.contract_id.value = preContract; }
    }
    payModal.show();
  }

  function openPaid(p) {
    paidForm.reset(); paidForm.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid')); paidForm.querySelectorAll('[data-error-for]').forEach(e => e.textContent = '');
    paidForm.elements.id.value = p.id; paidForm.elements.paid_date.value = new Date().toISOString().slice(0, 10); paidForm.elements.reference_number.value = p.reference_number || '';
    paidModal.show();
  }

  form.addEventListener('submit', e => e.preventDefault());
  form.elements.q.addEventListener('input', TT.debounce(() => { state.page = 1; load(); }, 350));
  form.querySelectorAll('select, input[type=date]').forEach(el => el.addEventListener('change', () => { state.page = 1; load(); }));
  document.getElementById('resetFilters').addEventListener('click', () => { form.reset(); state.sort = 'due_date'; state.dir = 'desc'; state.page = 1; load(); });
  document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', () => {
    if (state.sort === th.dataset.sort) { state.dir = state.dir === 'asc' ? 'desc' : 'asc'; } else { state.sort = th.dataset.sort; state.dir = 'asc'; }
    state.page = 1; load();
  }));
  if (addBtn) { addBtn.addEventListener('click', () => openForm(null)); }
  payForm.addEventListener('tt:saved', e => { payModal.hide(); TT.toast(e.detail.message || 'Saved.'); refresh(); });
  paidForm.addEventListener('tt:saved', e => { paidModal.hide(); TT.toast(e.detail.message || 'Saved.'); refresh(); });

  (async function init() {
    try {
      const d = await TT.api('contracts/list', { query: '&per_page=50&sort=title&dir=asc' });
      d.items.forEach(c => form.elements.contract_id.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + TT.esc(c.title) + '</option>'));
    } catch (e) { /* optional */ }
    try {
      if (addBtn) { writable = (await TT.api('contracts/options')).items; TT.fillSelect(payForm.elements.contract_id, writable, 'Select…', 'id', 'title'); }
    } catch (e) { /* optional */ }
    FIELDS.forEach(k => { if (S.get(k) && form.elements[k]) { form.elements[k].value = S.get(k); } });
    summary(); load();
    if (S.get('id')) { openForm(+S.get('id')); }
    else if (S.get('new') && addBtn) { openForm(null, S.get('contract_id')); }
  })();
})();
