(() => {
  'use strict';
  const root = document.getElementById('traffic-live');
  if (!root) return;
  const chart = root.querySelector('[data-live-chart]'), table = root.querySelector('[data-live-table]');
  const status = root.querySelector('[data-live-status]'), badge = root.querySelector('[data-live-badge]');
  const refresh = root.querySelector('[data-live-refresh]'), note = root.querySelector('[data-live-note]');
  const number = new Intl.NumberFormat('id-ID'), compact = new Intl.NumberFormat('id-ID', {notation: 'compact', maximumFractionDigits: 1});
  let data = null, range = 'daily', timer = null, busy = false, stopped = false;
  const valid = value => value && value.ok === true && typeof value.enabled === 'boolean'
    && typeof value.updated_label === 'string' && value.summary
    && ['views', 'bots', 'non_bot', 'recent_views'].every(k => Number.isSafeInteger(value.summary[k]) && value.summary[k] >= 0)
    && [['daily', 7], ['hourly', 24]].every(([key, count]) => Array.isArray(value[key]) && value[key].length === count
      && value[key].every(p => typeof p.key === 'string' && typeof p.label === 'string'
        && ['views', 'bots', 'non_bot'].every(k => Number.isSafeInteger(p[k]) && p[k] >= 0) && p.views === p.bots + p.non_bot));
  const element = (tag, className, text) => {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = text;
    return el;
  };
  function draw() {
    if (!data) return;
    const series = data[range], max = Math.max(1, ...series.map(p => p.views));
    const scale = max < 10 ? max : Math.ceil(max / 10 ** Math.floor(Math.log10(max))) * 10 ** Math.floor(Math.log10(max));
    const fragment = document.createDocumentFragment(), rows = document.createDocumentFragment();
    series.forEach(point => {
      const column = element('div', 'traffic-live-column'), track = element('div', 'traffic-live-track');
      const total = element('span', 'traffic-live-total', point.future ? '—' : compact.format(point.views));
      const stack = element('div', 'traffic-live-stack');
      stack.style.height = `${point.views / scale * 170}px`;
      for (const [key, cls] of [['bots', 'bot'], ['non_bot', 'non-bot']]) {
        const segment = element('span', cls);
        segment.style.height = `${point.views ? point[key] / point.views * 100 : 0}%`;
        stack.append(segment);
      }
      track.append(total, stack);
      column.append(track, element('span', 'traffic-live-label', point.label));
      column.title = point.future ? `${point.key} WIB: belum berlangsung` : `${point.key}: ${number.format(point.views)} tayangan, ${number.format(point.non_bot)} non-bot, ${number.format(point.bots)} bot/alat`;
      column.setAttribute('aria-hidden', 'true');
      fragment.append(column);
      const row = element('tr'), th = element('th', '', point.key); th.scope = 'row'; row.append(th);
      for (const key of ['non_bot', 'bots', 'views']) row.append(element('td', '', point.future ? 'Belum berlangsung' : number.format(point[key])));
      rows.append(row);
    });
    chart.classList.toggle('is-hourly', range === 'hourly');
    chart.replaceChildren(fragment); table.replaceChildren(rows);
    root.querySelector('[data-live-scale]').textContent = `Skala 0–${number.format(scale)} tayangan · ${range === 'daily' ? '7 hari terakhir, termasuk hari ini yang masih berjalan' : 'Per jam hari ini, pukul 00–23 WIB; geser untuk melihat semua jam'}.`;
    root.querySelectorAll('[data-live-range]').forEach(button => {
      const selected = button.dataset.liveRange === range;
      button.setAttribute('aria-pressed', String(selected));
      button.classList.toggle('btn-primary', selected); button.classList.toggle('btn-outline-primary', !selected);
    });
  }
  function render(value) {
    data = value;
    root.querySelectorAll('[data-live-metric]').forEach(el => { el.textContent = number.format(data.summary[el.dataset.liveMetric]); });
    badge.textContent = data.enabled ? 'Live · setiap 30 detik' : 'Pencatatan nonaktif';
    badge.classList.toggle('is-paused', !data.enabled);
    status.textContent = `Diperbarui ${data.updated_label}.`;
    note.textContent = (data.first_event_label ? `Pencatatan pertama: ${data.first_event_label} WIB. ` : 'Belum ada halaman tercatat. ')
      + (data.last_event_label ? `Halaman terakhir tercatat: ${data.last_event_label} WIB. ` : '')
      + (!data.enabled ? 'Pencatatan nonaktif; angka yang ada adalah riwayat, bukan data baru. ' : '')
      + (data.recording_warning ? 'Ada kegagalan pencatatan terbaru; angka mungkin belum lengkap.' : 'Hari sebelum pencatatan dimulai tidak memiliki data historis.');
    draw();
  }
  function schedule() {
    clearTimeout(timer);
    if (!document.hidden && !stopped) timer = setTimeout(update, 30000);
  }
  async function update() {
    if (busy || stopped || document.hidden) return;
    clearTimeout(timer); busy = true; refresh.disabled = true;
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(root.dataset.endpoint, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, signal: controller.signal});
      if (response.redirected || response.status === 401 || response.status === 403) { stopped = true; throw new Error('session'); }
      if (!response.ok) throw new Error('unavailable');
      const value = await response.json();
      if (!valid(value)) throw new Error('invalid');
      render(value);
    } catch (error) {
      badge.textContent = 'Pembaruan tertunda'; badge.classList.add('is-paused');
      status.textContent = stopped ? 'Sesi atau izin berubah. Muat ulang halaman untuk masuk kembali.'
        : `Belum berhasil memperbarui. ${data ? `Angka terakhir tetap ditampilkan (${data.updated_label}).` : 'Data belum tersedia, bukan berarti kunjungan nol.'} Akan dicoba kembali.`;
    } finally {
      clearTimeout(timeout); busy = false; refresh.disabled = stopped; schedule();
    }
  }
  root.querySelectorAll('[data-live-range]').forEach(button => button.addEventListener('click', () => { range = button.dataset.liveRange; draw(); }));
  refresh.addEventListener('click', update);
  document.addEventListener('visibilitychange', () => {
    clearTimeout(timer);
    if (!document.hidden) update();
  });
  try { const initial = JSON.parse(document.getElementById('traffic-live-data').textContent); if (valid(initial)) render(initial); } catch (_) { /* Keep server-rendered fallback. */ }
  if (data) schedule(); else update();
})();
