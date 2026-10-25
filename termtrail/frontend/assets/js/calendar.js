(function () {
  'use strict';
  const el = document.getElementById('calendar');
  if (!window.FullCalendar) { el.innerHTML = '<div class="empty-state"><p>The calendar library could not be loaded (internet access to the CDN is required).</p></div>'; return; }
  const visible = {};
  document.querySelectorAll('#legend input[data-type]').forEach(cb => { visible[cb.dataset.type] = cb.checked; });

  const cal = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth', height: 'auto', firstDay: 1, dayMaxEvents: 3, navLinks: false,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
    buttonText: { today: 'Today', month: 'Month', list: 'List' },
    events: (info, ok, fail) => {
      TT.api('calendar/events', { query: '&start=' + encodeURIComponent(info.startStr.substring(0, 10)) + '&end=' + encodeURIComponent(info.endStr.substring(0, 10)) })
        .then(list => ok(list.filter(e => visible[e.extendedProps.type] !== false)))
        .catch(e => { TT.toast(e.message || 'Could not load calendar events.', 'err'); fail(e); });
    },
    eventDidMount: info => { info.el.setAttribute('title', info.event.title + (info.event.extendedProps.contract ? ' — ' + info.event.extendedProps.contract : '')); },
  });
  cal.render();
  document.querySelectorAll('#legend input[data-type]').forEach(cb => cb.addEventListener('change', () => { visible[cb.dataset.type] = cb.checked; cal.refetchEvents(); }));
  window.TTCalendar = cal;
})();
