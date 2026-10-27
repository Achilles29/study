(() => {
 'use strict';
 const root = document.getElementById('english-hack');
 const dataNode = document.getElementById('eh-data');
 if (!root || !dataNode) return;
 const data = JSON.parse(dataNode.textContent);
 const $ = id => document.getElementById(id);
 const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const allUnits = data.levels.flatMap(l => l.units.map(u => ({...u, level:l})));
 const charts = Object.fromEntries(data.charts.map(c => [c.id,c]));
 let activeUnit = allUnits[0], activeLevel = data.levels[0].id, results = {}, firstResults = {};
 const paras = a => a.map(p => `<p>${esc(p)}</p>`).join('');
 const lessonBlocks = u => u.lesson_blocks ? u.lesson_blocks.map((b,i)=>`<section class="eh-reading-block"><span class="eh-reading-number">${String(i+1).padStart(2,'0')}</span><div><h3>${esc(b.title)}</h3><p>${b.segments.map(s=>s.kind==='key'?`<strong class="eh-key">${esc(s.text)}</strong>`:s.kind==='example'?`<span class="eh-inline-example" lang="en">${esc(s.text)}</span>`:esc(s.text)).join('')}</p></div></section>`).join('') : paras(u.lesson);
 const bullets = a => `<ul>${a.map(t=>`<li>${esc(t)}</li>`).join('')}</ul>`;
 const section = (s,open=false) => `<details class="eh-details"${open?' open':''}><summary>${esc(s.title)}</summary><div class="eh-details-body"><p>${esc(s.intro)}</p>${s.items.map(i=>`<article><h4>${esc(i.title)}</h4><p>${esc(i.body)}</p></article>`).join('')}</div></details>`;
 const chartHTML = id => {const c=charts[id];return c?`<figure class="eh-inline-chart"><button type="button" data-chart="${esc(id)}" aria-label="Perbesar ${esc(c.title)}"><img src="${c.image}" alt="${esc(c.title+'. '+c.description)}" loading="lazy"></button><figcaption>${esc(c.note)} · Klik untuk memperbesar.</figcaption></figure>`:'';};
 function stopAudio(){if('speechSynthesis' in window) window.speechSynthesis.cancel();}
 function tab(name,focus=false){
  stopAudio();
  root.querySelectorAll('[data-tab]').forEach(b=>{const on=b.dataset.tab===name;b.setAttribute('aria-selected',String(on));b.tabIndex=on?0:-1;if(on&&focus)b.focus();});
  root.querySelectorAll('.eh-panel').forEach(p=>p.hidden=p.id!=='eh-'+name);
 }
 function questionHTML(q,index){
  const control = q.type==='choice' ? q.options.map((op,i)=>`<label class="eh-choice"><input type="radio" name="${esc(q.id)}" value="${i}"><span>${esc(op)}</span></label>`).join('') : `<label class="eh-small" for="answer-${esc(q.id)}">Jawaban singkat sesuai ruang kosong</label><input type="text" id="answer-${esc(q.id)}" name="${esc(q.id)}" autocomplete="off" spellcheck="false" aria-describedby="feedback-${esc(q.id)}">`;
  return `<fieldset class="eh-question" data-question="${esc(q.id)}"><legend>${index+1}. ${esc(q.prompt)}</legend>${control}<button type="button" class="eh-btn eh-check" data-check="${esc(q.id)}">Periksa jawaban</button><div id="feedback-${esc(q.id)}" class="eh-feedback" aria-live="polite" hidden></div></fieldset>`;
 }
 function levelCards(){
  $('eh-levels').innerHTML=data.levels.map((l,i)=>`<article class="eh-level-card"><div class="eh-level-number"><strong>${esc(l.cefr)}</strong><span>TAHAP 0${i+1}<br>8 unit inti</span></div><div class="eh-level-body"><p class="eh-label">${esc(l.tagline)}</p><h3>${esc(l.name)}</h3><p>${esc(l.outcome)}</p><p class="eh-small"><strong>Prasyarat:</strong> ${esc(l.entry)}</p><div class="eh-unit-pills">${l.units.map(u=>`<button class="eh-pill" type="button" data-unit="${u.id}">${u.id} · ${esc(u.title)}</button>`).join('')}</div><details class="eh-details"><summary>Proyek akhir · ${esc(l.capstone.title)}</summary><div class="eh-details-body"><p>${esc(l.capstone.brief)}</p>${bullets(l.capstone.criteria)}<p class="eh-small">${esc(l.exit)}</p></div></details><details class="eh-details"><summary>Checkpoint · 4 soal baru & pembahasan</summary><div class="eh-details-body"><p class="eh-small">Sampel review, bukan ujian kelulusan tervalidasi. Empat soal belum mencakup seluruh keterampilan.</p>${l.checkpoint.map((q,j)=>`<article><h4>${j+1}. ${esc(q.prompt)}</h4>${q.type==='choice'?bullets(q.options):'<p>Jawaban singkat.</p>'}<details><summary>Lihat kunci dan alasan</summary><p><strong>${esc(q.type==='choice'?q.options[q.answer_index]:q.answers.join(' / '))}</strong> · ${esc(q.explanation)}</p></details></article>`).join('')}</div></details><button class="eh-btn" type="button" data-unit="${l.units[0].id}">Buka unit pertama ↗</button></div></article>`).join('');
 }
 function unitList(){
  const search=$('eh-unit-search').value.trim().toLocaleLowerCase('id');
  const list=allUnits.filter(u=>search?`${u.id} ${u.title} ${u.goal} ${u.focus.join(' ')}`.toLocaleLowerCase('id').includes(search):u.level.id===activeLevel);
  $('eh-search-count').textContent=search?`${list.length} unit cocok di semua level`:`8 unit · ${data.levels.find(l=>l.id===activeLevel).cefr}`;
  $('eh-unit-list').innerHTML=list.length?list.map(u=>`<button type="button" data-unit="${u.id}" aria-current="${u.id===activeUnit.id}"><small>${u.level.cefr} / ${u.id}</small>${esc(u.title)}</button>`).join(''):'<p class="eh-small">Tidak ada unit yang cocok. Coba kata yang lebih singkat.</p>';
 }
 function showUnit(id,scroll=false){
  const u=allUnits.find(x=>x.id===id);if(!u)return;
  stopAudio();activeUnit=u;activeLevel=u.level.id;results={};firstResults={};$('eh-level-select').value=activeLevel;
  unitList();
  const idx=allUnits.indexOf(u);
  $('eh-lesson').innerHTML=`<p class="eh-label">${esc(u.level.cefr)} / UNIT ${u.id}</p><h2 tabindex="-1" id="eh-unit-heading">${esc(u.title)}</h2><p>${esc(u.goal)}</p><div class="eh-tags">${u.focus.map(f=>`<span class="eh-tag">${esc(f)}</span>`).join('')}</div><p class="eh-small">${u.prerequisites.length?'Prasyarat: '+u.prerequisites.map(p=>`<button type="button" class="eh-pill" data-unit="${p}">${p}</button>`).join(' '):'Titik awal · Tidak memerlukan penguasaan grammar sebelumnya.'}</p><p class="eh-label">01 / Pahami konsepnya</p><div class="eh-prose">${lessonBlocks(u)}</div><div class="eh-patterns">${u.patterns.map(p=>`<div class="eh-pattern"><span class="eh-pattern-label">POLA / RUMUS</span><strong>${esc(p.form)}</strong><div class="eh-pattern-example"><span class="eh-pattern-label">CONTOH</span><em>${esc(p.example)}</em></div><span class="eh-pattern-meaning"><b>Makna:</b> ${esc(p.meaning)}</span></div>`).join('')}</div>${chartHTML(u.chart_id)}<aside class="eh-error"><p class="eh-label">Perangkap yang sering terjadi</p><p class="eh-wrong">${esc(u.common_error.wrong)}</p><p class="eh-correct">${esc(u.common_error.correct)}</p><p>${esc(u.common_error.reason)}</p></aside><section class="eh-context"><p class="eh-label">02 / Bahasa di dalam konteks</p><blockquote lang="en">${esc(u.context)}</blockquote><div class="eh-actions"><button type="button" class="eh-btn" data-audio="play">Dengarkan naskah</button><button type="button" class="eh-btn" data-audio="stop">Hentikan</button></div><p class="eh-audio-status" id="eh-audio-status" aria-live="polite">Suara perangkat untuk pratinjau. Bukan rekaman guru atau tes listening.</p></section><div class="eh-vocab">${u.vocabulary.map(v=>`<div><strong lang="en">${esc(v.term)}</strong><span>${esc(v.meaning)}</span></div>`).join('')}</div><p class="eh-label">03 / Coba tanpa melihat rumus</p><h3>Enam tantangan, dengan alasan.</h3><p class="eh-small">Jawab lalu periksa. Perbaikan setelah membaca pembahasan dicatat terpisah dari percobaan pertama.</p><div id="eh-quiz">${u.questions.map(questionHTML).join('')}</div><div id="eh-quiz-summary" class="eh-quiz-summary" aria-live="polite">Belum ada jawaban diperiksa.</div><button type="button" class="eh-btn" data-reset>Mulai ulang latihan</button><div style="margin-top:36px"><p class="eh-label">04 / Jadikan kemampuanmu</p><h3>Sekarang, buat jawaban sendiri.</h3><p class="eh-small">Model hanya salah satu jawaban yang mungkin. Tugas terbuka perlu penilaian berdasarkan rubrik; tidak dinilai otomatis di sini.</p>${u.tasks.map((t,i)=>`<section class="eh-production"><p class="eh-label">${esc(t.skill)}</p><p>${esc(t.prompt)}</p><label class="eh-small" for="eh-task-${i}">${t.skill==='writing'?'Draf jawaban':'Catatan persiapan bicara'} · tidak disimpan</label><textarea id="eh-task-${i}" placeholder="Gunakan ide sendiri sebelum membuka model."></textarea><h4>Yang perlu terlihat</h4>${bullets(t.criteria)}<details><summary>Buka contoh dan bandingkan</summary><p class="eh-model" lang="en">${esc(t.model)}</p></details></section>`).join('')}</div><div class="eh-callout"><h4>Uji transfer kecil</h4><p>Tutup contoh. Ganti tokoh, waktu, atau satu kondisi. Jelaskan mengapa bentuk bahasanya ikut berubah atau tetap. Ulangi dengan konteks baru pada hari berbeda.</p></div><div class="eh-lesson-nav"><button type="button" class="eh-btn" ${idx?`data-unit="${allUnits[idx-1].id}"`:'disabled'}>← Sebelumnya</button><button type="button" class="eh-btn eh-btn-gold" ${idx<allUnits.length-1?`data-unit="${allUnits[idx+1].id}"`:'disabled'}>Berikutnya →</button></div>`;
  if(scroll){$('eh-unit-heading').focus({preventScroll:true});$('eh-lesson').scrollIntoView({block:'start',behavior:'instant'});}
 }
 const normalise=s=>s.normalize('NFKC').toLowerCase().replace(/[‘’]/g,"'").replace(/[“”]/g,'"').trim().replace(/[.!?]+$/,'').replace(/\s+/g,' ');
 function check(id){
  const q=activeUnit.questions.find(q=>q.id===id);if(!q)return;
  const field=root.querySelector(`[data-question="${id}"]`), fb=$('feedback-'+id);
  const input=q.type==='choice'?field.querySelector('input:checked'):field.querySelector('input[type=text]');
  if(!input||!input.value.trim()){fb.hidden=false;fb.className='eh-feedback';fb.textContent='Pilih atau tulis jawaban terlebih dahulu.';return;}
  const correct=q.type==='choice'?Number(input.value)===q.answer_index:q.answers.some(a=>normalise(a)===normalise(input.value));
  if(!(id in firstResults)) firstResults[id]=correct;
  results[id]=correct;
  const answer=q.type==='choice'?q.options[q.answer_index]:q.answers.join(' / ');
  fb.hidden=false;fb.className='eh-feedback '+(correct?'is-correct':'is-wrong');
  fb.innerHTML=`<strong>${correct?'Tepat.':'Mari perbaiki.'}</strong>${correct?'':`Jawaban yang diharapkan: <b>${esc(answer)}</b>. `}${esc(q.explanation)}${q.type==='short'&&!correct?'<p class="eh-small">Pemeriksaan ini membandingkan jawaban singkat yang disiapkan. Variasi lain yang masuk akal perlu ditinjau pengajar.</p>':''}`;
  const count=Object.keys(firstResults).length,first=Object.values(firstResults).filter(Boolean).length,current=Object.values(results).filter(Boolean).length;
  $('eh-quiz-summary').textContent=`${count}/6 soal diperiksa · ${first} benar pada percobaan pertama · ${current} benar setelah perbaikan. Ini latihan pratinjau, bukan keputusan kelulusan.`;
 }
 function speak(){
  const status=$('eh-audio-status');
  if(!('speechSynthesis' in window)){status.textContent='Peramban ini tidak menyediakan suara perangkat. Naskah tetap dapat dibaca.';return;}
  stopAudio();const utterance=new SpeechSynthesisUtterance(activeUnit.context);utterance.lang='en-GB';utterance.rate=.88;
  const voice=window.speechSynthesis.getVoices().find(v=>/^en[-_]/i.test(v.lang));if(voice)utterance.voice=voice;
  utterance.onstart=()=>{status.textContent='Membacakan dengan suara perangkat. Transkrip tetap terlihat.';};
  utterance.onend=()=>{status.textContent='Selesai. Suara perangkat bukan asesmen listening.';};
  utterance.onerror=()=>{if(document.contains(status))status.textContent='Suara dihentikan atau tidak tersedia. Gunakan naskah yang terlihat.';};
  window.speechSynthesis.speak(utterance);
 }
 $('eh-stats').innerHTML=[[data.counts.units,'unit materi inti'],[data.counts.objective,'soal objektif + pembahasan'],[data.counts.production,'tugas speaking & writing'],[data.counts.capstones,'proyek akhir level']].map(([n,t])=>`<div class="eh-stat"><strong>${n}</strong><span>${t}</span></div>`).join('');
 $('eh-overview-details').innerHTML=section(data.sections[0],true);
 $('eh-method-content').innerHTML=data.sections.slice(1).map(s=>section(s)).join('');
 $('eh-level-select').innerHTML=data.levels.map(l=>`<option value="${l.id}">${l.cefr} · ${esc(l.name)}</option>`).join('');
 levelCards();showUnit(activeUnit.id);
 $('eh-chart-grid').innerHTML=data.charts.map(c=>`<article class="eh-chart-card"><button type="button" data-chart="${c.id}" aria-label="Perbesar ${esc(c.title)}"><img src="${c.image}" alt="${esc(c.title+'. '+c.description)}" loading="lazy"></button><div class="eh-chart-caption"><h3>${esc(c.title)}</h3><p>${esc(c.note)}</p></div></article>`).join('');
 $('eh-toolkit').innerHTML=data.toolkits.map(t=>`<section class="eh-toolkit"><h3>${esc(t.title)}</h3><div class="eh-scroll" tabindex="0" role="region" aria-label="${esc(t.title)}"><table class="eh-table"><thead><tr>${t.headers.map(h=>`<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${t.rows.map(r=>`<tr>${r.map(c=>`<td>${esc(c)}</td>`).join('')}</tr>`).join('')}</tbody></table></div><p class="eh-small">${esc(t.note)}</p></section>`).join('');
 $('eh-track-grid').innerHTML=data.tracks.map((t,i)=>`<article class="eh-card"><span class="eh-number">TRACK 0${i+1}</span><h3>${esc(t.name)}</h3><p class="eh-small">${esc(t.entry)}</p>${bullets(t.sequence)}<h4>Proyek penerapan</h4><p>${esc(t.project)}</p><h4>Bukti yang dinilai</h4><p>${esc(t.assessment)}</p><p class="eh-model" lang="en">${esc(t.sample)}</p></article>`).join('');
 $('eh-source-list').innerHTML=data.sources.map(s=>`<article class="eh-source"><h3><a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">${esc(s.name)} ↗</a></h3><p>${esc(s.use)}</p></article>`).join('');
 const dl={...(data.downloads||{})};
 if(data.offline_book){
  const bytes=Uint8Array.from(atob(data.offline_book),c=>c.charCodeAt(0));
  dl.book=URL.createObjectURL(new Blob([bytes],{type:'text/html;charset=utf-8'}));
  const exportData={...data};delete exportData.offline_book;delete exportData.downloads;
  dl.json=URL.createObjectURL(new Blob([JSON.stringify(exportData,null,2)],{type:'application/json;charset=utf-8'}));
 }
 $('eh-downloads').innerHTML=`<h3>Bawa seluruh naskah.</h3><p>Buku lengkap memuat semua unit, soal, kunci, model jawaban, atlas, rubrik, dan rujukan. HTML dapat dibuka tanpa koneksi dan dicetak ke PDF dari peramban.</p><div class="eh-actions">${dl.pdf?`<a class="eh-btn eh-btn-gold" href="${esc(dl.pdf)}">Unduh buku lengkap · PDF</a>`:''}${dl.book?`<a class="eh-btn" href="${esc(dl.book)}">Unduh buku lengkap · HTML</a>`:''}${dl.studio?`<a class="eh-btn" href="${esc(dl.studio)}">Unduh studio offline · HTML</a>`:''}${dl.json?`<a class="eh-btn" href="${esc(dl.json)}">Unduh konten terstruktur · JSON</a>`:''}</div><p class="eh-small">Versi ${esc(data.version)} · ${data.counts.units} unit, ${data.counts.objective} soal objektif termasuk ${data.counts.checkpoint} checkpoint, ${data.counts.production} tugas produksi, dan ${data.counts.capstones} proyek. Materi level C1–C2 memerlukan pengayaan teks panjang dan interaksi sebelum digunakan sebagai program lengkap.</p>`;
 $('eh-version').textContent=data.date;
 if(data.offline_book){
  $('eh-downloads').querySelectorAll('a').forEach(a=>{a.download=a.href===dl.book?'english-hack-coursebook-v1.html':'curriculum.json';});
 }
 root.addEventListener('click',e=>{
  const b=e.target.closest('button');if(!b)return;
  if(b.dataset.tab){tab(b.dataset.tab);return;}
  if(b.dataset.go){tab(b.dataset.go,true);$('eh-'+b.dataset.go).scrollIntoView({block:'start'});return;}
  if(b.dataset.unit){tab('studio');showUnit(b.dataset.unit,true);return;}
  if(b.dataset.check){check(b.dataset.check);return;}
  if(b.hasAttribute('data-reset')){showUnit(activeUnit.id);$('eh-quiz').scrollIntoView({block:'start'});return;}
  if(b.dataset.audio==='play'){speak();return;}
  if(b.dataset.audio==='stop'){stopAudio();$('eh-audio-status').textContent='Suara dihentikan.';return;}
  if(b.dataset.chart){const c=charts[b.dataset.chart];$('eh-chart-large').src=c.image;$('eh-chart-large').alt=c.title+'. '+c.description;$('eh-chart-note').textContent=c.note;$('eh-chart-dialog').showModal();}
 });
 root.querySelector('.eh-tabs').addEventListener('keydown',e=>{
  const buttons=[...root.querySelectorAll('[data-tab]')],i=buttons.indexOf(e.target);if(i<0)return;
  const next=e.key==='ArrowRight'?(i+1)%buttons.length:e.key==='ArrowLeft'?(i+buttons.length-1)%buttons.length:e.key==='Home'?0:e.key==='End'?buttons.length-1:-1;
  if(next>=0){e.preventDefault();tab(buttons[next].dataset.tab,true);}
 });
 $('eh-level-select').addEventListener('change',e=>{$('eh-unit-search').value='';activeLevel=e.target.value;showUnit(data.levels.find(l=>l.id===activeLevel).units[0].id);});
 $('eh-unit-search').addEventListener('input',unitList);
 window.addEventListener('pagehide',stopAudio);
})();
