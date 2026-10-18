import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',slug='matematika-kelas-1-mengenal-bilangan-1-20';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const cid=sql(`SELECT id FROM st_courses WHERE slug='${slug}'`);assert.match(cid,/^\d+$/);
const status=()=>sql(`SELECT status FROM st_courses WHERE id=${cid}`),prefix='qa047_'+Date.now(),pw='Content-QA-Only-2026!';
const hash=execFileSync('/www/server/php/84/bin/php',['-r','echo password_hash("Content-QA-Only-2026!", PASSWORD_DEFAULT);'],{encoding:'utf8'});
const ids={};for(const kind of ['parent','child','editor']){sql(`INSERT INTO st_users(username,email,password_hash,display_name,account_kind,learning_stage,status,email_verified_at,created_at,updated_at) VALUES('${prefix}_${kind}',${kind==='child'?'NULL':`'${prefix}_${kind}@example.invalid'`},'${hash}','QA Materi ${kind}','${kind==='editor'?'adult':kind}','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())`);ids[kind]=sql(`SELECT id FROM st_users WHERE username='${prefix}_${kind}'`);}
sql(`INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) VALUES(${ids.parent},${ids.child},'active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); INSERT INTO st_user_roles(user_id,role_id,created_at) SELECT ${ids.editor},id,UTC_TIMESTAMP() FROM st_roles WHERE role_code='content_editor';`);
function client(){const jar=new Map();return async(p,data)=>{const r=await fetch(base+p,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),p);return {status:r.status,body,location:r.headers.get('location'),headers:r.headers};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(name,password=pw){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password,study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const admin=await login('monitor1','Monitor-QA-Only-2026!'),parent=await login(prefix+'_parent'),child=await login(prefix+'_child'),editor=await login(prefix+'_editor'),guest=client();
async function post(c,path,data,getPath=path){const r=await c(getPath);assert.equal(r.status,200,getPath);return c(path,{...data,study_csrf_token:field(r.body,'study_csrf_token')});}
const lessons=sql(`SELECT l.id,l.slug FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${cid} ORDER BY l.sort_order`).split('\n').map(l=>l.split('\t'));assert.equal(lessons.length,6);
const expected=['3','5','8','7','enam belas','12','17','2 puluhan dan 0 satuan','Bima','sama banyak','18','11, 14, 16'];
assert.deepEqual(sql(`SELECT o.option_text FROM st_questions q JOIN st_question_options o ON o.question_id=q.id WHERE q.course_id=${cid} AND o.is_correct=1 ORDER BY q.id`).split('\n'),expected);
assert.equal(sql(`SELECT COUNT(*) FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id JOIN st_lessons l ON l.id=d.lesson_id JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${cid}`),'18');
if(status()==='draft'){
 for(const [id,ls] of lessons){assert.equal((await guest('/materi/'+ls)).status,404);assert.equal((await child('/materi/'+ls)).status,404);assert.equal((await child(`/kelola/kursus/${cid}/pelajaran/${id}/pratinjau`)).status,403);const r=await admin(`/kelola/kursus/${cid}/pelajaran/${id}/gambar/pratinjau`);assert.equal(r.status,200);assert.match(r.headers.get('content-type'),/image\/png/);assert.match(r.headers.get('cache-control'),/no-store/);}
 assert.equal((await child('/latihan/kursus/'+cid)).status,404);
 assert.equal((await parent('/kelola/kursus/'+cid)).status,403);
 console.log('PASS draft visibility, private diagrams, editor/learner isolation, exact answer key/card counts');
}
if(process.env.STUDY_MATH_HTTP_ONLY!=='1'){
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.goto(base+'/masuk');await p.type('[name=identity]','monitor1');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){
  await p.setViewport({width,height:900});
  for(const path of [`/kelola/kursus/${cid}`,`/kelola/kursus/${cid}/soal`,'/kelola/paket/3',...lessons.map(([id])=>`/kelola/kursus/${cid}/pelajaran/${id}/pratinjau`)]){
   assert.equal((await p.goto(base+path,{waitUntil:'networkidle0'})).status(),200,path);
   assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`overflow ${width} ${path}`);
   await p.evaluate(()=>{for(const i of document.images)i.loading='eager';});
   await p.waitForFunction(()=>[...document.images].every(i=>i.complete && i.naturalWidth>0));
  }
  await p.screenshot({path:`/tmp/study-math047-preview-${width}.png`,fullPage:true});
 }
 assert.deepEqual(errors,[]);
 console.log('PASS admin course/questions/package and all six previews, desktop1280/mobile360, images, no JS errors');
}finally{await browser.close();}
}
if(status()==='draft'){assert.ok([302,303].includes((await post(admin,`/kelola/kursus/${cid}/ajukan`,{},`/kelola/kursus/${cid}`)).status));assert.equal(status(),'review');}
if(status()==='review'){assert.ok([302,303].includes((await post(editor,`/kelola/kursus/${cid}/tinjau`,{decision:'approved',notes:'QA only: original content, answer key, diagrams and age appropriateness reviewed.'},`/kelola/kursus/${cid}`)).status));assert.equal(status(),'published');}
// Manual QA-only access: no sale, charge, package publication, or price approval.
sql(`INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,status,enrolled_at,updated_at) VALUES(${ids.child},${cid},'manual','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
assert.equal((await guest('/materi/'+lessons[0][1])).status,200);
const locked=await guest('/materi/'+lessons[1][1]);assert.ok([302,303,307].includes(locked.status));assert.equal(new URL(locked.location).pathname,'/kursus/'+slug);
assert.equal((await guest('/materi/'+lessons[1][1]+'/gambar')).status,403);
for(const [id,ls] of lessons){assert.equal((await post(child,'/materi/'+ls+'/selesai',{},'/materi/'+ls)).status,303);}
assert.equal(sql(`SELECT COUNT(*) FROM st_lesson_progress p JOIN st_lessons l ON l.id=p.lesson_id JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${cid} AND p.learner_user_id=${ids.child} AND p.status='completed'`),'6');
const cards=sql(`SELECT f.id,l.slug FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id JOIN st_lessons l ON l.id=d.lesson_id JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${cid}`).split('\n').map(l=>l.split('\t'));
for(const [id,ls] of cards){assert.equal((await post(child,`/materi/${ls}/flashcard/${id}`,{status:'known'},'/materi/'+ls)).status,303);}
assert.equal(sql(`SELECT COUNT(*) FROM st_flashcard_progress WHERE learner_user_id=${ids.child} AND status='known'`),'18');
const startPage=await child('/latihan/kursus/'+cid),start=await child('/latihan/kursus/'+cid,{start_token:field(startPage.body,'start_token'),study_csrf_token:field(startPage.body,'study_csrf_token')});assert.equal(start.status,303);const attemptPath=new URL(start.location).pathname,aid=attemptPath.split('/').pop();
const answerRows=sql(`SELECT q.id,o.id,o.is_correct FROM st_questions q JOIN st_question_options o ON o.question_id=q.id WHERE q.course_id=${cid} ORDER BY q.id,o.sort_order`).split('\n').map(l=>l.split('\t'));
const qids=[...new Set(answerRows.map(r=>r[0]))],answers={};for(const [i,id] of qids.entries()){answers[`answers[${id}]`]=answerRows.find(r=>r[0]===id && r[2]===(i<10?'1':'0'))[1];}
let r=await child(attemptPath);assert.ok(!r.body.includes('Mengubah jarak atau susunan tidak mengubah banyaknya benda.'));
assert.equal((await child(attemptPath,{...answers,action:'save',revision:field(r.body,'revision'),study_csrf_token:field(r.body,'study_csrf_token')})).status,303);
r=await child(attemptPath);assert.equal((r.body.match(/value="\d+" checked/g)||[]).length,12);
assert.equal((await child(attemptPath,{...answers,action:'finish',revision:field(r.body,'revision'),study_csrf_token:field(r.body,'study_csrf_token')})).status,303);
assert.equal(sql(`SELECT CONCAT(status,':',correct_count,':',question_count,':',score) FROM st_practice_attempts WHERE id=${aid}`),'graded:10:12:83.33');
r=await child(attemptPath);assert.ok(r.body.includes('10 dari 12'));assert.ok(r.body.includes('Mengubah jarak atau susunan tidak mengubah banyaknya benda.'));
assert.equal((await parent(`/keluarga/anak/${ids.child}/latihan/${aid}`)).status,200);
assert.equal((await parent(`/keluarga/anak/${ids.child}/rapor`)).status,200);
assert.equal((await admin('/kelola/rapor/'+ids.child)).status,200);
assert.equal((await admin('/kelola/pembelajaran/pengguna/'+ids.child)).status,200);
assert.equal((await editor(attemptPath)).status,404);
assert.equal(sql('SELECT status FROM st_packages WHERE id=3'),'draft');
console.log(`PASS QA publish/review, preview boundary, six completions, 18 mastered cards, save/resume twelve answers, 10/12=83.33%, explanations, parent report/admin monitor, attempt isolation; course=${cid}, child=${ids.child}, attempt=${aid}. No production writes.`);
