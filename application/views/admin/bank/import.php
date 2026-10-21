<section class="container page-section">
 <a class="back-link mb-3" href="<?php echo site_url('kelola/bank-soal'); ?>">Kembali ke bank soal</a>
 <div class="learning-hero mb-4"><p class="eyebrow">Unggah · Periksa · Konfirmasi</p><h1 class="h2">Impor bank soal</h1><p class="mb-0">Pilih berkas, periksa hasilnya, lalu konfirmasi untuk menyimpan.</p></div>
 <?php if($exam!==''): ?><p class="alert alert-info">Impor untuk <strong><?php echo html_escape($exam_context['title']); ?></strong> · <?php echo html_escape(study_grade_label($exam_context['grade_id'])); ?>. Setelah dikonfirmasi, soal otomatis tertaut ke simulasi dan tetap masuk bank yang sama untuk diacak pada Latihan Mandiri. Tidak ada batas jumlah total soal simulasi; satu unggahan maksimal 500 soal. <a href="<?php echo site_url('kelola/simulasi-ujian/'.$exam.'?langkah=soal'); ?>">Kembali ke pilihan soal</a>.</p><?php endif; ?>
 <details class="feature-card mb-4" id="import-templates">
  <summary class="fw-bold py-1">Butuh format berkas? Unduh template &amp; lihat panduan</summary>
  <div class="d-flex flex-wrap gap-2 mt-3">
   <?php foreach(array('naskah'=>'TXT naskah','naskah-gambar'=>'TXT dengan gambar','txt'=>'TXT berlabel','docx'=>'Word','xlsx'=>'Excel','csv'=>'CSV') as $ext=>$label): ?>
    <a class="btn <?php echo $ext==='naskah'?'btn-primary':'btn-outline-primary'; ?>" href="<?php echo site_url('kelola/bank-soal/template/'.$ext); ?>"><i class="bi bi-download me-2" aria-hidden="true"></i><?php echo $label; ?></a>
   <?php endforeach; ?>
  </div>
  <p class="mt-3">TXT naskah: judul boleh di awal, soal <code>1. Pertanyaan</code>, pilihan <code>a. Jawaban</code> sampai <code>d.</code>, lalu <code>KUNCI JAWABAN</code> dengan isi <code>1.a 2.b 3.c</code>. Pilih mapel dan kelas di bawah; tulisan “Level 1” tidak otomatis berarti kelas 1.</p>
  <details><summary class="fw-bold">Ketentuan berkas, pembahasan dan gambar</summary>
   <ul class="mt-2">
    <li>Maksimal 500 soal / 3 MiB. TXT harus UTF-8. Empat pilihan A–D berbeda, satu kunci A/B/C/D. Kunci <code>-</code> berarti belum lengkap, bukan jawaban benar.</li>
    <li>Pembahasan opsional: tambahkan <code>PEMBAHASAN: ...</code> sesudah pilihan pada TXT naskah. Pembahasan kosong diberi pengingat, tidak dibuat otomatis.</li>
    <li>Gambar otomatis: unggah <code>soal7.jpg</code> untuk nomor 7 tanpa menulis nama di soal; gambar tampil di atas teks. Untuk posisi tertentu/pilihan jawaban, tulis <code>[GAMBAR: nama.png]</code>. Galeri lama <code>GAMBAR: satu.png | dua.png</code> tetap didukung. Unggah gambar terpisah; teks rujukan PDF bukan lampiran.</li>
    <li>Header <code>BAGIAN A - MUDAH</code> memakai tingkat dasar; <code>BAGIAN B - SEDANG</code> atau <code>SULIT</code> memakai lanjutan. Tanpa header, gunakan pilihan tingkat di bawah.</li>
    <li>Untuk baris isi yang menyerupai nomor, pilihan, label atau judul kunci, awali dengan backslash (<code>\</code>) agar dibaca sebagai teks biasa.</li>
    <li>TXT berlabel lama tetap memakai <code>MAPEL:</code>, <code>SOAL:</code>, <code>KUNCI:</code> dan pemisah <code>---</code>. Word memakai tabel; Excel lembar pertama kolom A–K. Jangan ubah nama kolom. Rumus Excel dan gambar tempelan Word/Excel ditolak, bukan dihapus diam-diam.</li>
   </ul>
  </details>
 </details>
 <?php if($error): ?><div class="alert alert-danger" role="alert"><?php echo nl2br(html_escape($error)); ?></div><?php endif; ?>
 <?php if($analysis!==NULL && isset($analysis['ready_count'])): ?>
  <section class="feature-card mb-4" aria-labelledby="import-result">
   <h2 class="h4" id="import-result">Hasil pemeriksaan: belum siap disimpan</h2>
   <p class="alert alert-warning">Terbaca <strong><?php echo count($analysis['items']); ?> soal</strong>; <?php echo (int)$analysis['ready_count']; ?> lolos pemeriksaan baris, <?php echo count($analysis['items'])-(int)$analysis['ready_count']; ?> perlu diperbaiki. <?php echo (int)$analysis['warning_count']; ?> soal memiliki catatan. <strong>Belum ada soal yang disimpan ke bank.</strong></p>
   <?php if($analysis['headings']): ?><details class="mb-3"><summary>Judul yang terbaca (bukan soal)</summary><p class="text-break mt-2"><?php echo nl2br(html_escape(implode("\n",$analysis['headings']))); ?></p></details><?php endif; ?>
   <?php if($analysis['errors']): ?><div class="alert alert-danger"><h3 class="h6">Perbaiki berkas / pengaturan</h3><ul class="mb-0"><?php foreach($analysis['errors'] as $issue): ?><li><?php echo html_escape($issue); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
   <p class="small text-secondary">Klik nomor untuk melihat teks lengkap, pilihan, kunci, dan catatan. Pemeriksaan ini memvalidasi struktur/kelengkapan, bukan kebenaran materi atau izin penggunaan.</p>
   <?php foreach($analysis['items'] as $item): $row=$item['row']; ?>
    <details class="border rounded p-3 mb-2">
     <summary class="fw-bold text-break">No. <?php echo (int)$item['number']; ?><?php if($item['line']): ?> · baris <?php echo (int)$item['line']; ?><?php endif; ?> —
      <span class="<?php echo $item['errors']?'text-danger':'text-success'; ?>"><?php echo $item['errors']?'Perlu diperbaiki':'Struktur lengkap'; ?></span>
      <?php if($item['warnings']): ?><span class="text-secondary"> · Ada catatan</span><?php endif; ?>
      <span class="d-block fw-normal mt-1"><?php echo html_escape(mb_strimwidth($row['soal'],0,130,'…')); ?></span>
     </summary>
     <?php if($item['errors']): ?><ul class="text-danger mt-3"><?php foreach($item['errors'] as $issue): ?><li><?php echo html_escape($issue); ?></li><?php endforeach; ?></ul><?php endif; ?>
     <?php if($item['warnings']): ?><ul class="mt-3"><?php foreach($item['warnings'] as $issue): ?><li><?php echo html_escape($issue); ?></li><?php endforeach; ?></ul><?php endif; ?>
     <?php if(!empty($item['auto_images'])): ?><p class="small text-primary text-break">Gambar terdeteksi otomatis untuk bagian atas soal: <?php echo html_escape(implode(', ',$item['auto_images'])); ?>.</p><?php endif; ?>
     <p class="text-break mt-3"><?php echo nl2br(html_escape($row['soal'])); ?></p>
     <ol type="A"><?php foreach(isset($row['e'])?array('a','b','c','d','e'):array('a','b','c','d') as $option): ?><li class="text-break"><?php echo nl2br(html_escape($row[$option])); ?></li><?php endforeach; ?></ol>
     <p>Kunci dari berkas: <strong><?php echo html_escape($row['kunci']!==''?$row['kunci']:'Belum ada'); ?></strong></p>
     <p class="text-break">Pembahasan: <?php echo nl2br(html_escape($row['bahasan']!==''?$row['bahasan']:'Belum diisi')); ?></p>
     <?php if($row['gambar']!==''): ?><p class="text-break">Nama gambar: <?php echo html_escape($row['gambar']); ?></p><?php endif; ?>
    </details>
   <?php endforeach; ?>
   <p class="mt-3 mb-0">Perbaiki berkas, lalu pilih ulang dokumen beserta lampirannya di bawah. Tidak ada impor sebagian atau penggantian jawaban otomatis.</p>
  </section>
 <?php endif; ?>
 <?php echo form_open_multipart('kelola/bank-soal/impor'.($exam!==''?'?ujian='.(int)$exam:''),array('class'=>'feature-card')); ?>
  <h2 class="h5 mb-3"><i class="bi bi-file-earmark-arrow-up me-2" aria-hidden="true"></i>1. Pilih berkas</h2>
  <div class="mb-3"><label class="form-label fw-semibold" for="document">Dokumen soal</label><input class="form-control" type="file" name="document" id="document" accept=".docx,.xlsx,.txt,.csv" aria-describedby="file-help" required><p class="small text-secondary mt-1" id="file-help">TXT, Word (.docx), Excel (.xlsx), atau CSV · maksimal 500 soal / 3 MiB. Pilih ulang berkas jika mengirim kembali.</p></div>
  <fieldset class="mb-4">
   <legend class="h6">Lampiran gambar <span class="fw-normal text-secondary">(opsional)</span></legend>
   <label class="visually-hidden" for="images">Pilih lampiran gambar</label>
   <input class="form-control" type="file" name="images[]" id="images" accept="image/png,image/jpeg" multiple aria-describedby="images-selection-help images-help images-status" data-question-images>
   <input type="hidden" name="images_expected" value="">
   <p class="small mt-2 mb-1" id="images-selection-help"><strong>Blok/pilih semua gambar sekaligus</strong>, lalu Buka/Open. Memilih ulang mengganti pilihan sebelumnya.</p>
   <p class="small text-secondary mb-1" id="images-help">Hingga 500 PNG/JPEG · 3 MiB per gambar · 32 MiB total.</p>
   <p class="small mb-2" id="images-status" role="status" aria-live="polite">Belum ada lampiran dipilih.</p>
   <p class="small mb-2">Otomatis: <code>soal7.jpg</code> → gambar di atas soal nomor 7. Gunakan penanda hanya untuk posisi khusus atau pilihan jawaban.</p>
   <details class="border rounded p-3" id="import-image-guide">
   <summary class="fw-bold">Panduan gambar: cara memilih, penempatan &amp; contoh jawaban</summary>
   <p class="mt-3"><strong>Nama berkas menentukan hubungan, bukan urutan Anda memilih gambar.</strong> Tanpa penanda, nama <code>soal7.jpg</code>, <code>soal9.png</code>, atau <code>soal18.jpeg</code> otomatis mengikuti nomor soal dan tampil di atas teks.</p>
   <p>Beberapa gambar untuk nomor yang sama: <code>soal7-1.jpg</code>, <code>soal7-2.jpg</code>, <code>soal7-10.jpg</code> tampil berurutan 1, 2, 10. Nomor mengikuti nomor asli TXT naskah; pada Word/Excel/CSV/TXT berlabel mengikuti urutan soal mulai 1 (header tidak dihitung), bukan ID bank soal.</p>
   <p>Jika nama sudah dipakai pada <code>[GAMBAR: nama.png]</code> atau <code>GAMBAR:</code>, penempatan tersebut didahulukan dan tidak digandakan otomatis. Nama bebas seperti <code>diagram.png</code> tetap membutuhkan penanda karena nomor tujuannya tidak diketahui. Satu soal/pilihan boleh mempunyai beberapa gambar.</p>
   <ul>
    <li>Komputer: tahan <kbd>Ctrl</kbd> (atau <kbd>Command</kbd> di Mac) sambil klik gambar; gunakan <kbd>Shift</kbd> untuk memilih rentang. Jika seluruh isi folder diperlukan, gunakan <kbd>Ctrl+A</kbd> / <kbd>Command+A</kbd>.</li>
    <li>Ponsel: pilih beberapa berkas, biasanya dengan menekan lama gambar lalu memilih gambar lainnya, sesuai aplikasi pemilih berkas.</li>
    <li>Maksimal 4096 piksel per sisi, 4 juta piksel per gambar dan 64 juta piksel total. Perkecil gambar atau bagi impor jika melebihi batas.</li>
   </ul>
   <ol>
    <li>Untuk gambar biasa, beri nama sesuai nomor, misalnya <code>soal7.jpg</code>. Tidak perlu mencantumkan nama ini di teks soal.</li>
    <li>Hanya untuk posisi khusus/pilihan jawaban, tulis <code>[GAMBAR: nama.png]</code> di lokasi yang diinginkan. Tidak perlu mengulang nama itu pada baris <code>GAMBAR:</code>. Galeri tambahan tetap dapat memakai <code>GAMBAR: satu.png | dua.png</code>.</li>
    <li>Pilih berkas PNG/JPEG yang namanya <strong>sama persis</strong>, termasuk huruf besar/kecil dan ekstensi. Contoh: <code>.jpg</code> berbeda dari <code>.jpeg</code>.</li>
    <li>Klik <strong>Periksa berkas</strong>. Nama hilang, lampiran yang belum digunakan, atau nama ganda akan ditandai. Pada pratinjau, periksa posisi gambar pada soal dan pilihan sebelum menyimpan.</li>
   </ol>
   <details class="mb-3"><summary class="fw-bold">Contoh: dua gambar untuk soal 5, satu gambar untuk soal 7</summary>
    <pre class="bg-light border rounded p-3 mt-2 text-dark text-break" style="white-space: pre-wrap" tabindex="0" aria-label="Contoh format pengaitan gambar">5. Bandingkan gambar 1 dan gambar 2 berikut.
   a. Pilihan pertama
   b. Pilihan kedua
   c. Pilihan ketiga
   d. Pilihan keempat
GAMBAR: soal5a.png | soal5b.png

7. Perhatikan gambar berikut.
   a. Pilihan pertama
   b. Pilihan kedua
   c. Pilihan ketiga
   d. Pilihan keempat
GAMBAR: soal7.png

KUNCI JAWABAN
5.a 7.c</pre>
    <p>Unggah ketiga lampiran: <code>soal5a.png</code>, <code>soal5b.png</code>, <code>soal7.png</code>. Soal 5 menampilkan dua gambar sesuai urutan nama pada <code>GAMBAR:</code>; soal 7 hanya menampilkan gambarnya sendiri. Kunci contoh di atas hanya ilustrasi format.</p>
   </details>
   <p class="small">Word/Excel/CSV: penanda <code>[GAMBAR: nama.png]</code> dapat ditulis pada kolom <code>soal</code> dan <code>a</code>–<code>d</code>. Kolom <code>gambar</code> opsional untuk galeri tambahan, berisi nama dipisahkan <code>|</code>. Satu berkas boleh digunakan beberapa kali atau beberapa soal: unggah cukup sekali. Pembahasan masih berupa teks; gambar tempelan Word/Excel tidak dibaca.</p>
   <details class="mb-3"><summary class="fw-bold">Contoh: tabel dan gambar tepat di tengah kalimat</summary>
    <p class="mt-2">Pisahkan tabel dan rumus/simbol menjadi dua berkas. Letakkan penandanya di lokasi yang sesuai; tidak harus semua di bawah soal.</p>
    <pre class="bg-light border rounded p-3 text-dark text-break" style="white-space: pre-wrap" tabindex="0" aria-label="Contoh dua gambar di dalam soal">6. Perhatikan tabel berikut.
[GAMBAR: tabel6.png]
Nilai dari [GAMBAR: rumus6.png] adalah …
   a. 9
   b. 8
   c. 7
   d. 6

KUNCI JAWABAN
6.a</pre>
    <p class="small mb-0">Blok/pilih <code>tabel6.png</code> dan <code>rumus6.png</code> sekaligus pada lampiran. Tabel tampil di baris tersendiri; rumus tampil setelah “Nilai dari”. Kunci di atas hanya contoh format, sesuaikan dengan soal sebenarnya.</p>
   </details>
   <details class="mb-3"><summary class="fw-bold">Contoh: gambar langsung pada pilihan jawaban</summary>
    <p class="mt-2">Pilihan boleh berupa gambar saja, teks + gambar, atau beberapa gambar. Gambar muncul dalam tombol pilihan dan tetap bersama jawabannya saat pilihan diacak.</p>
    <pre class="bg-light border rounded p-3 text-dark text-break" style="white-space: pre-wrap" tabindex="0" aria-label="Contoh pilihan jawaban gambar">1. Gambar manakah yang menunjukkan segitiga?
   a. [GAMBAR: lingkaran.png]
   b. [GAMBAR: segitiga.png]
   c. [GAMBAR: persegi.png]
   d. [GAMBAR: persegi-panjang.png]

KUNCI JAWABAN
1.b</pre>
    <p class="small mb-0">Unggah keempat berkas sesuai nama. Contoh dua gambar dalam satu pilihan: <code>a. [GAMBAR: kiri.png] dan [GAMBAR: kanan.png]</code>. Nama salah, lampiran hilang, atau penanda tidak lengkap akan ditolak sebelum penyimpanan. Pratinjau menampilkan posisi gambar; periksa juga isi dan kunci.</p>
   </details>
   </details>
  </fieldset>
  <fieldset class="border-top pt-4 mb-4"><legend class="h5 float-none w-auto"><i class="bi bi-funnel me-2" aria-hidden="true"></i>2. Tentukan klasifikasi</legend><p class="small text-secondary">Untuk TXT naskah, pilih mapel, kelas, dan tingkat. Format berlabel/tabel memakai klasifikasi dari berkas.</p><div class="row g-3">
   <div class="col-md-4"><label class="form-label" for="mapel">Mata pelajaran</label><select class="form-select" name="mapel" id="mapel"><option value="">Pilih mata pelajaran</option><?php foreach($subjects as $s): ?><option value="<?php echo html_escape($s['slug']); ?>"<?php echo $values['mapel']===$s['slug']?' selected':''; ?>><?php echo html_escape($s['name']); ?></option><?php endforeach; ?></select></div>
   <div class="col-md-4"><label class="form-label" for="kelas">Kelas</label><select class="form-select" name="kelas" id="kelas"><option value="">Pilih kelas</option><?php foreach(study_grades() as $code=>$g): ?><option value="<?php echo html_escape($code); ?>"<?php echo $values['kelas']===$code?' selected':''; ?>><?php echo html_escape($g['name']); ?></option><?php endforeach; ?></select></div>
   <div class="col-md-4"><label class="form-label" for="tingkat">Tingkat bawaan</label><select class="form-select" name="tingkat" id="tingkat"><?php foreach(array('basic'=>'Dasar','advanced'=>'Lanjutan') as $code=>$label): ?><option value="<?php echo $code; ?>"<?php echo $values['tingkat']===$code?' selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
  </div></fieldset>
  <fieldset class="border-top pt-4"><legend class="h5 float-none w-auto"><i class="bi bi-shield-check me-2" aria-hidden="true"></i>3. Lengkapi sumber &amp; izin</legend>
   <div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="rights_status">Hak penggunaan</label><select class="form-select" name="rights_status" id="rights_status"><?php foreach(array('owned'=>'Karya sendiri','licensed'=>'Memiliki izin/lisensi','public_domain'=>'Domain publik') as $code=>$label): ?><option value="<?php echo $code; ?>"<?php echo $values['rights_status']===$code?' selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
    <div class="col-md-8"><label class="form-label" for="source_note">Sumber / penulis / izin</label><textarea class="form-control" name="source_note" id="source_note" minlength="5" maxlength="1000" rows="2" required><?php echo html_escape($values['source_note']); ?></textarea></div>
   </div>
   <p class="small text-secondary mt-2">Pastikan Anda berhak menggunakan seluruh soal dan gambar. Mengunggah berkas tidak otomatis memberikan izin.</p>
  </fieldset>
  <div class="border-top pt-3 mt-3 d-flex flex-column flex-md-row align-items-md-center gap-3">
   <button class="btn btn-primary flex-shrink-0" type="submit"><i class="bi bi-file-earmark-check me-2" aria-hidden="true"></i>Periksa berkas</button>
   <p class="small text-secondary mb-0">Belum menyimpan soal. Setelah pemeriksaan, tinjau pratinjau dan konfirmasi <strong>Simpan ke bank</strong>.</p>
  </div>
 <?php echo form_close(); ?>
</section>
<script src="<?php echo base_url('assets/js/question-image-upload.js?v='.filemtime(FCPATH.'assets/js/question-image-upload.js')); ?>" defer></script>
