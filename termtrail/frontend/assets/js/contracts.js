(function () {
  'use strict';
  const S = new URLSearchParams(location.search);
  const form = document.getElementById('filters');
  const FIELDS = ['q', 'status', 'risk', 'type', 'counterparty_id', 'quick', 'date_from', 'date_to'];
  const state = { page: Math.max(1, +S.get('page') || 1), sort: S.get('sort') || 'created_at', dir: S.get('dir') === 'asc' ? 'asc' : 'desc' };
  let seq = 0;

  function readFilters() {
    const p = {};
    FIELDS.forEach(k => { const v = form.elements[k].value.trim(); if (v) { p[k] = v; } });
    if (form.elements.archived.checked) { p.archived = '1'; }
    return p;
  }

  async function load() {
    const mine = ++seq;
    const params = Object.assign(readFilters(), { page: state.page, sort: state.sort, dir: state.dir, per_page: 10 });
    const qs = new URLSearchParams(params).toString();
    history.replaceState(null, '', location.pathname + '?page=contracts&' + qs);
    document.querySelectorAll('th.sortable').forEach(th => {
      th.classList.toggle('sorted', th.dataset.sort === state.sort);
      th.setAttribute('aria-sort', th.dataset.sort === state.sort ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none');
    });
    const body = document.getElementById('rows');
    try {
      const d = await TT.api('contracts/list', { query: '&' + qs });
      if (mine !== seq) { return; }
      state.page = d.page;
      body.innerHTML = d.items.length ? d.items.map(c =>
        '<tr class="clickable" tabindex="0" data-id="' + c.id + '"><td><a href="' + TT.url('contract_view', { id: c.id }) + '">' + TT.esc(c.title) + '</a>' +
        (c.is_archived == 1 ? ' <span class="badge-status st-archived">Archived</span>' : '') + '</td>' +
        '<td>' + TT.esc(c.counterparty_name || '—') + '</td><td>' + TT.esc(c.contract_type || '—') + '</td>' +
        '<td>' + TT.badge(c.status) + '</td><td>' + TT.riskBadge(c.risk_level) + '</td><td>' + TT.fmtDate(c.expiry_date) + '</td>' +
        '<td class="text-end">' + TT.esc(TT.money(c.contract_value, c.currency)) + '</td></tr>').join('')
        : '<tr><td colspan="7"><div class="empty-state"><i data-lucide="file-search"></i><p class="mb-0">No contracts match your filters.</p></div></td></tr>';
      if (window.lucide) { lucide.createIcons(); }
      TT.pager(document.getElementById('pager'), d, p => { state.page = p; load(); });
    } catch (e) {
      if (mine === seq) { body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + TT.esc(e.message || 'Could not load contracts.') + '</td></tr>'; }
    }
  }

  document.getElementById('rows').addEventListener('click', ev => {
    const tr = ev.target.closest('tr.clickable'); if (tr && !ev.target.closest('a')) { location.href = TT.url('contract_view', { id: tr.dataset.id }); }
  });
  document.getElementById('rows').addEventListener('keydown', ev => {
    if (ev.key === 'Enter') { const tr = ev.target.closest('tr.clickable'); if (tr) { location.href = TT.url('contract_view', { id: tr.dataset.id }); } }
  });
  document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', () => {
    if (state.sort === th.dataset.sort) { state.dir = state.dir === 'asc' ? 'desc' : 'asc'; } else { state.sort = th.dataset.sort; state.dir = 'asc'; }
    state.page = 1; load();
  }));
  const reload = () => { state.page = 1; load(); };
  form.addEventListener('submit', e => e.preventDefault());
  form.elements.q.addEventListener('input', TT.debounce(reload, 350));
  form.querySelectorAll('select, input[type=date], input[type=checkbox]').forEach(el => el.addEventListener('change', reload));
  document.getElementById('resetFilters').addEventListener('click', () => { form.reset(); state.sort = 'created_at'; state.dir = 'desc'; reload(); });

  (async function init() {
    FIELDS.forEach(k => { if (S.get(k) && form.elements[k]) { form.elements[k].value = S.get(k); } });
    form.elements.archived.checked = S.get('archived') === '1';
    try {
      const d = await TT.api('counterparties/list', { query: '&per_page=50' });
      const sel = form.elements.counterparty_id;
      d.items.forEach(i => sel.insertAdjacentHTML('beforeend', '<option value="' + i.id + '">' + TT.esc(i.name) + '</option>'));
      if (S.get('counterparty_id')) { sel.value = S.get('counterparty_id'); }
    } catch (e) { /* filter list is optional */ }
    load();
  })();
})();
