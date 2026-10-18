(() => {
  'use strict';
  const root = document.querySelector('[data-battle-live]');
  if (!root) return;
  const toggle = root.querySelector('[data-live-auto]');
  const status = root.querySelector('[data-live-status]');
  const clock = root.querySelector('[data-live-clock]');
  let seconds = Number(root.dataset.remaining), received = performance.now(), busy = false;
  const paint = () => { clock.textContent = String(Math.max(0, Math.ceil(seconds - (performance.now() - received) / 1000))); };
  const poll = async () => {
    if (busy || document.hidden || !toggle.checked) return;
    busy = true;
    try {
      const response = await fetch(root.dataset.statusUrl, {credentials: 'same-origin', cache: 'no-store', headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: AbortSignal.timeout(6000)});
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) { status.textContent = 'Akses/sesi berubah. Gunakan Muat ulang untuk memeriksa.'; return; }
      const state = await response.json();
      if (state.key !== root.dataset.key) { location.reload(); return; }
      seconds = state.remaining; received = performance.now(); paint(); status.textContent = 'Tersinkron dengan server.';
    } catch (_) { status.textContent = 'Koneksi terputus. Jawaban yang tersimpan tetap aman; menyambungkan kembali.'; }
    finally { busy = false; }
  };
  setInterval(poll, 3000); setInterval(paint, 1000);
  document.addEventListener('visibilitychange', poll); window.addEventListener('online', poll); toggle.addEventListener('change', poll); paint();
})();
