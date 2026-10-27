(() => {
  'use strict';
  const sheet = document.getElementById('practice-sheet');
  if (!sheet) return;
  const cards = [...sheet.querySelectorAll('.practice-question')];
  const links = [...sheet.querySelectorAll('[data-nav-index]')];
  const form = document.getElementById('practice-answer-form');
  const endpoint = form?.getAttribute('action');
  const single = !!form && sheet.dataset.mode === 'single';
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const mobile = matchMedia('(max-width: 767px)');
  const navigation = sheet.querySelector('.sheet-navigation');
  const numberStrip = sheet.querySelector('.sheet-numbers');
  const header = document.querySelector('.site-header-member');
  // Measure the real navbar (including its mobile menu), not a fixed guess.
  function measureBars() {
    sheet.style.setProperty('--sheet-header-height', `${header ? Math.ceil(header.getBoundingClientRect().height) : 0}px`);
    sheet.style.setProperty('--sheet-nav-height', `${Math.ceil(navigation.getBoundingClientRect().height)}px`);
  }
  measureBars();
  if ('ResizeObserver' in window) {
    const bars = new ResizeObserver(measureBars);
    if (header) bars.observe(header);
    bars.observe(navigation);
  }
  window.addEventListener('resize', measureBars, {passive: true});
  navigation.querySelector('details').addEventListener('toggle', measureBars);
  let current = 0, navigatingUntil = 0;
  let notePresence = () => {};
  function revealNumber() {
    if (!mobile.matches || !navigation.querySelector('details').open) return;
    const item = links[current].getBoundingClientRect(), strip = numberStrip.getBoundingClientRect();
    if (item.left < strip.left + 4 || item.right > strip.right - 4) {
      // Only scroll the number strip; never pull the whole page to its sidebar.
      numberStrip.scrollLeft += item.left - strip.left - (strip.width - item.width) / 2;
    }
  }
  function selected(card) {
    const radio = card.querySelector('input[type=radio]:checked');
    return radio ? radio.value !== '' : links[cards.indexOf(card)].classList.contains('is-answered');
  }
  function refresh() {
    let answered = 0;
    cards.forEach((card, i) => {
      const yes = selected(card);
      if (yes) answered++;
      if (form) {
        card.classList.toggle('is-answered', yes);
        links[i].classList.toggle('is-answered', yes);
        links[i].setAttribute('aria-label', `Soal ${i + 1}, ${yes ? 'sudah' : 'belum'} dijawab`);
        links[i].querySelector('small').textContent = yes ? '✓' : '○';
      }
      if (i === current) links[i].setAttribute('aria-current', 'true');
      else links[i].removeAttribute('aria-current');
      if (single) card.hidden = i !== current;
    });
    sheet.querySelectorAll('[data-answered-count]').forEach(el => { el.textContent = answered; });
    revealNumber();
    if (form) {
      sheet.querySelector('[data-current-label]').textContent = `${current + 1} / ${cards.length}`;
      sheet.querySelector('[data-sheet-prev]').disabled = current === 0;
      sheet.querySelector('[data-sheet-next]').disabled = current === cards.length - 1;
    }
    return answered;
  }
  function go(index, scroll = true) {
    current = Math.max(0, Math.min(cards.length - 1, index));
    refresh();
    notePresence();
    if (scroll) {
      // A short last question may not reach the top at the document's end.
      // Keep the explicitly chosen number during the resulting smooth scroll.
      navigatingUntil = Date.now() + 1000;
      cards[current].focus({preventScroll: true});
      cards[current].scrollIntoView({behavior: reduced ? 'instant' : 'smooth', block: 'start'});
    }
  }
  links.forEach((el, i) => el.addEventListener('click', event => { event.preventDefault(); go(i); }));
  refresh();
  // Ledger navigation follows ordinary scrolling as well as answer auto-scroll.
  let scrollFrame = 0;
  window.addEventListener('wheel', () => { navigatingUntil = 0; }, {passive: true});
  window.addEventListener('touchmove', () => { navigatingUntil = 0; }, {passive: true});
  window.addEventListener('scroll', () => {
    if (single || scrollFrame || Date.now() < navigatingUntil) return;
    scrollFrame = requestAnimationFrame(() => {
      scrollFrame = 0;
      if (Date.now() < navigatingUntil) return;
      const edge = (header?.getBoundingClientRect().bottom || 0)
        + (mobile.matches ? navigation.getBoundingClientRect().height : 0) + 32;
      let visible = 0;
      cards.forEach((card, i) => { if (card.getBoundingClientRect().top <= edge) visible = i; });
      if (visible !== current) { current = visible; refresh(); notePresence(); }
    });
  }, {passive: true});
  if (!form) return;
  const paging = sheet.querySelector('.sheet-paging');
  paging.hidden = !single;
  sheet.querySelector('[data-sheet-prev]').addEventListener('click', () => go(current - 1));
  sheet.querySelector('[data-sheet-next]').addEventListener('click', () => go(current + 1));
  sheet.querySelector('[data-current-label]').addEventListener('click', () => {
    navigation.querySelector('details').open = true;
    measureBars();
    revealNumber();
    links[current].focus({preventScroll: true});
    navigation.scrollIntoView({behavior: reduced ? 'instant' : 'smooth', block: 'start'});
  });
  const firstEmpty = cards.findIndex(card => !selected(card));
  go(firstEmpty < 0 ? 0 : firstEmpty, false);
  const status = document.getElementById('sheet-save-status');
  document.getElementById('sheet-save-help').textContent =
    'Jawaban disimpan otomatis setelah dipilih. Pastikan status “Tersimpan” sebelum meninggalkan halaman.';
  const guard = sheet.dataset.guard === '1';
  const limit = Number(sheet.dataset.limit);
  let focusCount = Number(sheet.dataset.focusCount), pending = {}, episode = false, leaving = false;
  let version = 0, savedVersion = 0, busy = false, finished = false, fatal = false;
  let parentLive = sheet.dataset.parentLive === '1';
  let presenceVersion = parentLive ? 1 : 0, savedPresenceVersion = 0;
  notePresence = () => { if (parentLive && !finished && !fatal) { presenceVersion++; queueMicrotask(pump); } };
  let finishWanted = false, retryTimer, changeTimer, scrollTimer;
  let csrfRetried = false;
  const eventKey = `pp-focus-080:${sheet.dataset.attempt}`;
  try {
    const cached = JSON.parse(sessionStorage.getItem(eventKey) || '{}');
    if (guard && cached && typeof cached === 'object') {
      Object.entries(cached).slice(0, 20).forEach(([key, reason]) => {
        if (/^[a-f0-9]{32}$/.test(key) && ['hidden', 'blur', 'sleep'].includes(reason)) pending[key] = reason;
      });
    }
  } catch (_) { /* Memory-only tracking remains available. */ }
  function remember() {
    try { sessionStorage.setItem(eventKey, JSON.stringify(pending)); } catch (_) {}
  }
  function syncCsrf(data) {
    if (!data.csrf_name || !data.csrf_token) return;
    document.querySelectorAll('input[type=hidden]').forEach(el => {
      if (el.name === data.csrf_name) el.value = data.csrf_token;
    });
  }
  function lockAnswers(lock) {
    cards.forEach(card => card.querySelectorAll('input').forEach(el => { el.disabled = lock; }));
  }
  function updateFocus() {
    const total = focusCount + Object.keys(pending).length;
    sheet.querySelectorAll('[data-focus-count]').forEach(el => { el.textContent = total; });
    if (guard && total >= limit) {
      // Freeze immediately while the server makes the authoritative decision.
      lockAnswers(true);
      status.textContent = 'Batas perpindahan tercapai. Menyelesaikan dan menyimpan hasil…';
    } else if (!finishWanted && !fatal && !finished) {
      // A retried event can already exist server-side after a lost response.
      lockAnswers(false);
    }
  }
  function payload(events, finish) {
    // Include answers even when focus-limit UI has disabled further edits.
    const data = new URLSearchParams();
    form.querySelectorAll('input[type=hidden]').forEach(el => { if (el.name) data.set(el.name, el.value); });
    const answers = {};
    cards.forEach(card => {
      const answer = card.querySelector('input[type=radio]:checked');
      if (answer) answers[answer.name.match(/^answers\[([0-9]+)\]$/)[1]] = answer.value;
    });
    // A single field avoids PHP max_input_vars truncation on large exams.
    data.set('answers_json', JSON.stringify(answers));
    data.set('action', finish ? 'finish' : 'save');
    data.set('sheet_ajax', '1');
    Object.entries(events).forEach(([token, reason]) => data.set(`focus_events[${token}]`, reason));
    return data;
  }
  function stopConflict() {
    fatal = true;
    lockAnswers(true);
    status.textContent = 'Sesi berubah di tab lain. Pilihan tab ini tidak menimpa simpanan terbaru. Muat simpanan terbaru untuk melanjutkan.';
    document.getElementById('sheet-reload').hidden = false;
  }
  async function pump() {
    if (busy || finished || fatal) return;
    clearTimeout(retryTimer);
    retryTimer = null;
    const needsAnswers = version !== savedVersion || Object.keys(pending).length || finishWanted;
    const needsPresence = parentLive && presenceVersion !== savedPresenceVersion;
    if (!needsAnswers && !needsPresence) return;
    busy = true;
    const sentVersion = version, sentEvents = {...pending}, sentFinish = finishWanted;
    const presenceOnly = needsPresence && (!needsAnswers || savedPresenceVersion === 0);
    const sentPresenceVersion = presenceVersion;
    if (!presenceOnly) status.textContent = sentFinish ? 'Menyelesaikan latihan…' : 'Menyimpan jawaban…';
    try {
      const dataBody = presenceOnly ? new URLSearchParams() : payload(sentEvents, sentFinish);
      if (presenceOnly) {
        form.querySelectorAll('input[type=hidden]').forEach(el => { if (el.name) dataBody.set(el.name, el.value); });
        dataBody.set('sheet_presence', '1');
        dataBody.set('current_question_id', cards[current].dataset.questionId);
      }
      const body = dataBody.toString();
      const response = await fetch(endpoint, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: body.length < 60000,
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
        body
      });
      if (presenceOnly && response.status === 404) { parentLive = false; savedPresenceVersion = sentPresenceVersion; return; }
      if (response.status === 403 && !csrfRetried) {
        // CI rotates CSRF; a different tab may have rotated the shared cookie.
        csrfRetried = true;
        const stateResponse = await fetch(`${endpoint}?sheet_state=1`, {cache: 'no-store', credentials: 'same-origin'});
        const state = await stateResponse.json();
        if (!stateResponse.ok || !state.ok) throw new Error('access');
        syncCsrf(state);
        throw new Error('retry');
      }
      const data = await response.json();
      syncCsrf(data);
      if (response.status === 409 || data.conflict) {
        if (data.status !== 'graded') { stopConflict(); return; }
      }
      if (!response.ok || !data.ok) {
        if ([400, 403, 404].includes(response.status)) {
          fatal = true;
          status.textContent = 'Jawaban belum tersimpan. Akses atau formulir berubah; buka kembali sesi melalui riwayat.';
          document.getElementById('sheet-reload').hidden = false;
          lockAnswers(true);
          return;
        }
        throw new Error('save');
      }
      csrfRetried = false;
      if (presenceOnly) { savedPresenceVersion = sentPresenceVersion; return; }
      form.querySelector('[name=revision]').value = data.revision;
      focusCount = data.focus_count;
      Object.keys(sentEvents).forEach(key => { delete pending[key]; });
      remember();
      savedVersion = sentVersion;
      updateFocus();
      if (data.status === 'graded') {
        finished = true;
        lockAnswers(true);
        status.textContent = 'Latihan selesai. Membuka hasil…';
        location.replace(endpoint);
        return;
      }
      status.textContent = version === savedVersion ? 'Tersimpan. Kamu bisa melanjutkan.' : 'Pilihan baru menunggu disimpan…';
    } catch (error) {
      if (error.message === 'retry') { retryTimer = setTimeout(pump, 0); return; }
      if (presenceOnly) { parentLive = false; savedPresenceVersion = sentPresenceVersion; return; }
      status.textContent = 'Belum tersimpan / koneksi terganggu. Jangan tutup halaman. Akan dicoba lagi otomatis.';
      retryTimer = setTimeout(pump, 2500);
    } finally {
      busy = false;
      if (!retryTimer && !fatal && !finished && (version !== savedVersion || Object.keys(pending).length || finishWanted || (parentLive && presenceVersion !== savedPresenceVersion))) queueMicrotask(pump);
    }
  }
  function scheduleSave() {
    clearTimeout(changeTimer);
    changeTimer = setTimeout(pump, 250);
  }
  form.addEventListener('change', event => {
    if (!event.target.matches('input[type=radio]') || finished || fatal) return;
    version++;
    status.textContent = 'Pilihan berubah, menunggu disimpan…';
    const index = cards.indexOf(event.target.closest('.practice-question'));
    current = index;
    refresh();
    scheduleSave();
    clearTimeout(scrollTimer);
    if (!single && event.target.value && index < cards.length - 1) {
      scrollTimer = setTimeout(() => go(index + 1), reduced ? 0 : 450);
    }
  });
  // Let deliberate navigation override a pending automatic ledger scroll.
  sheet.addEventListener('pointerdown', () => clearTimeout(scrollTimer));
  sheet.addEventListener('keydown', () => clearTimeout(scrollTimer));
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (fatal || finished) return;
    if (event.submitter?.value === 'finish') {
      const answered = refresh();
      // An inline confirmation avoids browser dialogs causing focus events.
      let confirmation = document.getElementById('sheet-confirm');
      if (!confirmation) {
        confirmation = document.createElement('div');
        confirmation.id = 'sheet-confirm';
        confirmation.className = 'alert alert-warning mt-3 mb-0';
        confirmation.setAttribute('role', 'group');
        const text = document.createElement('p');
        const yes = document.createElement('button'), no = document.createElement('button');
        yes.type = no.type = 'button';
        yes.className = 'btn btn-danger me-2'; yes.textContent = 'Ya, kumpulkan';
        no.className = 'btn btn-outline-primary'; no.textContent = 'Periksa lagi';
        yes.addEventListener('click', () => { finishWanted = true; lockAnswers(true); confirmation.hidden = true; pump(); });
        no.addEventListener('click', () => { confirmation.hidden = true; });
        confirmation.append(text, yes, no);
        sheet.querySelector('.sheet-controls').append(confirmation);
      }
      confirmation.querySelector('p').textContent = `${answered} dari ${cards.length} soal dijawab. Kumpulkan sekarang?`;
      confirmation.hidden = false;
      confirmation.querySelector('button').focus();
    } else {
      // Manual retry also persists an unchanged, not-yet-saved empty sheet.
      version++;
      pump();
    }
  });
  function focusEvent(reason) {
    if (!guard || episode || leaving || finished || fatal || finishWanted || focusCount + Object.keys(pending).length >= limit) return;
    episode = true;
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    const token = [...bytes].map(n => n.toString(16).padStart(2, '0')).join('');
    pending[token] = reason;
    remember();
    updateFocus();
    pump();
  }
  let lastTick = Date.now();
  function returned() {
    if (!document.hidden && document.hasFocus()) {
      if (Date.now() - lastTick > 30000) focusEvent('sleep');
      lastTick = Date.now(); episode = false; pump();
    }
  }
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) focusEvent('hidden'); else returned();
  });
  window.addEventListener('blur', () => focusEvent('blur'));
  window.addEventListener('focus', returned);
  window.addEventListener('online', pump);
  window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
  window.addEventListener('beforeunload', event => {
    // Reload/navigation is not a new focus-loss episode. Do not count the
    // browser's own unsaved-changes prompt as cheating either.
    leaving = true;
    setTimeout(() => { leaving = false; }, 1000);
    if (!finished && (version !== savedVersion || Object.keys(pending).length)) {
      event.preventDefault(); event.returnValue = true;
    }
  });
  const deadline = Number(sheet.dataset.deadline);
  const serverOffset = Number(sheet.dataset.serverTime) * 1000 - Date.now();
  function tick() {
    const now = Date.now();
    if (now - lastTick > 30000) { focusEvent('sleep'); if (!document.hidden && document.hasFocus()) episode = false; }
    lastTick = now;
    if (deadline && !finished) {
      const seconds = Math.max(0, Math.ceil((deadline * 1000 - now - serverOffset) / 1000));
      document.getElementById('sheet-timer').textContent = `Sisa ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
      if (seconds === 0 && !finishWanted) { finishWanted = true; lockAnswers(true); pump(); }
    }
  }
  updateFocus();
  tick();
  setInterval(tick, 1000);
  if (parentLive) setInterval(() => { if (!document.hidden && !finished && !fatal) notePresence(); }, 10000);
  pump();
})();
