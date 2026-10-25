(function () {
  'use strict';
  const h = TT.esc;
  const body = document.getElementById('rows'), sw = document.getElementById('regSwitch'), lab = document.getElementById('regLabel');
  let meId = null, users = [];

  const setLabel = on => { lab.textContent = on ? 'Open — anyone can sign up' : 'Closed — admins create accounts'; };

  async function load() {
    try {
      const d = await TT.api('admin/users'); users = d.items;
      sw.checked = d.registration_open; setLabel(d.registration_open);
      body.innerHTML = d.items.map(u => '<tr data-id="' + u.id + '"><td>' + h(u.name) + '</td><td>' + h(u.email) + '</td>' +
        '<td><select class="form-select form-select-sm w-auto" data-role aria-label="Role for ' + h(u.name) + '">' + ['admin', 'manager', 'viewer'].map(r => '<option' + (r === u.role ? ' selected' : '') + '>' + r + '</option>').join('') + '</select></td>' +
        '<td>' + (u.is_active == 1 ? '<span class="badge-status st-active">Active</span>' : '<span class="badge-status st-cancelled">Deactivated</span>') + '</td><td>' + (u.last_login_at ? h(TT.fmtDateTime(u.last_login_at)) : '<span class="text-muted">never</span>') + '</td>' +
        '<td class="text-center">' + u.contracts + '</td><td class="text-end text-nowrap"><button class="btn btn-outline-secondary btn-sm me-1" data-act="pw">Reset password</button>' +
        '<button class="btn btn-outline-' + (u.is_active == 1 ? 'danger' : 'secondary') + ' btn-sm" data-act="active">' + (u.is_active == 1 ? 'Deactivate' : 'Activate') + '</button></td></tr>').join('');
    } catch (e) { body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + h(e.message) + '</td></tr>'; }
  }

  async function showTemp(title, pw, msg) {
    await TT.formModal({ title, okText: 'Done', html: '<p>' + h(msg) + '</p><label class="form-label" for="m_pw">Temporary password</label><input class="form-control" id="m_pw" readonly value="' + h(pw) + '" onfocus="this.select()"><p class="small text-muted mt-2 mb-0">It will not be shown again. The user should change it in Settings after signing in.</p>', onSubmit: async () => {} });
  }

  body.addEventListener('change', async ev => {
    const sel = ev.target.closest('[data-role]'); if (!sel) { return; }
    const id = +sel.closest('tr').dataset.id;
    try { const r = await TT.api('admin/users_role', { method: 'POST', body: { id, role: sel.value } }); TT.toast(r.message); } catch (e) { TT.toast(e.message, 'err'); }
    load();
  });
  body.addEventListener('click', async ev => {
    const b = ev.target.closest('[data-act]'); if (!b) { return; }
    const tr = b.closest('tr'), id = +tr.dataset.id, u = users.find(x => x.id == id);
    try {
      if (b.dataset.act === 'pw') {
        if (!(await TT.confirm('Reset the password for ' + u.name + '? Their current password will stop working.', { title: 'Reset password?', okText: 'Reset' }))) { return; }
        const r = await TT.api('admin/users_password', { method: 'POST', body: { id } }); await showTemp('Temporary password', r.temporary_password, r.message);
      } else {
        const on = u.is_active != 1;
        if (!on && !(await TT.confirm(u.name + ' will be signed out and unable to sign in until reactivated.', { title: 'Deactivate user?', okText: 'Deactivate', danger: true }))) { return; }
        const r = await TT.api('admin/users_active', { method: 'POST', body: { id, active: on ? 1 : 0 } }); TT.toast(r.message);
      }
    } catch (e) { TT.toast(e.message, 'err'); }
    load();
  });
  sw.addEventListener('change', async () => {
    try { const r = await TT.api('admin/settings', { method: 'POST', body: { registration_open: sw.checked ? 1 : 0 } }); setLabel(r.registration_open); TT.toast(r.registration_open ? 'Sign-ups are open.' : 'Sign-ups are closed.'); }
    catch (e) { TT.toast(e.message, 'err'); sw.checked = !sw.checked; }
  });
  document.getElementById('addUser').addEventListener('click', async () => {
    let temp = null;
    const ok = await TT.formModal({ title: 'Create user', okText: 'Create',
      html: '<div class="mb-3"><label class="form-label" for="m_name">Name</label><input class="form-control" id="m_name" name="name"><div class="invalid-feedback" data-error-for="name"></div></div>' +
        '<div class="mb-3"><label class="form-label" for="m_email">Email</label><input class="form-control" id="m_email" name="email" type="email"><div class="invalid-feedback" data-error-for="email"></div></div>' +
        '<div class="mb-1"><label class="form-label" for="m_role">Role</label><select class="form-select" id="m_role" name="role"><option>viewer</option><option>manager</option><option>admin</option></select></div>',
      onSubmit: async v => { const r = await TT.api('admin/users_create', { method: 'POST', body: v }); temp = r; } });
    if (ok && temp) { await showTemp('User created', temp.temporary_password, temp.message); }
    load();
  });
  load();
})();
