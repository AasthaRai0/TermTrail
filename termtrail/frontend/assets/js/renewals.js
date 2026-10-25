(function () {
  'use strict';
  const body = document.getElementById('rows');
  const days = document.getElementById('r_days'), dec = document.getElementById('r_dec');
  const OPTS = [['pending', 'Pending'], ['renew', 'Renew'], ['renegotiate', 'Renegotiate'], ['not_renew', 'Do not renew']];

  function deadline(r) {
    if (!r.notice_deadline) { return '<span class="text-muted">No notice period set</span>'; }
    const d = +r.days_to_notice; let cls = 'text-muted', note = 'in ' + d + ' days';
    if (r.renewal_decision !== 'pending') { return TT.fmtDate(r.notice_deadline); }
    if (d < 0) { cls = 'text-danger'; note = (-d) + ' day' + (d === -1 ? '' : 's') + ' ago'; } else if (d <= 14) { cls = 'text-warning'; note = d === 0 ? 'today' : 'in ' + d + ' day' + (d === 1 ? '' : 's'); }
    return TT.fmtDate(r.notice_deadline) + '<br><small class="' + cls + '">' + note + '</small>';
  }

  async function load() {
    try {
      const d = await TT.api('renewals/list', { query: '&days=' + days.value + (dec.value ? '&decision=' + dec.value : '') });
      body.innerHTML = d.items.length ? d.items.map(r =>
        '<tr><td><a href="' + TT.url('contract_view', { id: r.id }) + '">' + TT.esc(r.title) + '</a></td><td>' + TT.esc(r.counterparty_name || '—') + '</td>' +
        '<td>' + TT.fmtDate(r.key_date) + '<br><small class="text-muted">' + (r.key_type === 'renewal' ? 'Renewal date' : 'Expiry date') + ' · ' + (r.days_to_key < 0 ? (-r.days_to_key) + ' days ago' : r.days_to_key + ' days left') + '</small></td>' +
        '<td>' + deadline(r) + '</td><td>' + (r.can_edit
          ? '<select class="form-select form-select-sm" data-id="' + r.id + '" aria-label="Renewal decision for ' + TT.esc(r.title) + '">' + OPTS.map(o => '<option value="' + o[0] + '"' + (o[0] === r.renewal_decision ? ' selected' : '') + '>' + o[1] + '</option>').join('') + '</select>'
          : TT.esc(TT.label(r.renewal_decision))) + '</td></tr>').join('')
        : '<tr><td colspan="5"><div class="empty-state"><i data-lucide="refresh-cw"></i><p class="mb-0">No renewals or expiries in this window.</p></div></td></tr>';
      if (window.lucide) { lucide.createIcons(); }
      body.querySelectorAll('select[data-id]').forEach(sel => sel.addEventListener('change', async () => {
        try { const r = await TT.api('renewals/decision', { method: 'POST', body: { id: +sel.dataset.id, decision: sel.value } }); TT.toast(r.message); load(); } catch (e) { TT.toast(e.message, 'err'); load(); }
      }));
    } catch (e) { body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + TT.esc(e.message) + '</td></tr>'; }
  }
  days.addEventListener('change', load); dec.addEventListener('change', load);
  load();
})();
