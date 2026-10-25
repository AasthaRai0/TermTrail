(function () {
  'use strict';
  const root = document.getElementById('copilotRoot');
  if (!root) { return; }
  const id = +document.getElementById('contractRoot').dataset.id;
  const h = TT.esc;
  const QUESTIONS = ['When does this agreement expire?', 'What is the notice period for termination?', 'What payment obligations does the contract specify?',
    'Does it include automatic renewal?', 'What happens if a party terminates early?', 'Which clauses deserve further review?'];
  let busy = false, aiOn = null;

  root.innerHTML = '<div class="glass p-3 mb-3 small" id="cpNote"></div>' +
    '<div class="glass p-3"><div class="chat-log" id="chatLog" aria-live="polite"></div>' +
    '<div class="d-flex flex-wrap gap-2 my-3" id="chips">' + QUESTIONS.map(q => '<button type="button" class="btn btn-outline-secondary btn-sm chip">' + h(q) + '</button>').join('') + '</div>' +
    '<form id="chatForm" class="d-flex gap-2" novalidate><label class="visually-hidden" for="chatInput">Ask a question about this contract</label>' +
    '<input class="form-control" id="chatInput" maxlength="500" placeholder="Ask about this contract…" autocomplete="off"><button class="btn btn-primary" type="submit" id="chatSend">Ask</button></form>' +
    '<div class="d-flex justify-content-between mt-2"><small class="text-muted">Answers use only passages retrieved from this contract. Verify important points in the document. Not legal advice.</small><button class="btn btn-link btn-sm p-0" id="chatClear" type="button">Clear conversation</button></div></div>';
  const log = document.getElementById('chatLog'), input = document.getElementById('chatInput'), send = document.getElementById('chatSend');

  function sourcesHtml(meta) {
    const src = (meta && meta.sources) || [];
    let html = '';
    if (meta && meta.warning) { html += '<div class="alert alert-warning py-1 px-2 small mt-2 mb-0">' + h(meta.warning) + '</div>'; }
    if (src.length) {
      html += '<div class="sources mt-2"><div class="small text-muted mb-1">Sources</div>' + src.map(s => '<div class="src"><span class="badge-status st-planned">' + h(s.section) + '</span> ' +
        (s.verified_quote ? '<span class="badge-status st-completed">Verified quotation</span>' : '<span class="badge-status st-cancelled">Retrieved excerpt</span>') +
        '<blockquote class="quote mb-0 mt-1">' + h(s.excerpt) + '</blockquote></div>').join('') + '</div>';
    }
    return html;
  }

  function bubble(role, text, meta) {
    const d = document.createElement('div');
    d.className = 'bubble ' + role;
    const t = document.createElement('div'); t.className = 'pre-wrap'; t.textContent = text; d.append(t);
    if (role === 'assistant') {
      if (meta && meta.mode === 'ai_insufficient') { d.insertAdjacentHTML('afterbegin', '<span class="badge-status st-due mb-1 d-inline-block">Not found in the contract</span>'); }
      if (meta && meta.mode === 'excerpts') { d.insertAdjacentHTML('afterbegin', '<span class="badge-status st-planned mb-1 d-inline-block">AI unavailable — showing excerpts</span>'); }
      d.insertAdjacentHTML('beforeend', sourcesHtml(meta));
    }
    log.append(d); log.scrollTop = log.scrollHeight;
    return d;
  }

  async function ask(q) {
    q = q.trim();
    if (busy || q.length < 3) { return; }
    busy = true; send.disabled = true; input.value = '';
    bubble('user', q);
    const typing = document.createElement('div'); typing.className = 'bubble assistant text-muted'; typing.textContent = 'Searching the contract…'; log.append(typing); log.scrollTop = log.scrollHeight;
    try {
      const r = await TT.api('copilot/ask', { method: 'POST', body: { contract_id: id, question: q } });
      typing.remove(); bubble('assistant', r.answer, { mode: r.mode, sources: r.sources, warning: r.warning });
    } catch (e) { typing.remove(); bubble('assistant', e.message || 'Something went wrong.', { mode: 'error' }); }
    finally { busy = false; send.disabled = false; input.focus(); }
  }

  async function init() {
    try {
      const st = await TT.api('ai/status'); aiOn = st.configured;
      document.getElementById('cpNote').innerHTML = aiOn
        ? 'Ask questions in plain English. Copilot retrieves the most relevant passages' + (st.embeddings ? ' (semantic search)' : ' (keyword search)') + ' and answers only from them.'
        : '<strong>AI answers are not configured.</strong> Copilot will show the passages of the contract most relevant to your question instead of an AI-written answer.';
    } catch (e) { /* non-critical */ }
    try {
      const d = await TT.api('copilot/history', { query: '&contract_id=' + id });
      if (!d.items.length) { log.innerHTML = '<div class="empty-state py-3"><i data-lucide="message-square"></i><p class="mb-0">Ask a question or pick one below.</p></div>'; if (window.lucide) { lucide.createIcons(); } }
      d.items.forEach(m => bubble(m.role, m.message, m.meta));
    } catch (e) { log.innerHTML = '<div class="text-danger small">' + h(e.message) + '</div>'; }
  }

  document.getElementById('chatForm').addEventListener('submit', ev => { ev.preventDefault(); const e = log.querySelector('.empty-state'); if (e) { e.remove(); } ask(input.value); });
  document.getElementById('chips').addEventListener('click', ev => { const b = ev.target.closest('.chip'); if (b) { const e = log.querySelector('.empty-state'); if (e) { e.remove(); } ask(b.textContent); } });
  document.getElementById('chatClear').addEventListener('click', async () => {
    if (!(await TT.confirm('Delete your conversation history for this contract?', { title: 'Clear conversation?', okText: 'Clear' }))) { return; }
    try { await TT.api('copilot/clear', { method: 'POST', body: { contract_id: id } }); log.innerHTML = ''; await init(); } catch (e) { TT.toast(e.message, 'err'); }
  });
  init();
})();
