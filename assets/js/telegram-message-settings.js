(() => {
 'use strict';
 const form = document.getElementById('telegram-message-form');
 if (!form) return;
 const data = JSON.parse(document.getElementById('message-preview-data').textContent);
 const template = document.getElementById('message-template');
 const mode = document.getElementById('message-mode');
 function refresh() {
  document.querySelectorAll('[data-schedule-field]').forEach(el => { el.hidden = el.dataset.scheduleField !== mode.value; });
  const unknown = [];
  const text = template.value.replace(/\{([^{}]*)\}/g, (original, key) => {
   if (Object.hasOwn(data.sample, key)) return data.sample[key];
   unknown.push(original); return original;
  });
  document.getElementById('message-preview').textContent = text;
  document.getElementById('message-preview-status').textContent = unknown.length ? 'Variabel tidak dikenal: ' + unknown.join(', ') : Array.from(template.value).length + '/600 karakter template. ' + (Array.from(text).length > 1500 ? 'Laporan panjang akan dibagi menjadi beberapa pesan.' : 'Isi berubah mengikuti data dan periode laporan.');
 }
 template.addEventListener('input', refresh);
 mode.addEventListener('change', refresh);
 document.getElementById('message-template-reset').addEventListener('click', () => { template.value = data.defaultTemplate; refresh(); });
 document.querySelectorAll('[data-interval]').forEach(button => button.addEventListener('click', () => { document.getElementById('message-interval').value = button.dataset.interval; }));
 refresh();
})();
