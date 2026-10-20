(function () {
  'use strict';
  const COLORS = { status: ['#34d399', '#fbbf24', '#7c5cff', '#f87171', '#9aa3c7', '#3b82f6'], risk: { low: '#34d399', medium: '#fbbf24', high: '#f87171', unassessed: '#6b7396' } };
  const label = s => String(s).replace(/_/g, ' ').replace(/^./, c => c.toUpperCase());

  function fmtDate(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }

  function chart(id, type, rows, colors) {
    const el = document.getElementById(id);
    if (!el || !window.Chart) { return; }
    if (!rows.length) { el.parentElement.innerHTML = '<div class="empty-state small">No data yet</div>'; return; }
    const text = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#9aa3c7';
    new Chart(el, {
      type,
      data: { labels: rows.map(r => label(r.label)), datasets: [{ data: rows.map(r => +r.value), backgroundColor: colors, borderWidth: 0, borderRadius: type === 'bar' ? 6 : 0 }] },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: type === 'doughnut', position: 'bottom', labels: { color: text } } },
        scales: type === 'bar' ? { x: { ticks: { color: text }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: 'rgba(148,163,255,.1)' } } } : {},
      },
    });
  }

  function empty(el, msg) { el.innerHTML = '<li class="text-muted">' + TT.esc(msg) + '</li>'; }

  async function load() {
    try {
      const d = await TT.api('dashboard/stats');
      Object.entries(d.cards).forEach(([k, v]) => { const n = document.querySelector('[data-stat="' + k + '"]'); if (n) { n.textContent = v; } });
      chart('chartStatus', 'doughnut', d.charts.status, COLORS.status);
      chart('chartRisk', 'doughnut', d.charts.risk, d.charts.risk.map(r => COLORS.risk[r.label] || '#7c5cff'));
      chart('chartMonthly', 'bar', d.charts.monthly, '#7c5cff');

      const body = document.getElementById('recentBody');
      body.innerHTML = d.recent_contracts.length ? d.recent_contracts.map(c =>
        '<tr><td>' + TT.esc(c.title) + '</td><td>' + TT.esc(c.counterparty || '—') + '</td><td><span class="badge-status st-' + TT.esc(c.status) + '">' + TT.esc(label(c.status)) + '</span></td><td>' + fmtDate(c.expiry_date) + '</td></tr>').join('')
        : '<tr><td colspan="4"><div class="empty-state">No contracts yet.</div></td></tr>';

      const dl = document.getElementById('deadlineList');
      d.deadlines.length ? dl.innerHTML = d.deadlines.map(x => '<li><span>' + TT.esc(x.title) + '<br><small class="text-muted">' + TT.esc(x.kind) + '</small></span><span>' + fmtDate(x.due) + '</span></li>').join('') : empty(dl, 'No upcoming deadlines.');
      const al = document.getElementById('activityList');
      d.activity.length ? al.innerHTML = d.activity.map(x => '<li><span>' + TT.esc(x.action.replace(/[._]/g, ' ')) + '</span><small class="text-muted">' + TT.esc(x.created_at) + '</small></li>').join('') : empty(al, 'No activity yet.');
    } catch (e) { TT.toast(e.message || 'Could not load dashboard.', 'err'); }
  }
  load();
})();
