(function () {
  'use strict';
  const COLORS = { status: ['#34d399', '#fbbf24', '#7c5cff', '#f87171', '#9aa3c7', '#3b82f6'], risk: { low: '#34d399', medium: '#fbbf24', high: '#f87171', unassessed: '#6b7396' } };

  function chart(id, type, rows, colors, param) {
    const el = document.getElementById(id);
    if (!el || !window.Chart) { return; }
    if (!rows.length) { el.parentElement.innerHTML = '<div class="empty-state small">No data yet</div>'; return; }
    const text = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#9aa3c7';
    new Chart(el, {
      type,
      data: { labels: rows.map(r => TT.label(r.label)), datasets: [{ data: rows.map(r => +r.value), backgroundColor: colors, borderWidth: 0, borderRadius: type === 'bar' ? 6 : 0 }] },
      options: {
        maintainAspectRatio: false,
        onClick: (e, els) => { if (param && els.length) { location.href = TT.url('contracts', { [param]: rows[els[0].index].label }); } },
        onHover: (e, els) => { if (param) { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; } },
        plugins: { legend: { display: type === 'doughnut', position: 'bottom', labels: { color: text } } },
        scales: type === 'bar' ? { x: { ticks: { color: text }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: 'rgba(148,163,255,.1)' } } } : {},
      },
    });
  }
  const empty = (el, msg) => { el.innerHTML = '<li class="text-muted">' + TT.esc(msg) + '</li>'; };

  async function load() {
    try {
      const d = await TT.api('dashboard/stats');
      Object.entries(d.cards).forEach(([k, v]) => { const n = document.querySelector('[data-stat="' + k + '"]'); if (n) { n.textContent = v; } });
      chart('chartStatus', 'doughnut', d.charts.status, COLORS.status, 'status');
      chart('chartRisk', 'doughnut', d.charts.risk, d.charts.risk.map(r => COLORS.risk[r.label] || '#7c5cff'), 'risk');
      chart('chartMonthly', 'bar', d.charts.monthly, '#7c5cff', null);

      const body = document.getElementById('recentBody');
      body.innerHTML = d.recent_contracts.length ? d.recent_contracts.map(c =>
        '<tr class="clickable" data-id="' + c.id + '"><td><a href="' + TT.url('contract_view', { id: c.id }) + '">' + TT.esc(c.title) + '</a></td><td>' + TT.esc(c.counterparty || '—') + '</td><td>' + TT.badge(c.status) + '</td><td>' + TT.fmtDate(c.expiry_date) + '</td></tr>').join('')
        : '<tr><td colspan="4"><div class="empty-state">No contracts yet.</div></td></tr>';
      body.querySelectorAll('tr.clickable').forEach(tr => tr.addEventListener('click', ev => { if (!ev.target.closest('a')) { location.href = TT.url('contract_view', { id: tr.dataset.id }); } }));

      const dl = document.getElementById('deadlineList');
      if (d.deadlines.length) { dl.innerHTML = d.deadlines.map(x => '<li><span><a href="' + (x.link_page === 'renewals' ? TT.url('renewals') : TT.url(x.link_page, { id: x.link_id })) + '">' + TT.esc(x.title) + '</a><br><small class="text-muted">' + TT.esc(x.kind) + '</small></span><span>' + TT.fmtDate(x.due) + '</span></li>').join(''); }
      else { empty(dl, 'No upcoming deadlines.'); }
      const al = document.getElementById('activityList');
      if (d.activity.length) { al.innerHTML = d.activity.map(x => '<li><span>' + TT.esc(x.action.replace(/[._]/g, ' ')) + '</span><small class="text-muted">' + TT.esc(TT.fmtDateTime(x.created_at)) + '</small></li>').join(''); }
      else { empty(al, 'No activity yet.'); }
    } catch (e) { TT.toast(e.message || 'Could not load dashboard.', 'err'); }
  }
  load();
})();
