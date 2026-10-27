from html import escape
from pathlib import Path
import textwrap
ROOT=Path(__file__).resolve().parents[2]
NAVY='#102f3b'; INK='#183d47'; MINT='#d9f1e6'; GOLD='#ffe4a5'; PAPER='#fffdf7'
def txt(x,y,s,size=20,fill=INK,weight=400):
 return f'<text x="{x}" y="{y}" font-family="Arial, sans-serif" font-size="{size}" font-weight="{weight}" fill="{fill}">{escape(s)}</text>'
def rect(x,y,w,h,fill,stroke='none'):
 return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="16" fill="{fill}" stroke="{stroke}"/>'
def lines(x,y,s,width=48,size=19,fill=INK):
 return ''.join(txt(x,y+i*(size+9),l,size,fill) for i,l in enumerate(textwrap.wrap(s,width)))
def svg(slug,title,sub,body,height=620,foot='Baca makna dan konteks sebelum memilih rumus.'):
 out=f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 {height}" role="img" aria-labelledby="title desc"><title id="title">{escape(title)}</title><desc id="desc">{escape(sub+" "+foot)}</desc>'+rect(0,0,1200,height,PAPER)+rect(0,0,1200,116,NAVY)+txt(36,49,title,30,'#ffffff',700)+txt(36,87,sub,18,'#d9f1e6')+body+lines(36,height-54,foot,112,16)+'</svg>'
 path=ROOT/'assets/images/english-hack'/f'{slug}.svg';path.write_text(out)
 return {'id':slug,'title':title,'description':sub,'note':foot,'file':f'assets/images/english-hack/{slug}.svg'}
def generate():
 charts=[]
 b=''
 for x,title,form,ex,note in [(32,'KEADAAN / IDENTITAS','S + be + pelengkap','She is happy.','be dapat diikuti adjective, noun, atau place.'),(620,'TINDAKAN / KEBIASAAN','S + V1 / V-s','She studies English.','Verb utama menyatakan tindakan/kebiasaan.')]:
  b+=rect(x,146,548,250,MINT if x==32 else GOLD)+txt(x+22,184,title,22,INK,700)+txt(x+22,231,form,27,INK,700)+txt(x+22,276,ex,25)+lines(x+22,318,note,44)
 b+=rect(32,418,1136,110,'#e9edf0')+txt(56,452,'Tanya dulu: predikatnya BE atau kata kerja lain?',23,INK,700)+txt(56,494,'She is happy.  →  Is she happy?     |     She studies.  →  Does she study?',22)
 charts.append(svg('sentence-engine','01 / Mesin kalimat','Kunci awal: jangan menempelkan is pada setiap kalimat.',b,620,'S = subject. Pelengkap menjelaskan identitas, sifat, atau tempat. Pola tindakan memiliki verb utama.'))
 b=''
 for i,(label,form,example) in enumerate([('Ada BE?','Balik be dan subject.','She is ready. → Is she ready?'),('Ada modal / auxiliary?','Pindahkan auxiliary pertama.','She can swim. → Can she swim?'),('Simple present / past, verb lain?','Tambahkan do / does / did + V1.','She works. → Does she work?'),('Who/what menjadi subject?','Biasanya tanpa do pada pertanyaan afirmatif.','Who works here? / What happened?')]):
  y=140+i*100;b+=rect(32,y,1136,86,MINT if i%2==0 else GOLD)+txt(50,y+33,label,20,INK,700)+txt(50,y+64,form,17)+txt(650,y+49,example,20)
 charts.append(svg('auxiliary','02 / Pembuka pertanyaan','Cari auxiliary lebih dulu. Setelah does/did, verb utama kembali ke V1.',b,640,'Contoh umum pertanyaan afirmatif. Emphasis, negation, dan indirect questions memerlukan pembahasan tersendiri.'))
 b=''; cols=[('SIMPLE','fakta / kejadian'),('CONTINUOUS','proses'),('PERFECT','sebelum titik acuan'),('PERFECT CONTINUOUS','durasi ke titik acuan')]
 for j,(name,note) in enumerate(cols):
  x=174+j*248;b+=rect(x,142,236,63,NAVY)+txt(x+12,167,name,15,'#fff',700)+txt(x+12,191,note,14,'#d9f1e6')
 data=[('PRESENT',[('V1 / V-s','She works.'),('am/is/are + V-ing','She is working.'),('have/has + V3','She has worked.'),('have/has been + V-ing','She has been working.')]),('PAST',[('V2','She worked.'),('was/were + V-ing','She was working.'),('had + V3','She had worked.'),('had been + V-ing','She had been working.')]),('FUTURE*',[('will + V1','She will work.'),('will be + V-ing','She will be working.'),('will have + V3','She will have worked.'),('will have been + V-ing','She will have been working.')])]
 for i,(row,cells) in enumerate(data):
  y=220+i*132;b+=txt(32,y+50,row,21,INK,700)
  for j,(form,ex) in enumerate(cells):
   x=174+j*248;b+=rect(x,y,236,117,MINT if i%2==0 else GOLD)+lines(x+12,y+28,form,24,17)+lines(x+12,y+83,ex,27,15)
 charts.append(svg('tenses','03 / Peta 12 kombinasi tense–aspect','Peta belajar, bukan daftar rumus yang harus dihafal sekaligus.',b,750,'*Future di sini memakai will sebagai pola bantu. Going to, present continuous, dan present simple juga bisa merujuk masa depan.'))
 b=''
 for i,(title,start,end,ex) in enumerate([('PAST PERFECT','sebelum','titik lampau','When I arrived, she had left.'),('PRESENT PERFECT','sebelum / sejak','sekarang','I have lived here for two years.'),('FUTURE PERFECT','sebelum','titik masa depan','By Friday, I will have finished.')]):
  y=145+i*125;b+=txt(35,y+20,title,20,INK,700)+f'<path d="M 310 {y+34} H 1110" stroke="{INK}" stroke-width="3"/>'+f'<circle cx="430" cy="{y+34}" r="9" fill="#b16c00"/><circle cx="960" cy="{y+34}" r="11" fill="#167c64"/>'+txt(350,y+12,start,17)+txt(870,y+12,end,17)+txt(310,y+77,ex,24)
 charts.append(svg('perfect','04 / Perfect menghubungkan dua titik','Pertanyaannya: sebelum titik acuan yang mana?',b,620,'Perfect tidak selalu berarti selesai. Dengan keadaan/durasi, situasi dapat berlanjut. For = durasi; since = titik awal.'))
 b=''
 rows=[('ZERO','if + present, present','If water freezes, it expands.','hubungan umum'),('FIRST','if + present, will + V1','If it rains, we will stay in.','kemungkinan ke depan'),('SECOND','if + past, would + V1','If I had time, I would join.','andaian kini / nanti'),('THIRD','if + had V3, would have V3','If I had known, I would have helped.','andaian lampau'),('MIXED','if + had V3, would + V1 now','If I had saved it, I would have a copy now.','lampau → kini')]
 for i,(name,form,ex,meaning) in enumerate(rows):
  y=140+i*100;b+=rect(32,y,1136,88,MINT if i%2==0 else GOLD)+txt(48,y+31,name,19,INK,700)+txt(194,y+31,form,21,INK,700)+txt(194,y+65,ex,20)+txt(890,y+32,meaning,17)
 charts.append(svg('conditionals','05 / Pilih waktu andaian','Tentukan waktu kondisi dan hasil, baru pilih bentuk.',b,740,'Ini pola inti, bukan semua kemungkinan conditional. Modals lain, imperatives, dan konteks khusus dibahas saat penerapan.'))
 b=rect(32,145,1136,160,MINT)+txt(56,182,'ACTIVE: The team checks the files.',28,INK,700)+txt(56,235,'PASSIVE: The files are checked by the team.',28,INK,700)+txt(56,276,'Objek menjadi fokus → sesuaikan BE → verb utama V3.',21)
 for i,(form,ex) in enumerate([('present: am/is/are + V3','The file is checked.'),('past: was/were + V3','The file was checked.'),('perfect: has/have been + V3','The file has been checked.'),('modal: modal + be + V3','The file must be checked.')]):
  x=32+(i%2)*580;y=326+(i//2)*92;b+=rect(x,y,556,78,GOLD)+txt(x+18,y+29,form,19,INK,700)+txt(x+18,y+60,ex,20)
 charts.append(svg('passive','06 / Passive: fokus berpindah','Tense/aspect ada pada rangkaian auxiliary; verb utama tetap V3.',b,620,'Passive lazim untuk verb yang dapat memiliki objek. Sebut pelaku bila tanggung jawab atau sumber informasi penting.'))
 b=''
 for i,(title,form,meaning) in enumerate([('LARANGAN','You must not enter.','Tidak boleh masuk.'),('TIDAK WAJIB','You do not have to enter.','Boleh masuk, boleh tidak.'),('DEDUKSI','She must be busy.','Kesimpulan kuat menurut bukti.'),('KEMUNGKINAN','She might be busy.','Kemungkinan, belum pasti.')]):
  x=32+(i%2)*580;y=145+(i//2)*180;b+=rect(x,y,556,160,MINT if i%2==0 else GOLD)+txt(x+22,y+37,title,20,INK,700)+txt(x+22,y+83,form,26)+txt(x+22,y+127,meaning,21)
 charts.append(svg('modals','07 / Modal: bentuk kecil, makna berbeda','Tentukan fungsi: izin, kewajiban, saran, atau perkiraan.',b,620,'Sesudah modal gunakan base verb. Untuk perkiraan lampau: may/might/must/can’t + have + V3. Makna mengikuti konteks.'))
 b=''
 for i,(title,ex,note) in enumerate([('CLAIM','What do you think?','Tulis klaim dengan cakupan jelas.'),('EVIDENCE','What supports it?','Sebut sumber, angka, dan denominator.'),('LIMIT','What is still unknown?','Jangan ubah dugaan menjadi fakta.'),('ACTION','What should happen next?','Rekomendasi sebanding dengan bukti.')]):
  x=32+i*290;b+=rect(x,155,266,310,MINT if i%2==0 else GOLD)+txt(x+20,198,f'0{i+1} / {title}',22,INK,700)+lines(x+20,246,ex,19,23)+lines(x+20,344,note,23,19)
 charts.append(svg('evidence','08 / Argumen yang dapat dipercaya','Bahasa profesional menyampaikan alasan sekaligus batasnya.',b,580,'Data rekaan dalam latihan harus tetap disebut rekaan. Korelasi/urutan waktu saja tidak membuktikan sebab.'))
 b=''
 for i,(title,one,many) in enumerate([('DEKAT','THIS · this book','THESE · these books'),('JAUH','THAT · that book','THOSE · those books')]):
  y=150+i*180;b+=txt(34,y+54,title,24,INK,700)
  for j,label in enumerate([one,many]):
   x=210+j*470;b+=rect(x,y,440,150,MINT if i==0 else GOLD)+txt(x+24,y+47,label,25,INK,700)
   for k in range(1 if j==0 else 3): b+=rect(x+28+k*75,y+75,52,50,NAVY)
 charts.append(svg('demonstratives','09 / Tunjuk dengan dua keputusan','Dekat atau jauh? Satu atau banyak?',b,620,'This/that untuk satu; these/those untuk jamak. Jarak juga dapat bersifat konteks percakapan, bukan hanya fisik.'))
 return charts
MAP={'be':'sentence-engine','sentence':'sentence-engine','sounds':'sentence-engine','nouns':'demonstratives','demonstratives':'demonstratives','place':'demonstratives','time':'tenses','auxiliary':'auxiliary','questions':'auxiliary','present':'sentence-engine','frequency':'tenses','timeline':'tenses','modals':'modals','paragraph':'evidence','past':'perfect','future':'tenses','comparison':'sentence-engine','perfect':'perfect','verb-patterns':'sentence-engine','conditionals':'conditionals','passive':'passive','clauses':'auxiliary','reported':'perfect','tense-matrix':'tenses','certainty':'modals','word-families':'sentence-engine','evidence':'evidence','emphasis':'auxiliary','clarity':'sentence-engine','cohesion':'evidence','pragmatics':'modals','proposal':'evidence','nuance':'evidence','register':'evidence','mediation':'evidence','portfolio':'evidence'}
