(function () {
  'use strict';
  const h = TT.esc;
  const form = document.getElementById('repForm'), typeSel = document.getElementById('r_type'), box = document.getElementById('r_filters');
  const csv = document.getElementById('r_csv'), pdf = document.getElementById('r_pdf');
  const STATUS = { contracts: ['draft', 'under_review', 'active', 'expired', 'terminated'], obligations: ['active', 'open', 'overdue', 'completed', 'cancelled'], payments: ['upcoming', 'planned', 'due', 'overdue', 'paid', 'cancelled'] };
  const F = {
    contracts: [['status', 'Status', 'select', STATUS.contracts], ['risk', 'Risk', 'select', ['low', 'medium', 'high']], ['date_from', 'Expires from', 'date'], ['date_to', 'Expires to', 'date']],
    risks: [['severity', 'Severity', 'select', ['high', 'medium', 'low']], ['review_status', 'Review status', 'select', ['pending', 'reviewed', 'dismissed']]],
    expirations: [['days', 'Look ahead (days)', 'select', ['30', '60', '90', '180', '365']]],
    obligations: [['status', 'Status', 'select', STATUS.obligations], ['priority', 'Priority', 'select', ['high', 'medium', 'low']], ['date_from', 'Due from', 'date'], ['date_to', 'Due to', 'date']],
    payments: [['status', 'Status', 'select', STATUS.payments], ['currency', 'Currency', 'select', ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'AUD', 'CAD', 'JPY']], ['date_from', 'Due from', 'date'], ['date_to', 'Due to', 'date']],
    counterparties: [], activity: [['date_from', 'From', 'date'], ['date_to', 'To', 'date']],
  };
  let pdfOk = true;

  function renderFilters() {
    box.innerHTML = (F[typeSel.value] || []).map(([name, label, kind, opts]) => '<div class="col-6 col-lg-3"><label class="form-label" for="rf_' + name + '">' + h(label) + '</label>' +
      (kind === 'select' ? '<select class="form-select" id="rf_' + name + '" name="' + name + '"><option value="">' + (name === 'days' ? '' : 'Any') + '</option>' + opts.map(o => '<option value="' + h(o) + '">' + h(TT.label(o)) + '</option>').join('') + '</select>'
        : '<input type="date" class="form-control" id="rf_' + name + '" name="' + name + '">') + '</div>').join('');
    links();
  }

  function params() {
    const p = new URLSearchParams(); p.set('type', typeSel.value);
    new FormData(form).forEach((v, k) => { if (k !== 'type' && String(v).trim() !== '') { p.set(k, v); } });
    return p;
  }
  function links() {
    const p = params(); const base = TT.APP_URL + '/backend/api/index.php?r=reports/export&' + p.toString();
    csv.href = base + '&format=csv'; pdf.href = base + '&format=pdf';
    pdf.classList.toggle('disabled', !pdfOk); pdf.title = pdfOk ? '' : 'PDF export needs "composer install"';
  }

  async function preview() {
    const meta = document.getElementById('r_meta'), head = document.getElementById('r_head'), body = document.getElementById('r_body');
    meta.textContent = 'Loading…';
    try {
      const d = await TT.api('reports/data', { query: '&' + params().toString() });
      head.innerHTML = '<tr>' + d.columns.map(c => '<th>' + h(c.label) + '</th>').join('') + '</tr>';
      body.innerHTML = d.rows.length ? d.rows.map(r => '<tr>' + d.columns.map(c => '<td>' + h(r[c.key] == null ? '' : String(r[c.key]).slice(0, 160)) + '</td>').join('') + '</tr>').join('')
        : '<tr><td colspan="' + d.columns.length + '"><div class="empty-state py-3">No records match this report.</div></td></tr>';
      const f = Object.entries(d.filters || {}).map(([k, v]) => k + ': ' + v).join(' · ');
      meta.textContent = d.title + ' — ' + d.total + ' record' + (d.total === 1 ? '' : 's') + (d.total > 50 ? ' (showing the first 50; exports include all)' : '') + (d.truncated ? ' — list capped at 5,000' : '') + (f ? ' · ' + f : '');
    } catch (e) { meta.textContent = e.message || 'Could not load the report.'; head.innerHTML = body.innerHTML = ''; }
  }

  form.addEventListener('submit', ev => { ev.preventDefault(); preview(); });
  form.addEventListener('change', links);
  typeSel.addEventListener('change', () => { renderFilters(); document.getElementById('r_head').innerHTML = document.getElementById('r_body').innerHTML = ''; document.getElementById('r_meta').textContent = 'Click Preview to load this report.'; });
  [csv, pdf].forEach(a => a.addEventListener('click', ev => { if (a.classList.contains('disabled')) { ev.preventDefault(); } }));

  (async function () {
    try {
      const d = await TT.api('reports/types'); pdfOk = d.pdf_available;
      typeSel.innerHTML = d.types.map(t => '<option value="' + h(t.type) + '">' + h(t.label) + '</option>').join('');
      const want = new URLSearchParams(location.search).get('type'); if (want && d.types.some(t => t.type === want)) { typeSel.value = want; }
      renderFilters();
    } catch (e) { document.getElementById('r_meta').textContent = e.message; }
  })();
})();
