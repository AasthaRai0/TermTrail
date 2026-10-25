(function () {
  'use strict';
  const id = +document.getElementById('cpRoot').dataset.id;
  const dl = (k, v) => '<dt>' + TT.esc(k) + '</dt><dd>' + (v ? TT.esc(v) : '—') + '</dd>';
  let page = 1;

  async function contracts() {
    const d = await TT.api('contracts/list', { query: '&counterparty_id=' + id + '&per_page=10&page=' + page });
    page = d.page;
    document.getElementById('cpContracts').innerHTML = d.items.length ? d.items.map(c =>
      '<tr><td><a href="' + TT.url('contract_view', { id: c.id }) + '">' + TT.esc(c.title) + '</a></td><td>' + TT.badge(c.status) + '</td><td>' + TT.riskBadge(c.risk_level) + '</td><td>' + TT.fmtDate(c.expiry_date) + '</td></tr>').join('')
      : '<tr><td colspan="4"><div class="empty-state py-3">No contracts linked to this counterparty.</div></td></tr>';
    TT.pager(document.getElementById('pager'), d, p => { page = p; contracts(); });
  }

  (async function () {
    try {
      const d = await TT.api('counterparties/get', { query: '&id=' + id }); const c = d.counterparty;
      document.getElementById('cpTitle').textContent = c.name;
      document.getElementById('cpBadges').innerHTML = (c.is_archived == 1 ? '<span class="badge-status st-archived">Archived</span> ' : '') + '<span class="text-muted small">' + c.contract_count + ' contract' + (c.contract_count == 1 ? '' : 's') + '</span>';
      document.getElementById('cpDetails').innerHTML = dl('Contact', c.contact_person) + dl('Email', c.email) + dl('Phone', c.phone) + dl('Address', c.address) + dl('Notes', c.notes);
      document.getElementById('loading').classList.add('d-none'); document.getElementById('content').classList.remove('d-none');
      await contracts();
    } catch (e) {
      document.getElementById('loading').innerHTML = '<div class="empty-state"><h2 class="h5">' + TT.esc(e.status === 404 ? 'Counterparty not found' : 'Could not load') + '</h2><a class="btn btn-primary" href="' + TT.url('counterparties') + '">Back</a></div>';
    }
  })();
})();
