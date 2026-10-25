(function () {
  'use strict';
  const id = +document.getElementById('obRoot').dataset.id;
  const modalEl = document.getElementById('statusModal');
  const modal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;
  let o = null, pending = null;
  const dl = (k, v) => '<dt>' + TT.esc(k) + '</dt><dd>' + (v == null || v === '' ? '—' : v) + '</dd>';

  function render() {
    document.title = o.title + ' · TermTrail AI';
    document.getElementById('oTitle').textContent = o.title;
    document.getElementById('oBadges').innerHTML = TT.badge(o.status) + TT.priBadge(o.priority) + (o.recurrence ? '<span class="badge-status st-archived">Repeats ' + TT.esc(o.recurrence) + '</span>' : '');
    const act = document.getElementById('oActions'); act.innerHTML = '';
    const mk = (txt, cls, fn, href) => { const el = document.createElement(href ? 'a' : 'button'); el.className = 'btn ' + cls; el.textContent = txt; if (href) { el.href = href; } else { el.type = 'button'; el.addEventListener('click', fn); } act.append(el); };
    if (o.can_status && ['open', 'overdue'].includes(o.status)) {
      mk('Mark completed', 'btn-primary', () => ask('completed', 'Mark as completed'));
      mk('Cancel obligation', 'btn-outline-secondary', () => ask('cancelled', 'Cancel obligation'));
    }
    if (o.can_status && ['completed', 'cancelled'].includes(o.status)) { mk('Reopen', 'btn-outline-secondary', reopen); }
    if (o.can_edit) { mk('Edit', 'btn-outline-secondary', null, TT.url('obligation_edit', { id: o.id })); mk('Delete', 'btn-outline-danger', del); }

    let due = TT.fmtDate(o.due_date);
    if (o.due_date && ['open', 'overdue'].includes(o.status)) {
      const days = Math.round((new Date(o.due_date + 'T00:00:00') - new Date(new Date().toDateString())) / 86400000);
      due += days < 0 ? ' <span class="text-danger">(' + (-days) + ' day' + (days === -1 ? '' : 's') + ' overdue)</span>' : days === 0 ? ' <span class="text-warning">(today)</span>' : ' <span class="text-muted">(in ' + days + ' day' + (days === 1 ? '' : 's') + ')</span>';
    }
    document.getElementById('oDetails').innerHTML =
      dl('Contract', '<a href="' + TT.url('contract_view', { id: o.contract_id }) + '">' + TT.esc(o.contract_title) + '</a>') + dl('Description', TT.esc(o.description)) +
      dl('Responsible party', TT.esc(o.responsible_party)) + dl('Internal owner', TT.esc(o.owner_name)) + dl('Due date', due) +
      dl('Recurrence', TT.esc(o.recurrence ? TT.label(o.recurrence) : 'None')) + dl('Completion notes', TT.esc(o.completion_notes)) + dl('Created', TT.esc(TT.fmtDateTime(o.created_at)));
    document.getElementById('oSource').innerHTML =
      (o.source_quote ? '<blockquote class="quote">' + TT.esc(o.source_quote) + '</blockquote>' : '<p class="text-muted small">No source clause recorded.</p>') +
      '<div class="small text-muted">' + (o.approved_by ? 'Approved by ' + TT.esc(o.approver_name) + ' on ' + TT.esc(TT.fmtDateTime(o.approved_at)) : o.suggestion_id ? 'Created from an AI suggestion.' : 'Created manually.') + '</div>';
    if (window.lucide) { lucide.createIcons(); }
  }

  function ask(status, title) {
    pending = status; document.getElementById('statusTitle').textContent = title; document.getElementById('statusNotes').value = '';
    modal.show(); modalEl.addEventListener('shown.bs.modal', () => document.getElementById('statusNotes').focus(), { once: true });
  }
  document.getElementById('statusOk').addEventListener('click', async () => {
    try {
      const r = await TT.api('obligations/status', { method: 'POST', body: { id, status: pending, notes: document.getElementById('statusNotes').value } });
      modal.hide(); TT.toast(r.message); await load();
    } catch (e) { TT.toast(e.message, 'err'); }
  });
  async function reopen() {
    try { const r = await TT.api('obligations/status', { method: 'POST', body: { id, status: 'open' } }); TT.toast(r.message); await load(); } catch (e) { TT.toast(e.message, 'err'); }
  }
  async function del() {
    if (!(await TT.confirm('Delete this obligation permanently?', { title: 'Delete obligation?', okText: 'Delete', danger: true }))) { return; }
    try { await TT.api('obligations/delete', { method: 'POST', body: { id } }); sessionStorage.setItem('tt-flash', JSON.stringify({ m: 'Obligation deleted.', t: 'ok' })); location.href = TT.url('obligations'); } catch (e) { TT.toast(e.message, 'err'); }
  }

  async function load() {
    try {
      o = (await TT.api('obligations/get', { query: '&id=' + id })).obligation;
      document.getElementById('loading').classList.add('d-none'); document.getElementById('content').classList.remove('d-none'); render();
    } catch (e) {
      document.getElementById('loading').innerHTML = '<div class="empty-state"><i data-lucide="alert-circle"></i><h2 class="h5">' + TT.esc(e.status === 404 ? 'Obligation not found' : 'Could not load obligation') + '</h2><a class="btn btn-primary" href="' + TT.url('obligations') + '">Back to obligations</a></div>';
      if (window.lucide) { lucide.createIcons(); }
    }
  }
  load();
})();
