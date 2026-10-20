(function () {
  'use strict';
  const meta = n => (document.querySelector('meta[name="' + n + '"]') || {}).content || '';
  const APP_URL = meta('app-url');
  const API = APP_URL + '/backend/api/index.php?r=';

  window.TT = {
    APP_URL,
    async api(route, { method = 'GET', body = null, query = '' } = {}) {
      const opts = { method, headers: { 'Accept': 'application/json', 'X-CSRF-Token': meta('csrf-token') }, credentials: 'same-origin' };
      if (body !== null) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
      let res;
      try { res = await fetch(API + route + query, opts); }
      catch (e) { throw { status: 0, message: 'Network error. Check that Apache is running.' }; }
      let data = {};
      try { data = await res.json(); } catch (e) { data = { success: false, message: 'Unexpected server response.' }; }
      if (res.status === 401 && !location.search.includes('page=login')) { location.href = APP_URL + '/index.php?page=login'; }
      if (!res.ok || data.success === false) { throw Object.assign({ status: res.status }, data); }
      return data;
    },
    toast(message, type = 'ok') {
      const box = document.getElementById('toasts');
      if (!box) { return; }
      const el = document.createElement('div');
      el.className = 'toast align-items-center show ' + type;
      el.setAttribute('role', type === 'err' ? 'alert' : 'status');
      const inner = document.createElement('div'); inner.className = 'd-flex';
      const body = document.createElement('div'); body.className = 'toast-body'; body.textContent = message;
      const close = document.createElement('button'); close.className = 'btn-close me-2 m-auto'; close.setAttribute('aria-label', 'Close');
      close.addEventListener('click', () => el.remove());
      inner.append(body, close); el.append(inner); box.append(el);
      setTimeout(() => el.remove(), 5000);
    },
    esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; },
  };

  function setBusy(btn, busy) {
    if (!btn) { return; }
    const label = btn.querySelector('.btn-label');
    if (busy) { btn.disabled = true; btn.dataset.orig = label.textContent; label.innerHTML = '<span class="spinner-inline"></span>Please wait…'; }
    else { btn.disabled = false; if (btn.dataset.orig) { label.textContent = btn.dataset.orig; } }
  }

  // Generic AJAX forms: <form data-api="route">
  document.querySelectorAll('form[data-api]').forEach(form => {
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = form.querySelector('[type=submit]');
      const errBox = form.querySelector('[data-form-error]');
      form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
      form.querySelectorAll('[data-error-for]').forEach(e => e.textContent = '');
      if (errBox) { errBox.classList.add('d-none'); }
      const payload = Object.fromEntries(new FormData(form).entries());
      setBusy(btn, true);
      try {
        const res = await TT.api(form.dataset.api, { method: 'POST', body: payload });
        if (res.redirect) { location.href = res.redirect; return; }
        TT.toast(res.message || 'Saved.');
        if (res.theme) { document.documentElement.dataset.theme = res.theme; document.documentElement.setAttribute('data-bs-theme', res.theme); }
        if (form.dataset.reset) { form.reset(); }
      } catch (e) {
        const fieldErrors = e.errors || {};
        let shown = false;
        Object.keys(fieldErrors).forEach(k => {
          const input = form.querySelector('[name="' + k + '"]'); const slot = form.querySelector('[data-error-for="' + k + '"]');
          if (input) { input.classList.add('is-invalid'); }
          if (slot) { slot.textContent = fieldErrors[k]; shown = true; }
        });
        if (errBox && (!shown || e.status !== 422)) { errBox.textContent = e.message || 'Something went wrong.'; errBox.classList.remove('d-none'); }
        else if (!shown) { TT.toast(e.message || 'Something went wrong.', 'err'); }
      } finally { setBusy(btn, false); }
    });
  });

  // Sidebar + theme + logout
  const shell = document.getElementById('appShell');
  const toggle = document.getElementById('sidebarToggle');
  if (shell && toggle) {
    toggle.addEventListener('click', () => shell.classList.toggle(window.innerWidth < 992 ? 'nav-open' : 'nav-collapsed'));
    const bd = document.getElementById('sidebarBackdrop'); if (bd) { bd.addEventListener('click', () => shell.classList.remove('nav-open')); }
  }
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', async () => {
      const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next; document.documentElement.setAttribute('data-bs-theme', next);
      try { await TT.api('auth/profile', { method: 'POST', body: { theme: next } }); } catch (e) { TT.toast(e.message, 'err'); }
    });
  }
  const logout = document.getElementById('logoutBtn');
  if (logout) { logout.addEventListener('click', async () => { try { const r = await TT.api('auth/logout', { method: 'POST', body: {} }); location.href = r.redirect; } catch (e) { TT.toast(e.message, 'err'); } }); }
  if (window.lucide) { lucide.createIcons(); }
})();
