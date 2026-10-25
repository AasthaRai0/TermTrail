(function () {
  'use strict';
  const state = { page: 1 };
  let seq = 0;
  const body = document.getElementById('rows');
  const q = document.getElementById('cq'), arch = document.getElementById('carch');
  const modalEl = document.getElementById('cpModal');
  const form = document.getElementById('cpForm');
  const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
  let canWrite = !!form;

  async function load() {
    const mine = ++seq;
    const params = new URLSearchParams({ page: state.page, per_page: 10 });
    if (q.value.trim()) { params.set('q', q.value.trim()); }
    if (arch.checked) { params.set('archived', '1'); }
    try {
      const d = await TT.api('counterparties/list', { query: '&' + params });
      if (mine !== seq) { return; }
      state.page = d.page;
      body.innerHTML = d.items.length ? d.items.map(c =>
        '<tr><td><a href="' + TT.url('counterparty_view', { id: c.id }) + '">' + TT.esc(c.name) + '</a>' + (c.is_archived == 1 ? ' <span class="badge-status st-archived">Archived</span>' : '') + '</td>' +
        '<td>' + TT.esc(c.contact_person || '—') + '</td><td>' + TT.esc(c.email || '—') + '</td><td>' + TT.esc(c.phone || '—') + '</td>' +
        '<td class="text-center"><a href="' + TT.url('contracts', { counterparty_id: c.id }) + '">' + c.contract_count + '</a></td>' +
        '<td class="text-end text-nowrap">' + (canWrite && c.can_edit !== false ? actions(c) : '') + '</td></tr>').join('')
        : '<tr><td colspan="6"><div class="empty-state"><i data-lucide="building-2"></i><p class="mb-0">No counterparties found.</p></div></td></tr>';
      if (window.lucide) { lucide.createIcons(); }
      TT.pager(document.getElementById('pager'), d, p => { state.page = p; load(); });
      body.querySelectorAll('[data-act]').forEach(b => b.addEventListener('click', () => act(b.dataset.act, d.items.find(i => i.id == b.dataset.id))));
    } catch (e) { if (mine === seq) { body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + TT.esc(e.message) + '</td></tr>'; } }
  }

  function actions(c) {
    return '<button class="btn btn-sm btn-outline-secondary me-1" data-act="edit" data-id="' + c.id + '">Edit</button>' +
      '<button class="btn btn-sm btn-outline-secondary me-1" data-act="' + (c.is_archived == 1 ? 'restore' : 'archive') + '" data-id="' + c.id + '">' + (c.is_archived == 1 ? 'Restore' : 'Archive') + '</button>' +
      '<button class="btn btn-sm btn-outline-danger" data-act="delete" data-id="' + c.id + '">Delete</button>';
  }

  async function act(type, c) {
    if (type === 'edit') { return openForm(c); }
    try {
      if (type === 'delete') {
        if (!(await TT.confirm('Delete "' + c.name + '"? This cannot be undone.', { title: 'Delete counterparty?', okText: 'Delete', danger: true }))) { return; }
      }
      const r = await TT.api('counterparties/' + type, { method: 'POST', body: { id: c.id } });
      TT.toast(r.message); load();
    } catch (e) { TT.toast(e.message, 'err'); }
  }

  async function openForm(c) {
    form.reset(); form.elements.id.value = c ? c.id : '';
    form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
    form.querySelectorAll('[data-error-for]').forEach(e => e.textContent = '');
    form.querySelector('[data-form-error]').classList.add('d-none');
    form.dataset.api = c ? 'counterparties/update' : 'counterparties/create';
    document.getElementById('cpModalTitle').textContent = c ? 'Edit counterparty' : 'Add counterparty';
    if (c) {
      try {
        const d = (await TT.api('counterparties/get', { query: '&id=' + c.id })).counterparty;
        ['name', 'contact_person', 'email', 'phone', 'address', 'notes'].forEach(k => { form.elements[k].value = d[k] || ''; });
      } catch (e) { TT.toast(e.message, 'err'); return; }
    }
    modal.show();
    modalEl.addEventListener('shown.bs.modal', () => form.elements.name.focus(), { once: true });
  }

  if (form) {
    document.getElementById('addBtn').addEventListener('click', () => openForm(null));
    form.addEventListener('tt:saved', e => { if (modal) { modal.hide(); } TT.toast(e.detail.message || 'Saved.'); load(); });
  }
  q.addEventListener('input', TT.debounce(() => { state.page = 1; load(); }, 350));
  arch.addEventListener('change', () => { state.page = 1; load(); });
  load();
})();
