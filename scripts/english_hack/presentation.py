"""Editorial reading cues. Original lesson text and assessment content remain unchanged."""
import re
LABELS={
'F01':('Nama huruf berbeda dari bunyi','Dengarkan gabungan bunyinya','nama huruf|bunyi|suku kata|tekanan'),
'F02':('Pilih be sesuai subjek','Kenali tiga jenis pelengkap','am|is|are|identitas|sifat|keterangan tempat'),
'F03':('A/an mengikuti bunyi awal','Bedakan satu, banyak, dan bahan','bunyi awal|bunyi vokal|Bentuk jamak|tidak teratur|Water|rice'),
'F04':('Tentukan jarak dan jumlah','Tunjukkan pemiliknya','This|that|These|those|kepemilikan|it is|it has'),
'F05':('Letakkan sifat pada tempatnya','Jelaskan posisi dan negasi','Adjective|sebelum|sesudah|Tempat|not|Where'),
'F06':('Umur dan jam memiliki pola','Pasangkan waktu dengan preposisi','Umur|be|half past|at untuk jam|on untuk hari|in untuk bulan'),
'F07':('Mulai instruksi dari kata kerja','Can selalu diikuti bentuk dasar','kata kerja dasar|negatif|Can|can|bentuk dasar'),
'F08':('Balik be untuk bertanya','Minta klarifikasi saat diperlukan','pindahkan|kata tanya|Jawaban pendek|Please speak slowly|kemampuan komunikasi'),
'A101':('Kebiasaan mengikuti subjek','Bedakan be dan kata kerja tindakan','Simple Present|s/es|kata kerja inti|tidak wajib|objek'),
'A102':('Does membawa penanda orang ketiga','Pahami urutan dan pengecualiannya','do/does|bentuk dasar|does|Kata tanya|be|pelaku'),
'A103':('Posisi frekuensi mengikuti jenis verb','Frekuensi tidak selalu berarti kepastian','sebelum kata kerja utama|sesudah be|Never|biasanya|pengecualian'),
'A104':('Bedakan rutinitas dan proses','Lengkapi be, lalu bentuk -ing','Present Continuous|situasi sementara|be|negatif|know|want'),
'A105':('Perkenalkan keberadaan benda','Bentuk pertanyaan dan pilih some/any','There is|there are|Some|any|pengecualian'),
'A106':('Jumlah bergantung pada jenis benda','Gunakan pola permintaan yang wajar','many|much|countable|uncountable|some|permintaan'),
'A107':('Satu modal, beberapa fungsi','Buat ajakan yang bisa ditanggapi','Can|kemampuan|izin|permintaan|Let’s|Would you like|ajakan'),
'A108':('Hubungkan gagasan dengan tujuan','Pastikan pesan mudah ditindaklanjuti','and|but|because|so|pesan|tujuan|informasi'),
'A201':('Ceritakan waktu yang sudah selesai','Did mengembalikan verb ke V1','Simple Past|Past Simple|lampau|did|bentuk dasar|V1'),
'A202':('Bedakan latar dan kejadian utama','Pilih was/were sesuai subjek','Past Continuous|Past Simple|was|were|when|while'),
'A203':('Pilih bentuk sesuai jenis rencana','Jadwal dan pengaturan punya pola','will|going to|Present Continuous|present continuous|rencana|jadwal'),
'A204':('Bandingkan dua pilihan atau satu kelompok','Jaga makna saat membandingkan','comparative|Comparative|superlative|Superlative|as|than|lebih'),
'A205':('Hubungkan pengalaman dengan sekarang','Pisahkan dari waktu lampau yang selesai','Present Perfect|have/has|V3|Past Simple|waktu|selesai'),
'A206':('Bedakan kewajiban dan saran','Tidak wajib berbeda dari dilarang','must|have to|should|mustn’t|must not|don’t have to|tidak wajib|dilarang'),
'A207':('Verb pertama menentukan bentuk berikutnya','Preposisi juga memengaruhi bentuk','to|gerund|ing|preposisi|kata kerja'),
'A208':('Susun cerita dalam urutan yang jelas','Tutup dengan pesan yang utuh','orientasi|rangkaian kejadian|urutan|cerita|paragraf|pesan|pembaca|tujuan'),
'B101':('Hasil atau durasi: pilih fokusnya','Tentukan awal dan lamanya waktu','Perfect Simple|Continuous|hasil|durasi|for|since|stative'),
'B102':('Tandai kejadian yang lebih dahulu','Gabungkan latar, peristiwa, dan hasil','Past Perfect|Past Simple|Past Continuous|urutan|lebih dulu|latar'),
'B103':('Lihat proses pada titik masa depan','Perhatikan klausa waktu dan batasnya','Future Continuous|present|when|until|by|klausa waktu'),
'B104':('Pilih tingkat kemungkinan','Jaga makna pada if dan unless','Zero|First|Second|conditional|unless|if|andaian'),
'B105':('Pindahkan fokus ke penerima tindakan','Gunakan passive hanya pada konteks tepat','Passive|be|V3|objek|pelaku|transitif'),
'B106':('Gunakan klausa untuk mengidentifikasi','Hapus hanya unsur yang boleh dihilangkan','relative clause|who|which|that|objek|subjek'),
'B107':('Laporkan isi, bukan sekadar menyalin','Sesuaikan waktu dan urutan pertanyaan','said|told|backshift|urutan pernyataan|reported|waktu'),
'B108':('Dukung pendapat dengan alasan','Buat email yang memiliki tindakan lanjut','pendapat|alasan|bukti|email|permintaan|tujuan'),
'B201':('Tense dan aspect memberi sudut pandang','Bedakan hasil, proses, dan durasi','Simple|continuous|perfect|Future Perfect Continuous|keadaan|kegiatan'),
'B202':('Tentukan waktu kondisi dan hasil','Wish berbeda menurut waktu penyesalan','Third conditional|Mixed conditional|Wish|past form|past perfect|kapan'),
'B203':('Sesuaikan keyakinan dengan bukti','Gunakan have + V3 untuk modal lampau','Must|May/might/could|have + V3|Should have|Could have|dugaan|fakta'),
'B204':('Pertahankan V3 saat auxiliary berubah','Bedakan mengerjakan dan meminta layanan','be + V3|Reporting passive|Have/get something done|mengatur|berbeda'),
'B205':('Kata pelapor menentukan pola','Perubahan bentuk dapat mengubah makna','Reporting verbs|reporting verbs|suggest|advise|deny|remember|stop|makna'),
'B206':('Koma menentukan jenis informasi','Hubungkan klausa tanpa mengaburkan pelaku','non-defining|defining|koma|although|despite|subjek|participle'),
'B207':('Pelajari pasangan kata yang lazim','Pilih bentuk sesuai konteks dan pembaca','pasangan lazim|Bentuk turunan|collocation|Collocation|phrasal verb|register|konteks|kata ganti'),
'B208':('Laporkan angka dengan ukuran yang benar','Pisahkan temuan dari klaim sebab','percentage points|persentase|poin persentase|sebab|korelasi|bukti|rekomendasi'),
'C101':('Batasi klaim sesuai bukti','Hedging harus tetap jelas','bukti|menggeneralisasikannya|May|suggests|batas|jangan menumpuk|klaim'),
'C102':('Inversion menonjolkan pembatasan','Cleft menyorot informasi tertentu','auxiliary sebelum subject|klausa utama|Cleft|menekankan|percakapan sehari-hari'),
'C103':('Padatkan gagasan tanpa menumpuk nomina','Perjelas siapa melakukan apa','Nominalisation|tumpukan nomina|pelaku dan tindakan|Passive|kebutuhan pembaca'),
'C104':('Bangun rantai informasi','Pastikan setiap rujukan jelas','informasi baru|Koherensi|rujukannya harus jelas|kontras|inferensi|penambahan'),
'C105':('Parafrasa harus mempertahankan makna','Sintesis menghubungkan beberapa sumber','mempertahankan makna|sumber|Sintesis|pertanyaan|sumber rekaan'),
'C106':('Kesantunan bergantung situasi','Tutup negosiasi dengan status yang jelas','Kesantunan|hubungan|batas|Conditional offers|syarat|belum disepakati'),
'C107':('Tanyakan siapa yang diwakili data','Uji argumen, bukan pribadi penulis','siapa|pembandingnya|asumsi|bukti|Counterargument|alasan'),
'C108':('Susun proposal untuk sebuah keputusan','Bedakan target dari hasil yang terbukti','masalah|risiko|Target bukan prediksi|Executive summary|informasi yang belum tersedia'),
'C201':('Posisi kata mengubah cakupan makna','Bedakan makna literal dan implikasi','Not all|No members|Only|cakupan|Makna pragmatis|klarifikasi'),
'C202':('Sesuaikan bahasa, pertahankan fakta','Urutkan informasi menurut audiens','fakta dasar|batas bukti|dampak|Pemberitahuan pengguna|Ringkasan pimpinan|not yet confirmed'),
'C203':('Pahami idiom sebagai satu kesatuan','Periksa konteks sebelum menyimpulkan ironi','Idiom|kata demi kata|Ironi|nada|kepastian|Retorika'),
'C204':('Sunting makna sebelum bentuk','Tanda baca dapat mengubah cakupan','makna terlebih dahulu|sejajar|Tanda baca|cakupan|pertanyaan editorial'),
'C205':('Ringkas posisi tanpa memaksa sepakat','Verifikasi pemahaman dan status keputusan','posisi|alasan|batas|rangkuman sementara|transkrip|percakapan nyata'),
'C206':('Samakan dasar perbandingan','Akui perbedaan yang belum terjelaskan','definisi|rentang waktu|populasi|denominator|Rekonsiliasi|unresolved discrepancy'),
'C207':('Sebut tindakan dan tanggung jawab','Janji tindakan berbeda dari jaminan hasil','pelaku|tanggung jawab|dampak|diketahui|belum diketahui|komitmen|koreksi eksplisit'),
'C208':('Buktikan kemampuan pada masalah baru','Gunakan beberapa jenis bukti','Portofolio|umpan balik|revisi|kondisi|Rubrik|bukti|bukan jaminan sertifikasi')
}
def blocks(unit):
 a,b,terms=LABELS[unit['id']]
 candidates=[(x,'key') for x in terms.split('|') if len(x)>2]
 candidates += [(p['example'].rstrip('.'),'example') for p in unit['patterns']]
 candidates.sort(key=lambda x:len(x[0]),reverse=True)
 out=[]
 for idx,text in enumerate(unit['lesson']):
  spans=[]
  for term,kind in candidates:
   match=re.search(r'(?<!\w)'+re.escape(term)+r'(?!\w)',text,re.I)
   if match and not any(match.start()<e and match.end()>s for s,e,k in spans):spans.append((match.start(),match.end(),kind))
   if len(spans)>=3:break
  segments=[];pos=0
  for start,end,kind in sorted(spans):
   if start>pos:segments.append({'text':text[pos:start],'kind':'plain'})
   segments.append({'text':text[start:end],'kind':kind});pos=end
  if pos<len(text):segments.append({'text':text[pos:],'kind':'plain'})
  assert ''.join(s['text'] for s in segments)==text
  out.append({'title':[a,b][min(idx,1)],'segments':segments})
 return out
