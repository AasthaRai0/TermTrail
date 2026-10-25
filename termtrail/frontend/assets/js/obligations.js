(function () {
  'use strict';
  const S = new URLSearchParams(location.search);
  const form = document.getElementById('filters');
  const FIELDS = ['q', 'status', 'priority', 'contract_id', 'owner_id', 'date_from', 'date_to'];
  const state = { page: Math.max(1, +S.get('page') || 1), sort: S.get('sort') || 'due_date', dir: S.get('dir') === 'desc' ? 'desc' : 'asc' };
  let seq = 0;
  const readFilters = () => { const p = {}; FIELDS.forEach(k => { const v = form.elements[k].value.trim(); if (v) { p[k] = v; } }); return p; };

  async function load() {
    const mine = ++seq;
    const qs = new URLSearchParams(Object.assign(readFilters(), { page: state.page, sort: state.sort, dir: state.dir, per_page: 10 })).toString();
    history.replaceState(null, '', location.pathname + '?page=obligations&' + qs);
    document.querySelectorAll('th.sortable').forEach(th => { th.classList.toggle('sorted', th.dataset.sort === state.sort); th.setAttribute('aria-sort', th.dataset.sort === state.sort ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none'); });
    const body = document.getElementById('rows');
    try {
      const d = await TT.api('obligations/list', { query: '&' + qs });
      if (mine !== seq) { return; }
      state.page = d.page;
      body.innerHTML = d.items.length ? d.items.map(o =>
        '<tr class="clickable" tabindex="0" data-id="' + o.id + '"><td><a href="' + TT.url('obligation_view', { id: o.id }) + '">' + TT.esc(o.title) + '</a>' + (o.recurrence ? ' <span class="badge-status st-archived">' + TT.esc(TT.label(o.recurrence)) + '</span>' : '') + '</td>' +
        '<td><a href="' + TT.url('contract_view', { id: o.contract_id }) + '">' + TT.esc(o.contract_title) + '</a></td><td>' + TT.fmtDate(o.due_date) + '</td><td>' + TT.priBadge(o.priority) + '</td><td>' + TT.badge(o.status) + '</td><td>' + TT.esc(o.owner_name || '—') + '</td></tr>').join('')
        : '<tr><td colspan="6"><div class="empty-state"><i data-lucide="list-checks"></i><p class="mb-0">No obligations match your filters.</p></div></td></tr>';
      if (window.lucide) { lucide.createIcons(); }
      TT.pager(document.getElementById('pager'), d, p => { state.page = p; load(); });
    } catch (e) { if (mine === seq) { body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + TT.esc(e.message) + '</td></tr>'; } }
  }

  const go = tr => { location.href = TT.url('obligation_view', { id: tr.dataset.id }); };
  document.getElementById('rows').addEventListener('click', ev => { const tr = ev.target.closest('tr.clickable'); if (tr && !ev.target.closest('a')) { go(tr); } });
  document.getElementById('rows').addEventListener('keydown', ev => { if (ev.key === 'Enter') { const tr = ev.target.closest('tr.clickable'); if (tr) { go(tr); } } });
  document.querySelectorAll('th.sortable').forEach(th => th.addEventListener('click', () => {
    if (state.sort === th.dataset.sort) { state.dir = state.dir === 'asc' ? 'desc' : 'asc'; } else { state.sort = th.dataset.sort; state.dir = 'asc'; }
    state.page = 1; load();
  }));
  const reload = () => { state.page = 1; load(); };
  form.addEventListener('submit', e => e.preventDefault());
  form.elements.q.addEventListener('input', TT.debounce(reload, 350));
  form.querySelectorAll('select, input[type=date]').forEach(el => el.addEventListener('change', reload));
  document.getElementById('resetFilters').addEventListener('click', () => { form.reset(); state.sort = 'due_date'; state.dir = 'asc'; reload(); });

  (async function init() {
    try {
      const d = await TT.api('contracts/list', { query: '&per_page=50&sort=title&dir=asc' });
      d.items.forEach(c => form.elements.contract_id.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + TT.esc(c.title) + '</option>'));
    } catch (e) { /* optional */ }
    FIELDS.forEach(k => { if (S.get(k)) { form.elements[k].value = S.get(k); } });
    load();
  })();
})();
