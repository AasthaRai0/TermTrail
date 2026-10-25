(function () {
  'use strict';
  const list = document.getElementById('notifList'), badge = document.getElementById('notifBadge');
  if (!list || !window.TT) { return; }
  const ICONS = { contract_expiry: 'hourglass', renewal_notice: 'refresh-cw', payment_due: 'wallet', payment_overdue: 'alarm-clock', obligation_due: 'list-checks', obligation_overdue: 'alarm-clock', review_request: 'eye', assignment: 'user-check', analysis_completed: 'sparkles', analysis_failed: 'alert-triangle' };

  function render(d) {
    badge.textContent = d.unread > 99 ? '99+' : d.unread;
    badge.classList.toggle('d-none', d.unread === 0);
    list.innerHTML = d.items.length ? d.items.map(n =>
      '<li><button type="button" class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" data-id="' + n.id + '" data-link="' + TT.esc(n.link || '') + '">' +
      '<i data-lucide="' + (ICONS[n.type] || 'bell') + '"></i><span><strong>' + TT.esc(n.title) + '</strong><br><span class="small">' + TT.esc(n.message || '') + '</span><br><small class="text-muted">' + TT.esc(TT.fmtDateTime(n.created_at)) + '</small></span></button></li>').join('')
      : '<li class="px-3 py-3 text-muted small">You are all caught up.</li>';
    if (window.lucide) { lucide.createIcons(); }
  }

  async function load() {
    try { render(await TT.api('notifications/list')); } catch (e) { /* keep silent: bell is non-critical */ }
  }

  list.addEventListener('click', async ev => {
    const b = ev.target.closest('.notif-item'); if (!b) { return; }
    try { await TT.api('notifications/read', { method: 'POST', body: { id: +b.dataset.id } }); } catch (e) { /* still navigate */ }
    if (b.dataset.link) { location.href = TT.APP_URL + '/' + b.dataset.link; } else { load(); }
  });
  document.getElementById('notifReadAll').addEventListener('click', async () => {
    try { await TT.api('notifications/read_all', { method: 'POST', body: {} }); load(); } catch (e) { TT.toast(e.message, 'err'); }
  });
  load();
  setInterval(() => { if (!document.hidden) { load(); } }, 60000);
})();
