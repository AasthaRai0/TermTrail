(function () {
  'use strict';
  const meta = n => (document.querySelector('meta[name="' + n + '"]') || {}).content || '';
  const APP_URL = meta('app-url');
  const API = APP_URL + '/backend/api/index.php?r=';

  const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  const label = s => String(s == null ? '' : s).replace(/_/g, ' ').replace(/^./, c => c.toUpperCase());

  window.TT = {
    APP_URL, esc, label,
    url: (page, params = {}) => APP_URL + '/index.php?' + new URLSearchParams(Object.assign({ page }, params)).toString(),
    downloadUrl: id => API + 'contracts/download&id=' + encodeURIComponent(id),

    async api(route, { method = 'GET', body = null, form = null, query = '' } = {}) {
      const opts = { method, headers: { 'Accept': 'application/json', 'X-CSRF-Token': meta('csrf-token') }, credentials: 'same-origin' };
      if (form) { opts.body = form; }
      else if (body !== null) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
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
      setTimeout(() => el.remove(), 6000);
    },

    /** Accessible confirmation dialog. Resolves true/false. */
    confirm(message, { title = 'Please confirm', okText = 'Confirm', danger = false } = {}) {
      return new Promise(resolve => {
        const wrap = document.createElement('div');
        wrap.innerHTML = '<div class="modal fade" tabindex="-1" role="alertdialog" aria-modal="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass">' +
          '<div class="modal-header"><h2 class="modal-title h5"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
          '<div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
          '<button type="button" class="btn ' + (danger ? 'btn-danger' : 'btn-primary') + ' ok"></button></div></div></div></div>';
        const modalEl = wrap.firstChild;
        modalEl.querySelector('.modal-title').textContent = title;
        modalEl.querySelector('.modal-body').textContent = message;
        modalEl.querySelector('.ok').textContent = okText;
        document.body.append(modalEl);
        if (!window.bootstrap) { const r = window.confirm(message); modalEl.remove(); resolve(r); return; }
        const modal = new bootstrap.Modal(modalEl);
        let answer = false, shown = false, pending = false;
        // Bootstrap ignores hide() while the fade-in is running, so defer it until the dialog is fully shown.
        modalEl.addEventListener('shown.bs.modal', () => { shown = true; if (pending) { modal.hide(); } });
        modalEl.querySelector('.ok').addEventListener('click', () => { answer = true; if (shown) { modal.hide(); } else { pending = true; } });
        modalEl.addEventListener('hidden.bs.modal', () => { modalEl.remove(); resolve(answer); });
        modal.show();
      });
    },

    /** Modal with a form. onSubmit(values) must return a promise; throw {errors,message} to show errors and keep the dialog open. Resolves true on success, false if cancelled. */
    formModal({ title, html, okText = 'Save', onSubmit }) {
      return new Promise(resolve => {
        const wrap = document.createElement('div');
        wrap.innerHTML = '<div class="modal fade" tabindex="-1" aria-modal="true" role="dialog"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content glass"><form novalidate>' +
          '<div class="modal-header"><h2 class="modal-title h5"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
          '<div class="modal-body"></div><div class="modal-footer"><div class="alert alert-danger py-1 px-2 me-auto mb-0 d-none" data-form-error></div>' +
          '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><span class="btn-label"></span></button></div></form></div></div></div>';
        const el = wrap.firstChild;
        el.querySelector('.modal-title').textContent = title;
        el.querySelector('.modal-body').innerHTML = html;
        el.querySelector('.btn-label').textContent = okText;
        document.body.append(el);
        const modal = new bootstrap.Modal(el);
        let done = false;
        const form = el.querySelector('form');
        form.addEventListener('submit', async ev => {
          ev.preventDefault();
          const btn = form.querySelector('[type=submit]');
          form.querySelectorAll('.is-invalid').forEach(x => x.classList.remove('is-invalid'));
          form.querySelectorAll('[data-error-for]').forEach(x => x.textContent = '');
          const box = form.querySelector('[data-form-error]'); box.classList.add('d-none');
          const values = {}; new FormData(form).forEach((v, k) => { values[k] = v; });
          btn.disabled = true;
          try { await onSubmit(values); done = true; modal.hide(); }
          catch (e) {
            let shown = false;
            Object.entries(e.errors || {}).forEach(([k, m]) => {
              const inp = form.querySelector('[name="' + k + '"]'), slot = form.querySelector('[data-error-for="' + k + '"]');
              if (inp) { inp.classList.add('is-invalid'); } if (slot) { slot.textContent = m; shown = true; }
            });
            box.textContent = e.message || 'Something went wrong.'; box.classList.remove('d-none');
          } finally { btn.disabled = false; }
        });
        el.addEventListener('shown.bs.modal', () => { const f = form.querySelector('input:not([type=hidden]),textarea,select'); if (f) { f.focus(); } });
        el.addEventListener('hidden.bs.modal', () => { el.remove(); resolve(done); });
        modal.show();
      });
    },

    fmtDate(d) { if (!d) { return '—'; } const x = new Date(String(d).substring(0, 10) + 'T00:00:00'); return isNaN(x) ? '—' : x.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }); },
    fmtDateTime(d) { if (!d) { return ''; } const x = new Date(String(d).replace(' ', 'T') + 'Z'); return isNaN(x) ? String(d) : x.toLocaleString(); },
    money(v, cur) { if (v == null || v === '') { return '—'; } try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur || 'INR' }).format(+v); } catch (e) { return (cur || '') + ' ' + v; } },
    badge(status) { return status ? '<span class="badge-status st-' + esc(status) + '">' + esc(label(status)) + '</span>' : '—'; },
    priBadge(p) { return p ? '<span class="badge-status rk-' + esc(p) + '">' + esc(label(p)) + '</span>' : '—'; },
    riskBadge(r) { return r ? '<span class="badge-status rk-' + esc(r) + '">' + esc(label(r)) + '</span>' : '<span class="text-muted">Not assessed</span>'; },

    pager(el, { page, pages, total }, onPage) {
      if (!el) { return; }
      if (pages <= 1) { el.innerHTML = '<small class="text-muted">' + total + ' result' + (total === 1 ? '' : 's') + '</small>'; return; }
      const btn = (p, txt, dis, act) => '<li class="page-item' + (dis ? ' disabled' : '') + (act ? ' active' : '') + '"><button class="page-link" data-p="' + p + '"' + (dis ? ' disabled' : '') + '>' + txt + '</button></li>';
      let html = '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><small class="text-muted">' + total + ' results · page ' + page + ' of ' + pages + '</small><ul class="pagination pagination-sm mb-0">';
      html += btn(page - 1, 'Prev', page <= 1, false);
      const start = Math.max(1, page - 2), end = Math.min(pages, page + 2);
      for (let p = start; p <= end; p++) { html += btn(p, p, false, p === page); }
      html += btn(page + 1, 'Next', page >= pages, false) + '</ul></div>';
      el.innerHTML = html;
      el.querySelectorAll('button[data-p]').forEach(b => b.addEventListener('click', () => onPage(+b.dataset.p)));
    },

    debounce(fn, ms = 300) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; },
    fillSelect(sel, items, placeholder, valueKey = 'id', textKey = 'name') {
      sel.innerHTML = '<option value="">' + esc(placeholder) + '</option>' + items.map(i => '<option value="' + esc(i[valueKey]) + '">' + esc(i[textKey]) + '</option>').join('');
    },
  };

  function setBusy(btn, busy) {
    if (!btn) { return; }
    const lbl = btn.querySelector('.btn-label');
    if (busy) { btn.disabled = true; btn.dataset.orig = lbl.textContent; lbl.innerHTML = '<span class="spinner-inline"></span>Please wait…'; }
    else { btn.disabled = false; if (btn.dataset.orig) { lbl.textContent = btn.dataset.orig; } }
  }

  // Generic AJAX forms: <form data-api="route" [data-multipart="1"] [data-reset="1"]>
  document.querySelectorAll('form[data-api]').forEach(form => {
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const btn = form.querySelector('[type=submit]');
      const errBox = form.querySelector('[data-form-error]');
      form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
      form.querySelectorAll('[data-error-for]').forEach(e => e.textContent = '');
      if (errBox) { errBox.classList.add('d-none'); }
      setBusy(btn, true);
      try {
        let res;
        if (form.dataset.multipart === '1') { res = await TT.api(form.dataset.api, { method: 'POST', form: new FormData(form) }); }
        else {
          const payload = {};
          new FormData(form).forEach((v, k) => { if (!(v instanceof File)) { payload[k] = v; } });
          res = await TT.api(form.dataset.api, { method: 'POST', body: payload });
        }
        form.dispatchEvent(new CustomEvent('tt:saved', { detail: res }));
        if (res.redirect && !form.dataset.noRedirect) {
          if (res.extraction_error) { sessionStorage.setItem('tt-flash', JSON.stringify({ m: 'Saved, but text extraction failed: ' + res.extraction_error, t: 'err' })); }
          location.href = res.redirect; return;
        }
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
        const firstBad = form.querySelector('.is-invalid'); if (firstBad) { firstBad.focus(); }
      } finally { setBusy(btn, false); }
    });
  });

  // Flash message carried across a redirect
  try { const f = sessionStorage.getItem('tt-flash'); if (f) { sessionStorage.removeItem('tt-flash'); const o = JSON.parse(f); setTimeout(() => TT.toast(o.m, o.t), 200); } } catch (e) { /* ignore */ }

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
