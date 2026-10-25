(function () {
  'use strict';
  const h = TT.esc;
  const form = document.getElementById('filters'), body = document.getElementById('rows');
  const state = { page: 1 }; let seq = 0, init = false;

  function qs() { const p = new URLSearchParams(); ['user_id', 'action', 'date_from', 'date_to'].forEach(k => { const v = form.elements[k].value; if (v) { p.set(k, v); } }); return p; }
  function exportLink() { const p = qs(); p.set('type', 'activity'); p.set('format', 'csv'); if (p.has('user_id')) { /* admin filter supported */ } document.getElementById('auditCsv').href = TT.APP_URL + '/backend/api/index.php?r=reports/export&' + p.toString(); }

  async function load() {
    const mine = ++seq; const p = qs(); p.set('page', state.page); exportLink();
    try {
      const d = await TT.api('admin/audit', { query: '&' + p.toString() });
      if (mine !== seq) { return; }
      if (!init) {
        init = true;
        form.elements.user_id.insertAdjacentHTML('beforeend', d.users.map(u => '<option value="' + u.id + '">' + h(u.name) + '</option>').join(''));
        form.elements.action.insertAdjacentHTML('beforeend', d.actions.map(a => '<option value="' + h(a) + '">' + h(a) + '</option>').join(''));
      }
      state.page = d.page;
      body.innerHTML = d.items.length ? d.items.map(i => '<tr><td class="text-nowrap">' + h(i.created_at) + '</td><td>' + h(i.user_name || 'system') + '</td><td><code>' + h(i.action) + '</code></td>' +
        '<td>' + h(i.entity_type ? i.entity_type + (i.entity_id ? ' #' + i.entity_id : '') : '—') + '</td><td class="small">' + h(i.metadata ? Object.entries(i.metadata).map(([k, v]) => k + '=' + (typeof v === 'object' ? JSON.stringify(v) : v)).join(', ').slice(0, 220) : '') + '</td><td class="small text-muted">' + h(i.ip_address || '') + '</td></tr>').join('')
        : '<tr><td colspan="6"><div class="empty-state py-3">No audit entries match.</div></td></tr>';
      TT.pager(document.getElementById('pager'), d, pg => { state.page = pg; load(); });
    } catch (e) { if (mine === seq) { body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + h(e.message) + '</td></tr>'; } }
  }
  form.addEventListener('submit', e => e.preventDefault());
  form.querySelectorAll('select,input').forEach(el => el.addEventListener('change', () => { state.page = 1; load(); }));
  document.getElementById('resetFilters').addEventListener('click', () => { form.reset(); state.page = 1; load(); });
  load();
})();
