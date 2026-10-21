/* Optional early feedback. Server validation remains authoritative. */
(() => {
  'use strict';
  const input = document.querySelector('[data-question-images]');
  if (!input) return;
  const expected = input.form.querySelector('[name="images_expected"]');
  const status = document.getElementById('images-status');
  const review = () => {
    const files = Array.from(input.files || []);
    const total = files.reduce((sum, file) => sum + file.size, 0);
    const names = new Set(files.map(file => file.name));
    let error = '';
    if (files.length > 500) error = 'Pilih maksimal 500 gambar per impor.';
    else if (names.size !== files.length) error = 'Nama gambar harus unik; ubah nama berkas yang sama.';
    else if (files.some(file => file.size > 3 * 1024 * 1024)) error = 'Ada gambar melebihi 3 MiB. Perkecil berkas tersebut.';
    else if (total > 32 * 1024 * 1024) error = 'Total gambar melebihi 32 MiB. Perkecil gambar atau bagi impor.';
    input.setCustomValidity(error);
    input.setAttribute('aria-invalid', error ? 'true' : 'false');
    expected.value = String(files.length);
    status.textContent = `${files.length} gambar dipilih · ${(total / 1024 / 1024).toFixed(2)} MiB.` + (error ? ` ${error}` : ' Hubungkan nama berkas pada GAMBAR: di tiap soal.');
  };
  input.addEventListener('change', review);
  input.form.addEventListener('submit', review);
})();
