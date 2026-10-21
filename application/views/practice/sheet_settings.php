<?php
require_once dirname(__DIR__,2).'/libraries/Practice_sheet.php';
$sheet_values=isset($values)?array_intersect_key($values,Practice_sheet::defaults())+Practice_sheet::defaults():Practice_sheet::defaults();
?>
<fieldset class="mt-4 mb-3">
 <legend class="h5">Tampilan &amp; fokus belajar</legend>
 <div class="row g-3">
  <div class="col-sm-6"><label class="form-label" for="view_mode">Tampilan lembar soal</label><select class="form-select" id="view_mode" name="view_mode">
   <option value="ledger"<?php echo $sheet_values['view_mode']==='ledger'?' selected':''; ?>>Ledger · semua soal, gulir otomatis</option>
   <option value="single"<?php echo $sheet_values['view_mode']==='single'?' selected':''; ?>>Per soal · satu per satu</option>
  </select></div>
  <div class="col-sm-6"><label class="form-label" for="focus_guard">Anti-cheating / pantau fokus</label><select class="form-select" id="focus_guard" name="focus_guard">
   <option value="0"<?php echo $sheet_values['focus_guard']==='0'?' selected':''; ?>>Nonaktif</option>
   <option value="1"<?php echo $sheet_values['focus_guard']==='1'?' selected':''; ?>>Aktif · akhiri saat mencapai batas</option>
  </select></div>
  <div class="col-sm-6"><label class="form-label" for="focus_limit">Selesai pada perpindahan ke-</label><input class="form-control" type="number" id="focus_limit" name="focus_limit" min="1" max="10" value="<?php echo html_escape($sheet_values['focus_limit']); ?>" required aria-describedby="focus-help"></div>
 </div>
 <p id="focus-help" class="small text-secondary mt-3 mb-0">Jika aktif: pindah tab/aplikasi, jendela kehilangan fokus, atau layar tertidur akan dicatat. Batas 3 berarti sesi selesai otomatis pada kejadian ke-3, memakai jawaban yang berhasil disimpan. Jeda browser lebih dari 30 detik juga dapat terhitung sebagai layar tidak aktif. Ini bukan bukti kecurangan; browser tidak selalu dapat mengenali sleep. Perlu JavaScript dan koneksi internet; kejadian saat offline dikirim setelah tersambung. Pengaturan berlaku pada sesi baru.</p>
</fieldset>
