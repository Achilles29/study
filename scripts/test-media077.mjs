import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',route='/kelola/bank-soal/impor',nonce=Date.now();
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{
 const multi=data instanceof FormData,r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{'User-Agent':'ProjectPintar-QA/media077',Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data&&!multi?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?(multi?data:new URLSearchParams(data)):undefined});
 for(const c of r.headers.getSetCookie()){const s=c.split(';')[0],i=s.indexOf('=');jar.set(s.slice(0,i),s.slice(i+1));}
 const bytes=Buffer.from(await r.arrayBuffer()),body=bytes.toString();
 assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body),path);
 return{status:r.status,body,bytes,location:r.headers.get('location')};
};}
const field=(body,name)=>{const m=body.match(new RegExp(`name="${name}" value="([^"]*)"`));assert.ok(m,name);return m[1];};
async function login(name){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
const root=await login('monitor1'),parent=await login('monitor2'),child=await login('monitor4'),outsider=await login('monitor11'),guest=client();
// Finish only this test's own interrupted synthetic attempt, through normal HTTP.
const pending=sql("SELECT a.id FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id=a.session_id WHERE a.learner_user_id=4 AND a.status='in_progress' AND s.title LIKE 'Simulasi Ujian · QA077 images %';");
if(pending){const p=await child('/latihan/percobaan/'+Number(pending));assert.equal((await child('/latihan/percobaan/'+Number(pending),{action:'finish',revision:field(p.body,'revision'),study_csrf_token:field(p.body,'study_csrf_token')})).status,303);}
function png(i){return execFileSync('/www/server/php/84/bin/php',['-r',`$im=imagecreatetruecolor(360,100);imagefill($im,0,0,imagecolorallocate($im,240,240,255));$c=imagecolorallocate($im,${i*23},20,100);imagestring($im,5,25,40,"QA077 image ${i}",$c);imagepng($im);`]);}
const files=Array.from({length:6},(_,i)=>({name:`image${i+1}.png`,bytes:png(i+1)}));
const text=`2 Soal Pilihan Ganda\n6. QA077-${nonce} Perhatikan tabel berikut.\n[GAMBAR: image1.png]\nNilai dari [GAMBAR: image2.png] adalah …\na. 9\nb. 8\nc. 7\nd. 6\n7. QA077-${nonce} Pilih gambar yang benar <script>alert(1)</script>.\na. [GAMBAR: image3.png]\nb. [GAMBAR: image4.png] dan [GAMBAR: image5.png]\nc. Teks dan [GAMBAR: image5.png]\nd. [GAMBAR: image6.png]\nKUNCI JAWABAN\n6.a 7.b\n`;
const defaults={mapel:'matematika',kelas:'sd_1',tingkat:'basic',rights_status:'owned',source_note:'Synthetic QA077 own drawings'};
async function upload(source,attachments=files,target=route){
 const p=await root(target),form=new FormData();
 for(const [k,v]of Object.entries({...defaults,study_csrf_token:field(p.body,'study_csrf_token'),images_expected:String(attachments.length)}))form.set(k,v);
 form.set('document',new Blob([source]),'media.txt');for(const f of attachments)form.append('images[]',new Blob([f.bytes],{type:'image/png'}),f.name);
 return root(target,form);
}
const before=sql('SELECT COUNT(*) FROM st_bank_imports;');
let r=await upload(text.replace('image2.png','missing.png'));assert.equal(r.status,422);assert.ok(r.body.includes('missing.png'));assert.equal(sql('SELECT COUNT(*) FROM st_bank_imports;'),before);
r=await upload(text.replace('[GAMBAR: image2.png]','[GAMBAR: image2.png'));assert.equal(r.status,422);assert.equal(sql('SELECT COUNT(*) FROM st_bank_imports;'),before);
assert.equal((await parent(route)).status,403);
r=await upload(text,[...files].reverse());assert.equal(r.status,303);
const preview=new URL(r.location).pathname;
let page=await root(preview);assert.equal((page.body.match(/class="question-inline-image"/g)||[]).length,7);assert.ok(!page.body.includes('<script>alert(1)</script>'));
assert.equal((page.body.match(/<figure /g)||[]).length,0);
assert.equal((await parent(preview+'/gambar/0/1')).status,403);
assert.ok([303,307].includes((await guest(preview+'/gambar/0/1')).status));
assert.equal((await root(preview,{confirm:'1',publish:'1',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
const token=preview.split('/').at(-1),ids=JSON.parse(sql(`SELECT JSON_EXTRACT(result_json,'$.question_ids') FROM st_bank_imports WHERE token='${token}';`));
assert.equal(ids.length,2);
const examValues={title:'QA077 images '+nonce,grade_id:'3',duration_minutes:'30',max_daily:'5',credit_price:'0',status:'draft',question_shuffle:'1',option_shuffle:'1',revision:'0',action:'save'};
page=await root('/kelola/simulasi-ujian/0');r=await root('/kelola/simulasi-ujian/0',{...examValues,study_csrf_token:field(page.body,'study_csrf_token')});assert.equal(r.status,303);
const examPath=new URL(r.location).pathname,eid=examPath.split('/').at(-1);
const renamed=files.map(f=>({...f,name:'renamed-'+f.name}));let renamedText=text;for(const f of files)renamedText=renamedText.replaceAll(f.name,'renamed-'+f.name);
r=await upload(renamedText,renamed,route+'?ujian='+eid);assert.equal(r.status,303);const preview2=new URL(r.location).pathname;
page=await root(preview2);assert.equal((await root(preview2,{confirm:'1',publish:'1',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
assert.deepEqual(sql(`SELECT question_id FROM st_exam_questions WHERE exam_id=${Number(eid)} ORDER BY question_id;`).split('\n').map(Number),[...ids].sort((a,b)=>a-b));
page=await root(examPath);assert.equal((await root(examPath,{...examValues,status:'published',revision:field(page.body,'revision'),study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
page=await child('/latihan/ujian/'+eid);r=await child('/latihan/ujian/'+eid,{start_token:field(page.body,'start_token'),study_csrf_token:field(page.body,'study_csrf_token')});assert.equal(r.status,303);
const attempt=new URL(r.location).pathname,aid=Number(attempt.split('/').at(-1));
page=await child(attempt);assert.equal((page.body.match(/class="question-inline-image"/g)||[]).length,7);assert.ok(!page.body.includes('Jawaban benar'));assert.ok(!page.body.includes('storage_key'));assert.ok(!page.body.includes('image4.png'));
assert.equal((await outsider(attempt+`/gambar/${ids[1]}/1`)).status,404);
assert.equal((await parent(attempt+`/gambar/${ids[1]}/1`)).status,404);
assert.equal((await root(attempt+`/gambar/${ids[1]}/1`)).status,404,'Admin cannot inspect active child answers via media');
assert.equal((await child(attempt+`/gambar/${ids[1]}/99`)).status,404);
assert.ok([303,307].includes((await guest(attempt+`/gambar/${ids[1]}/1`)).status));
const historical=(await child(attempt+`/gambar/${ids[1]}/1`)).bytes;
// Browser checks real private images, inline order, selected options and mobile fit.
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('dialog',d=>d.accept());
 async function bl(name){await p.deleteCookie(...await p.cookies());await p.goto(base+'/masuk');await p.type('[name=identity]',name);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);}
 await bl('monitor1');
 for(const width of [1280,360]){
  await p.setViewport({width,height:950});await p.goto(base+preview,{waitUntil:'networkidle0'});
  await p.$$eval('img.question-inline-image',imgs=>imgs.forEach(i=>i.loading='eager'));
  await p.waitForFunction(()=>[...document.querySelectorAll('.question-inline-image')].every(i=>i.complete&&i.naturalWidth>0));
  assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await p.$eval('.question-rich-text',el=>el.scrollIntoView({behavior:'instant',block:'center'}));await p.screenshot({path:`/tmp/media077-preview-${width}.png`});
  await p.goto(base+route);
  const guide=await p.$('#import-image-guide > summary');await guide.focus();await p.keyboard.press('Enter');
  const summary=await p.evaluateHandle(()=>[...document.querySelectorAll('summary')].find(e=>e.textContent.includes('gambar langsung pada pilihan')));
  await summary.asElement().focus();await p.keyboard.press('Enter');assert.ok(await summary.asElement().evaluate(e=>e.parentElement.open));
 }
 await bl('monitor4');
 for(const width of [1280,360]){
  await p.setViewport({width,height:950});await p.goto(base+attempt,{waitUntil:'networkidle0'});
  await p.$$eval('img.question-inline-image',imgs=>imgs.forEach(i=>i.loading='eager'));
  await p.waitForFunction(()=>[...document.querySelectorAll('.question-inline-image')].every(i=>i.complete&&i.naturalWidth>0));
  assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  assert.equal(await p.$$eval('.practice-option .question-inline-image',imgs=>imgs.length),5);
  const choice=await p.$(`[data-question-id="${ids[1]}"] label.practice-option`);await choice.click();assert.ok(await choice.$eval('input',i=>i.checked));
  await choice.$eval('input',i=>i.focus());await p.keyboard.press('ArrowDown');
  assert.equal(await p.$$eval(`[data-question-id="${ids[1]}"] input:checked`,els=>els.length),1);
  await choice.evaluate(e=>e.scrollIntoView({behavior:'instant',block:'center'}));await p.screenshot({path:`/tmp/media077-attempt-${width}.png`});
 }
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
// The editor preserves filenames in input, adds/replaces multiple files by name,
// and rejects removing an image still referenced by an option.
const qid=ids[1],stored=JSON.parse(sql(`SELECT JSON_OBJECT('prompt',q.prompt,'options',(SELECT JSON_ARRAYAGG(option_text ORDER BY sort_order) FROM st_question_options WHERE question_id=q.id)) FROM st_questions q WHERE q.id=${qid};`));
const edit={...defaults,soal:stored.prompt,a:stored.options[0],b:stored.options[1],c:stored.options[2],d:stored.options[3],kunci:'B',bahasan:'',status:'published',confirm:'1'};
page=await root('/kelola/bank-soal/'+qid);assert.ok(page.body.includes('[GAMBAR: image4.png]'));
let form=new FormData();for(const [k,v]of Object.entries({...edit,revision:field(page.body,'revision'),study_csrf_token:field(page.body,'study_csrf_token'),images_expected:'2'}))form.set(k,v);
form.append('images[]',new Blob([png(8)]),'image4.png');form.append('images[]',new Blob([png(9)]),'extra.png');
assert.equal((await root('/kelola/bank-soal/'+qid,form)).status,303);
assert.notDeepEqual((await root(`/kelola/bank-soal/${qid}/gambar/1`)).bytes,historical);
assert.deepEqual((await child(attempt+`/gambar/${qid}/1`)).bytes,historical,'Existing snapshot retains old image');
page=await root('/kelola/bank-soal/'+qid);const revision=field(page.body,'revision');
assert.equal((await root('/kelola/bank-soal/'+qid,{...edit,remove_image:'1',revision,study_csrf_token:field(page.body,'study_csrf_token')})).status,400);
assert.equal(sql(`SELECT content_version FROM st_questions WHERE id=${qid};`),revision);
// Grade uses snapshotted IDs, never image order or names.
page=await child(attempt);const answers={action:'finish',revision:field(page.body,'revision'),study_csrf_token:field(page.body,'study_csrf_token')};
const snapshots=sql(`SELECT question_id,snapshot_json FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=${aid});`).split('\n').map(s=>{const tab=s.indexOf('\t');return [Number(s.slice(0,tab)),JSON.parse(s.slice(tab+1))];});
for(const [id,snapshot]of snapshots)answers[`answers[${id}]`]=String(snapshot.options.find(o=>Number(o.is_correct)===1).id);
assert.equal((await child(attempt,answers)).status,303);assert.equal(sql(`SELECT score FROM st_practice_attempts WHERE id=${aid};`),'100.00');
page=await child(attempt+'/pembahasan');assert.equal((page.body.match(/class="question-inline-image"/g)||[]).length,7);assert.ok(page.body.includes('Jawaban benar'));
assert.equal((await parent(attempt+`/gambar/${qid}/1`)).status,404,'Unrelated parent still denied after completion');
assert.equal((await root(attempt+`/gambar/${qid}/1`)).status,200,'Authorized audited admin historical result image');
console.log('PASS077: multipart/preview/commit, bad markers/no write, private child images, rename dedup/exam links, shuffle/click/keyboard, multi-image edit/replacement, old snapshot and 100% grading, desktop/mobile and real image loading.');
