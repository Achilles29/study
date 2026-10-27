"""Build original coursebook, offline studio, and validated application content. No database writes."""
from pathlib import Path
from importlib import import_module
from html import escape
import base64,copy,json,re,hashlib
from design import SOURCES,SECTIONS,LEVEL_INFO,CHECKPOINTS,TRACKS,TOOLKITS
from charts import generate,MAP
from presentation import blocks
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'application/content/english_hack/v1'
OUT.mkdir(parents=True,exist_ok=True)
def h(s): return escape(str(s),quote=True)
def ps(items): return ''.join('<p>'+h(p)+'</p>' for p in items)
def ul(items): return '<ul>'+''.join('<li>'+h(p)+'</li>' for p in items)+'</ul>'
def lesson_html(unit):
 out=[]
 for i,b in enumerate(unit['lesson_blocks']):
  text=''.join('<strong class="eh-key">'+h(s['text'])+'</strong>' if s['kind']=='key' else '<span class="eh-inline-example">'+h(s['text'])+'</span>' if s['kind']=='example' else h(s['text']) for s in b['segments'])
  out.append('<section class="eh-reading-block"><span class="eh-reading-number">'+str(i+1).zfill(2)+'</span><div><h3>'+h(b['title'])+'</h3><p>'+text+'</p></div></section>')
 return ''.join(out)
def prepare(q,code,index):
 q=copy.deepcopy(q);q['id']=f'{code}-Q{index+1:02}'
 if q['type']=='choice':
  options=[q.pop('answer')]+q.pop('distractors')
  assert len(options)>=3 and len(set(options))==len(options),(code,'duplicate options')
  shift=int(hashlib.sha256(q['id'].encode()).hexdigest()[:8],16)%len(options)
  q['options']=options[shift:]+options[:shift];q['answer_index']=(-shift)%len(options)
 else: assert q['answers'] and all(q['answers'])
 assert q['prompt'] and q['explanation']
 return q
charts=generate()
for c in charts:
 c['image']='data:image/svg+xml;base64,'+base64.b64encode((ROOT/c['file']).read_bytes()).decode()
chart_ids={c['id'] for c in charts}
# A chart is optional; do not show an unrelated diagram just to fill a visual slot.
for key in ['sounds','time','frequency','comparison','verb-patterns','word-families','emphasis','clauses','reported']:
 MAP[key]=None
levels=[];seen=set()
for idx,info in enumerate(LEVEL_INFO):
 slug,name,cefr,tagline,outcome,entry,exit_,cap_title,cap_brief,cap_criteria=info
 units=copy.deepcopy(import_module(slug).UNITS)
 assert len(units)==8
 for u in units:
  assert u['id'] not in seen
  assert all(p in seen for p in u['prerequisites']),(u['id'],'missing or forward prerequisite')
  seen.add(u['id'])
  assert len(u['questions'])==6 and len(u['tasks'])==2 and len(u['focus'])==6,u['id']
  assert len(u['lesson'])>=2 and len(u['vocabulary'])==6
  assert u['chart'] in MAP
  u['chart_id']=MAP[u['chart']]
  u['lesson_blocks']=blocks(u)
  assert u['chart_id'] is None or u['chart_id'] in chart_ids
  u['questions']=[prepare(q,u['id'],j) for j,q in enumerate(u['questions'])]
  for t in u['tasks']:
   assert t['model'] and len(t['criteria'])>=3
   m=re.search(r'(\d+)[–-](\d+) kata',t['prompt'])
   if m and 'tiga versi' not in t['prompt'].lower():
    lo,hi=map(int,m.groups());count=len(t['model'].split())
    assert lo<=count<=hi,(u['id'],'model length',count,lo,hi)
 levels.append({'id':slug,'name':name,'cefr':cefr,'tagline':tagline,'outcome':outcome,'entry':entry,'exit':exit_,'capstone':{'title':cap_title,'brief':cap_brief,'criteria':cap_criteria.split('|')},'units':units,'checkpoint':[prepare(q,slug.upper()+'-CHECK',j) for j,q in enumerate(CHECKPOINTS[idx])]})
counts={'levels':len(levels),'units':len(seen),'unit_questions':sum(len(u['questions']) for l in levels for u in l['units']),'checkpoint':sum(len(l['checkpoint']) for l in levels),'production':sum(len(u['tasks']) for l in levels for u in l['units']),'capstones':len(levels),'charts':len(charts)}
counts['objective']=counts['unit_questions']+counts['checkpoint']
data={'version':'1.0.0','date':'25 Oktober 2026','status':'editorial_preview','title':'English Hack · Kelas Bahasa Inggris Project Pintar','counts':counts,'levels':levels,'sections':SECTIONS,'charts':charts,'toolkits':TOOLKITS,'tracks':TRACKS,'sources':SOURCES}
(OUT/'curriculum.json').write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
css=(ROOT/'assets/css/english-hack.css').read_text();js=(ROOT/'assets/js/english-hack.js').read_text();fragment=(ROOT/'application/views/admin/english_hack/fragment.html').read_text()
def page(title,body,script=''):
 return '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'+h(title)+'</title><style>'+css+'</style></head><body class="eh-standalone">'+body+script+'</body></html>'
offline=copy.deepcopy(data);offline['downloads']={'book':'english-hack-coursebook-v1.html','json':'curriculum.json'}
encoded=json.dumps(offline,ensure_ascii=False).replace('<','\\u003c').replace('>','\\u003e').replace('&','\\u0026')
(OUT/'english-hack-studio-v1.html').write_text(page(data['title'],fragment,'<script type="application/json" id="eh-data">'+encoded+'</script><script>'+js+'</script>'))
# Static coursebook: complete readable content, including answers, without JavaScript.
b=['<main class="eh eh-book"><p class="eh-kicker">PROJECT PINTAR / ORIGINAL COURSEBOOK V1</p><h1>English,<br><em>unlocked.</em></h1><p>Kelas Bahasa Inggris · Fondasi sampai sasaran C2</p><p>56 unit inti · 364 soal objektif dengan pembahasan · 112 tugas produksi · 7 proyek akhir · 9 chart</p><p>25 Oktober 2026 · Versi editorial 1.0.0</p><div class="eh-callout"><h2>Cara memakai buku ini</h2><p>Baca konsep, jelaskan contoh, kerjakan latihan tanpa melihat kunci, lalu buat jawaban sendiri. Model jawaban adalah ilustrasi, bukan satu-satunya jawaban sah. Ulangi dengan konteks baru pada hari berbeda.</p><p>Nama level menunjukkan sasaran desain, bukan sertifikasi. Paket ini belum menggantikan materi anggota. Audio, teks panjang, penilaian produksi, dan kalibrasi asesmen perlu dilengkapi sebelum peluncuran penuh.</p><p class="eh-print-hide">Gunakan menu Cetak peramban untuk menyimpan PDF. Buku ini dapat dibaca tanpa koneksi.</p></div><h2>Daftar isi</h2><nav class="eh-book-toc">']
for l in levels:
 b.append('<p><a href="#level-'+l['id']+'"><strong>'+h(l['cefr']+' · '+l['name'])+'</strong></a></p>')
 b.extend('<a href="#'+u['id']+'">'+h(u['id']+' '+u['title'])+'</a>' for u in l['units'])
b.append('</nav><h2>Desain pembelajaran</h2>')
for s in SECTIONS:
 b.append('<section><h3>'+h(s['title'])+'</h3><p>'+h(s['intro'])+'</p>'+''.join('<h4>'+h(i['title'])+'</h4><p>'+h(i['body'])+'</p>' for i in s['items'])+'</section>')
def question(q,j):
 return '<div class="eh-question"><strong>'+str(j+1)+'. '+h(q['prompt'])+'</strong>'+(ul(q['options']) if q['type']=='choice' else '<p>Jawaban singkat: ____________________</p>')+'</div>'
def key(q,j):
 answer=q['options'][q['answer_index']] if q['type']=='choice' else ' / '.join(q['answers'])
 return '<p><strong>'+str(j+1)+'. '+h(answer)+'</strong> — '+h(q['explanation'])+'</p>'
for l in levels:
 b.append('<section class="eh-book-level" id="level-'+l['id']+'"><p class="eh-kicker">'+h(l['cefr'])+'</p><h2>'+h(l['name'])+'</h2><p>'+h(l['tagline'])+'</p><p>'+h(l['outcome'])+'</p><p><strong>Prasyarat:</strong> '+h(l['entry'])+'</p><p><strong>Bukti akhir:</strong> '+h(l['exit'])+'</p></section>')
 for u in l['units']:
  b.append('<article class="eh-book-unit" id="'+u['id']+'"><p class="eh-kicker">'+h(l['cefr']+' / '+u['id'])+'</p><h2>'+h(u['title'])+'</h2><p><strong>Tujuan:</strong> '+h(u['goal'])+'</p><p class="eh-small">Cakupan: '+h(' · '.join(u['focus']))+'</p><h3>Pahami konsepnya</h3>'+lesson_html(u))
  b.append('<div class="eh-patterns">'+''.join('<div class="eh-pattern"><strong>'+h(p['form'])+'</strong><em>'+h(p['example'])+'</em><span>'+h(p['meaning'])+'</span></div>' for p in u['patterns'])+'</div>')
  e=u['common_error'];b.append('<aside class="eh-error"><strong>Perangkap umum</strong><p class="eh-wrong">'+h(e['wrong'])+'</p><p class="eh-correct">'+h(e['correct'])+'</p><p>'+h(e['reason'])+'</p></aside>')
  b.append('<section class="eh-context"><p class="eh-label">Bahasa dalam konteks</p><blockquote lang="en">'+h(u['context'])+'</blockquote></section>')
  b.append('<div class="eh-vocab">'+''.join('<div><strong>'+h(v['term'])+'</strong><span>'+h(v['meaning'])+'</span></div>' for v in u['vocabulary'])+'</div><h3>Latihan mandiri</h3>')
  b.extend(question(q,j) for j,q in enumerate(u['questions']))
  b.append('<div class="eh-book-key"><h4>Kunci & pembahasan</h4>'+''.join(key(q,j) for j,q in enumerate(u['questions']))+'</div><h3>Berbicara & menulis</h3>')
  for t in u['tasks']:
   b.append('<section class="eh-production"><p class="eh-label">'+h(t['skill'])+'</p><p>'+h(t['prompt'])+'</p><strong>Kriteria</strong>'+ul(t['criteria'])+'<strong>Contoh jawaban</strong><p class="eh-model" lang="en">'+h(t['model'])+'</p></section>')
  b.append('<p class="eh-small">Transfer: ganti tokoh, waktu, atau kondisi; jelaskan pengaruhnya terhadap bahasa. Tinjau kembali pada hari berbeda.</p></article>')
 b.append('<section class="eh-book-unit"><h2>'+h(l['cefr'])+' · Checkpoint baru</h2><p>Empat soal ini sampel review, bukan ujian kelulusan lengkap.</p>'+''.join(question(q,j) for j,q in enumerate(l['checkpoint']))+'<div class="eh-book-key">'+''.join(key(q,j) for j,q in enumerate(l['checkpoint']))+'</div><h3>Proyek akhir · '+h(l['capstone']['title'])+'</h3><p>'+h(l['capstone']['brief'])+'</p>'+ul(l['capstone']['criteria'])+'</section>')
b.append('<section class="eh-book-level"><h2>Atlas rumus</h2>')
for c in charts:b.append('<figure class="eh-chart-card"><img style="width:100%" src="'+c['image']+'" alt="'+h(c['title']+'. '+c['description'])+'"><figcaption>'+h(c['note'])+'</figcaption></figure>')
for t in TOOLKITS:
 b.append('<section class="eh-toolkit"><h3>'+h(t['title'])+'</h3><table class="eh-table"><thead><tr>'+''.join('<th>'+h(x)+'</th>' for x in t['headers'])+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+h(c)+'</td>' for c in r)+'</tr>' for r in t['rows'])+'</tbody></table><p>'+h(t['note'])+'</p></section>')
b.append('</section><section class="eh-book-level"><h2>Jalur profesional · blueprint</h2>')
for t in TRACKS:b.append('<h3>'+h(t['name'])+'</h3><p>'+h(t['entry'])+'</p>'+ul(t['sequence'])+'<p><strong>Proyek:</strong> '+h(t['project'])+'</p><p><strong>Penilaian:</strong> '+h(t['assessment'])+'</p><p lang="en">'+h(t['sample'])+'</p>')
b.append('</section><section class="eh-book-level"><h2>Rujukan & atribusi</h2>')
for s in SOURCES:b.append('<h3><a href="'+h(s['url'])+'">'+h(s['name'])+'</a></h3><p>'+h(s['use'])+'</p><p class="eh-small">'+h(s['url'])+'</p>')
b.append('</section><footer class="eh-footer">Project Pintar · English Hack v1.0.0 · Materi dan soal orisinal. Semua data kasus ditulis sebagai latihan; bukan laporan operasional nyata.</footer></main>')
(OUT/'english-hack-coursebook-v1.html').write_text(page(data['title']+' · Buku lengkap',''.join(b)))
# Keep the downloaded studio self-contained, including its book and JSON downloads.
offline['downloads']={}
offline['offline_book']=base64.b64encode((OUT/'english-hack-coursebook-v1.html').read_bytes()).decode()
encoded=json.dumps(offline,ensure_ascii=False).replace('<','\\u003c').replace('>','\\u003e').replace('&','\\u0026')
(OUT/'english-hack-studio-v1.html').write_text(page(data['title'],fragment,'<script type="application/json" id="eh-data">'+encoded+'</script><script>'+js+'</script>'))
# Quantitative QA report is technical output, not an additional authored curriculum.
ids=[q['id'] for l in levels for u in l['units'] for q in u['questions']]+[q['id'] for l in levels for q in l['checkpoint']]
assert len(ids)==len(set(ids))==364
report={'counts':counts,'unique_unit_ids':len(seen),'unique_question_ids':len(ids),'checks':['prerequisites exist and precede dependent units','six questions and two production tasks per unit','all models meet explicit single-piece word ranges','unique options and valid answer indexes','all chart references resolve or are intentionally absent'],'text_words_approx':len(re.sub('<[^>]+>',' ',''.join(b)).split())}
(OUT/'validation.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(report,ensure_ascii=False,indent=2))
