(function () {
  'use strict';
  const form = document.getElementById('obForm');
  const editId = +form.dataset.editId || 0;
  const S = new URLSearchParams(location.search);
  (async function () {
    try {
      const [cs, us] = await Promise.all([TT.api('contracts/options'), TT.api('users/options')]);
      TT.fillSelect(form.elements.contract_id, cs.items, 'Select a contract…', 'id', 'title');
      TT.fillSelect(form.elements.owner_id, us.items.map(u => ({ id: u.id, name: u.name + ' (' + u.role + ')' })), 'Unassigned');
      if (!editId && S.get('contract_id')) { form.elements.contract_id.value = S.get('contract_id'); }
    } catch (e) { TT.toast(e.message || 'Could not load options.', 'err'); }
    if (editId) {
      try {
        const o = (await TT.api('obligations/get', { query: '&id=' + editId })).obligation;
        if (!o.can_edit) { TT.toast('You do not have permission to edit this obligation.', 'err'); form.querySelector('[type=submit]').disabled = true; }
        if (!Array.from(form.elements.contract_id.options).some(x => x.value == o.contract_id)) { form.elements.contract_id.add(new Option(o.contract_title, o.contract_id)); }
        form.elements.contract_id.value = o.contract_id; form.elements.contract_id.disabled = true;
        ['title', 'description', 'responsible_party', 'owner_id', 'due_date', 'priority', 'source_quote'].forEach(k => { if (form.elements[k] && o[k] != null) { form.elements[k].value = o[k]; } });
        form.elements.recurrence.value = o.recurrence || 'none';
      } catch (e) { TT.toast(e.message || 'Could not load obligation.', 'err'); }
    }
  })();
})();
