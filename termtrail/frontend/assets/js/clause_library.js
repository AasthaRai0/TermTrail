(function () {
  'use strict';
  const h = TT.esc;
  const state = { page: 1 };
  const q = document.getElementById('tq'), cat = document.getElementById('tcat'), arch = document.getElementById('tarch');
  const cards = document.getElementById('cards');
  const modalEl = document.getElementById('tplModal'), form = document.getElementById('tplForm');
  const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
  const catLabel = c => TT.label(String(c).replace(/_/g, ' '));
  let seq = 0, items = [], categories = [];

  function fillCats(sel, blank) { sel.innerHTML = (blank ? '<option value="">' + blank + '</option>' : '') + categories.map(c => '<option value="' + h(c) + '">' + h(catLabel(c)) + '</option>').join(''); }

  async function load() {
    const mine = ++seq;
    const p = new URLSearchParams({ page: state.page, per_page: 12 }); if (q.value.trim()) { p.set('q', q.value.trim()); } if (cat.value) { p.set('category', cat.value); } if (arch.checked) { p.set('archived', '1'); }
    try {
      const d = await TT.api('templates/list', { query: '&' + p });
      if (mine !== seq) { return; }
      if (!categories.length) { categories = d.categories; const cur = cat.value; fillCats(cat, 'All categories'); cat.value = cur; if (form) { fillCats(form.elements.category); } }
      items = d.items; state.page = d.page;
      cards.innerHTML = d.items.length ? d.items.map(t =>
        '<div class="col-md-6 col-xl-4"><div class="glass p-3 h-100 d-flex flex-column"><div class="d-flex justify-content-between align-items-start gap-2"><h2 class="h6 mb-1">' + h(t.name) + '</h2>' + (t.is_archived == 1 ? '<span class="badge-status st-archived">Archived</span>' : '') + '</div>' +
        '<div class="mb-2"><span class="badge-status st-open">' + h(catLabel(t.category)) + '</span>' + (t.tags ? ' <small class="text-muted">' + h(t.tags) + '</small>' : '') + '</div>' +
        '<blockquote class="quote clause-text">' + h(t.clause_text) + '</blockquote>' + (t.plain_explanation ? '<p class="small mb-2"><strong>In plain English:</strong> ' + h(t.plain_explanation) + '</p>' : '') + (t.notes ? '<p class="small text-muted mb-2">' + h(t.notes) + '</p>' : '') +
        '<div class="mt-auto d-flex justify-content-between align-items-center flex-wrap gap-2"><small class="text-muted">By ' + h(t.creator_name) + '</small><div class="d-flex gap-1">' +
        '<button class="btn btn-outline-secondary btn-sm" data-act="copy" data-id="' + t.id + '">Copy</button>' +
        (t.can_edit ? '<button class="btn btn-outline-secondary btn-sm" data-act="edit" data-id="' + t.id + '">Edit</button><button class="btn btn-outline-secondary btn-sm" data-act="' + (t.is_archived == 1 ? 'restore' : 'archive') + '" data-id="' + t.id + '">' + (t.is_archived == 1 ? 'Restore' : 'Archive') + '</button>' : '') + '</div></div></div></div>').join('')
        : '<div class="col-12"><div class="empty-state"><i data-lucide="book-open"></i><p class="mb-0">No templates found.</p></div></div>';
      if (window.lucide) { lucide.createIcons(); }
      TT.pager(document.getElementById('pager'), d, pg => { state.page = pg; load(); });
    } catch (e) { if (mine === seq) { cards.innerHTML = '<div class="col-12 text-danger text-center py-4">' + h(e.message) + '</div>'; } }
  }

  function openForm(t) {
    form.reset(); form.elements.id.value = t ? t.id : '';
    form.querySelectorAll('.is-invalid').forEach(x => x.classList.remove('is-invalid')); form.querySelectorAll('[data-error-for]').forEach(x => x.textContent = ''); form.querySelector('[data-form-error]').classList.add('d-none');
    form.dataset.api = t ? 'templates/update' : 'templates/create'; document.getElementById('tplTitle').textContent = t ? 'Edit template' : 'Add template';
    if (t) { ['name', 'category', 'clause_text', 'plain_explanation', 'tags', 'notes'].forEach(k => { form.elements[k].value = t[k] || ''; }); }
    modal.show(); modalEl.addEventListener('shown.bs.modal', () => form.elements.name.focus(), { once: true });
  }

  cards.addEventListener('click', async ev => {
    const b = ev.target.closest('[data-act]'); if (!b) { return; }
    const t = items.find(i => i.id == b.dataset.id);
    if (b.dataset.act === 'copy') {
      try { await navigator.clipboard.writeText(t.clause_text); TT.toast('Clause text copied.'); } catch (e) { TT.toast('Copy is not available in this browser; select the text manually.', 'err'); }
    } else if (b.dataset.act === 'edit') { openForm(t); }
    else {
      try {
        if (b.dataset.act === 'archive' && !(await TT.confirm('Archive "' + t.name + '"? You can restore it later.', { title: 'Archive template?', okText: 'Archive' }))) { return; }
        const r = await TT.api('templates/' + b.dataset.act, { method: 'POST', body: { id: t.id } }); TT.toast(r.message); load();
      } catch (e) { TT.toast(e.message, 'err'); }
    }
  });
  if (form) {
    document.getElementById('addBtn').addEventListener('click', () => openForm(null));
    form.addEventListener('tt:saved', e => { modal.hide(); TT.toast(e.detail.message || 'Saved.'); load(); });
  }
  q.addEventListener('input', TT.debounce(() => { state.page = 1; load(); }, 350));
  cat.addEventListener('change', () => { state.page = 1; load(); }); arch.addEventListener('change', () => { state.page = 1; load(); });
  load();
})();
