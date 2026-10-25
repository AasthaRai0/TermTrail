(function () {
  'use strict';
  const form = document.getElementById('contractForm');
  const editId = +form.dataset.editId || 0;
  const S = new URLSearchParams(location.search);

  async function init() {
    try {
      const [cps, users] = await Promise.all([
        TT.api('counterparties/options'),
        TT.api('users/options'),
      ]);
      TT.fillSelect(form.elements.counterparty_id, cps.items, 'None');
      TT.fillSelect(form.elements.reviewer_id, users.items.map(u => ({ id: u.id, name: u.name + ' (' + u.role + ')' })), 'Unassigned');
      if (!editId && S.get('counterparty_id')) { form.elements.counterparty_id.value = S.get('counterparty_id'); }
    } catch (e) { TT.toast(e.message || 'Could not load form options.', 'err'); }

    if (editId) {
      try {
        const d = await TT.api('contracts/get', { query: '&id=' + editId });
        const c = d.contract;
        if (!d.can_write) { TT.toast('You do not have permission to edit this contract.', 'err'); form.querySelector('[type=submit]').disabled = true; }
        ['title', 'contract_type', 'counterparty_id', 'status', 'reviewer_id', 'effective_date', 'start_date', 'expiry_date', 'renewal_date',
          'notice_period_days', 'contract_value', 'currency', 'tags', 'description'].forEach(k => {
          if (form.elements[k] && c[k] != null) { form.elements[k].value = c[k]; }
        });
      } catch (e) { TT.toast(e.message || 'Could not load contract.', 'err'); }
    }
  }
  init();
})();
