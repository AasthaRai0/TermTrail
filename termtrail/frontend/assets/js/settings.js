(function () {
  'use strict';
  TT.api('ai/status').then(d => {
    document.getElementById('aiState').textContent = d.configured ? 'Configured' : 'Not configured — basic extraction only';
    document.getElementById('aiModel').textContent = d.model || '—';
    document.getElementById('aiEmb').textContent = d.embeddings ? 'Enabled (EMBEDDING_API_KEY set)' : 'Off — keyword/FULLTEXT retrieval is used';
  }).catch(() => {});
  const last = document.getElementById('remLast'), mail = document.getElementById('remMail'), res = document.getElementById('remResult'), btn = document.getElementById('runRem');
  const show = d => {
    last.textContent = d.last_run ? TT.fmtDateTime(d.last_run) + ' (server time)' : 'Never — scheduled reminders are not active yet';
    mail.textContent = d.email_configured ? 'Configured (emails are sent with each reminder)' : 'Not configured (in-app notifications only)';
  };
  TT.api('reminders/status').then(show).catch(e => { last.textContent = mail.textContent = e.message; });
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    try {
      const d = await TT.api('reminders/run', { method: 'POST', body: {} });
      res.textContent = 'Done: ' + d.result.notifications + ' new notification(s), ' + d.result.emails + ' email(s) sent.';
      show({ last_run: d.last_run, email_configured: d.result.email_configured });
    } catch (e) { res.textContent = e.message; } finally { btn.disabled = false; }
  });
})();
