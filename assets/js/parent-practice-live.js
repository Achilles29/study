(() => {
  'use strict';
  const root = document.getElementById('parent-practice-live');
  if (!root) return;
  const summary = document.getElementById('parent-live-summary');
  const presence = document.getElementById('parent-live-presence');
  const result = document.getElementById('parent-live-result');
  const cards = [...root.querySelectorAll('[data-live-question]')];
  let stopped = false, busy = false;
  const utc = value => new Date(`${value.replace(' ', 'T')}Z`);
  async function update() {
    if (stopped || busy || document.hidden) return;
    busy = true;
    try {
      const response = await fetch(root.dataset.statusUrl, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
      if (response.status === 404 || response.status === 403) {
        stopped = true;
        cards.forEach(card => { card.parentElement.hidden = true; });
        summary.textContent = 'Pemantauan berakhir: latihan selesai atau izin pemantauan berubah.';
        presence.textContent = 'Muat ulang riwayat latihan untuk melihat status terbaru.';
        result.hidden = false;
        return;
      }
      if (!response.ok) throw new Error('status');
      const data = await response.json();
      let answered = 0;
      const active = data.last_seen_at && utc(data.server_time) - utc(data.last_seen_at) <= 25000;
      cards.forEach(card => {
        const id = card.dataset.liveQuestion;
        const selected = data.answers[id];
        if (selected !== null && selected !== undefined) answered++;
        card.querySelectorAll('[data-live-option]').forEach(option => {
          const chosen = selected !== null && Number(option.dataset.liveOption) === Number(selected);
          option.classList.toggle('border', chosen);
          option.classList.toggle('border-primary', chosen);
          option.querySelector('[data-live-selected]').hidden = !chosen;
        });
        card.querySelector('[data-live-current]').hidden = !(active && Number(data.current_question_id) === Number(id));
        const timing = data.timings[id];
        const label = card.querySelector('[data-live-timing]');
        if (!timing) label.textContent = selected === null ? 'Belum dijawab atau belum tersimpan.' : 'Pilihan tersimpan; waktu jawaban belum tersedia.';
        else {
          const seconds = timing.first_ms === null ? null : Math.round(timing.first_ms / 1000);
          label.textContent = `Pilihan ${selected === null ? 'dikosongkan' : 'tersimpan'} · waktu pilihan pertama ${seconds === null ? 'tidak tersedia' : `sekitar ${seconds} detik`}${timing.changes > 1 ? ` · pilihan berubah ${timing.changes - 1} kali` : ''}.`;
        }
      });
      summary.textContent = `${answered} dari ${cards.length} soal sudah dijawab dan tersimpan.`;
      presence.textContent = active ? `Anak sedang membuka soal ${cards.findIndex(card => Number(card.dataset.liveQuestion) === Number(data.current_question_id)) + 1}.` : 'Belum ada aktivitas halaman anak dalam 25 detik terakhir.';
    } catch (_) {
      presence.textContent = 'Koneksi pemantauan terputus. Mencoba lagi otomatis…';
    } finally { busy = false; }
  }
  update();
  setInterval(update, 3000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
})();
