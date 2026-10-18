// Isolated QA milestone flow. Never connects to production or changes a paid package.
import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(body,n)=>{const m=body.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(name){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const parent=await login('monitor2'),other=await login('monitor1');
const cid=Number(sql("SELECT id FROM st_courses WHERE slug='matematika-kelas-6-pecahan-geometri-data-dan-peluang'"));assert.equal(cid,64);
const [lid,slug]=sql(`SELECT l.id,l.slug FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${cid} ORDER BY s.sort_order,l.sort_order LIMIT 1`).split('\t');
assert.equal(sql(`SELECT COUNT(*) FROM st_course_enrollments WHERE learner_user_id=2 AND course_id=${cid}`),'0','Requires fresh QA fixture; do not delete existing progress to rerun');
assert.ok([302,303,307].includes((await parent('/materi/'+slug)).status));
sql(`INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,status,enrolled_at,updated_at) VALUES(2,${cid},'manual','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
let r=await parent('/materi/'+slug);assert.equal(r.status,200);assert.ok(r.body.includes('<h2>Tujuan belajar</h2>'));
assert.equal((await parent('/materi/'+slug+'/selesai',{})).status,403);
r=await parent('/materi/'+slug);assert.equal((await parent('/materi/'+slug+'/selesai',{study_csrf_token:field(r.body,'study_csrf_token')})).status,303);
assert.equal(sql(`SELECT status FROM st_lesson_progress WHERE learner_user_id=2 AND lesson_id=${lid}`),'completed');
const card=sql(`SELECT f.id FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id WHERE d.lesson_id=${lid} ORDER BY f.id LIMIT 1`);assert.match(card,/^\d+$/);
r=await parent('/materi/'+slug);assert.equal((await parent('/materi/'+slug+'/flashcard/'+card,{study_csrf_token:field(r.body,'study_csrf_token'),status:'known'})).status,303);
assert.equal(sql(`SELECT status FROM st_flashcard_progress WHERE learner_user_id=2 AND flashcard_id=${card}`),'known');
r=await parent('/latihan/kursus/'+cid);const start={study_csrf_token:field(r.body,'study_csrf_token'),start_token:field(r.body,'start_token')};
assert.equal((await parent('/latihan/kursus/'+cid,{start_token:start.start_token})).status,403);
r=await parent('/latihan/kursus/'+cid);const started=await parent('/latihan/kursus/'+cid,{study_csrf_token:field(r.body,'study_csrf_token'),start_token:field(r.body,'start_token')});assert.equal(started.status,303);
const attempt=started.location.match(/\/latihan\/percobaan\/(\d+)/)[1],path='/latihan/percobaan/'+attempt;
assert.equal((await other(path)).status,404);r=await parent(path);assert.equal(r.status,200);assert.ok(!r.body.includes('Jawaban benar'));
const answers={};for(const row of sql(`SELECT q.id,o.id FROM st_questions q JOIN st_question_options o ON o.question_id=q.id AND o.is_correct=1 WHERE q.course_id=${cid} AND q.status='published' ORDER BY q.id`).split('\n')){const [q,o]=row.split('\t');answers['answers['+q+']']=o;}assert.equal(Object.keys(answers).length,24);
assert.equal((await parent(path,{...answers,revision:field(r.body,'revision'),action:'save',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);
r=await parent(path);assert.equal(field(r.body,'revision'),'1');assert.equal((await parent(path,{...answers,revision:'1',action:'finish',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);
assert.equal(sql(`SELECT CONCAT(status,':',correct_count,':',question_count,':',score) FROM st_practice_attempts WHERE id=${attempt} AND learner_user_id=2`),'graded:24:24:100.00');
r=await parent(path);assert.ok(r.body.includes('Jawaban benar'));assert.ok(r.body.includes('Terima kasih sudah berlatih'));
assert.ok((await parent('/latihan')).body.includes('Matematika Kelas 6'));
const report=await other('/kelola/rapor/2?kursus='+cid);assert.equal(report.status,200);assert.ok(report.body.includes('Matematika Kelas 6'));assert.match(report.body,/<\/th><td>1 \/ 1<\/td><td>1 \/ 1<\/td><td>100\.00%<\/td><td>1 \/ 1<\/td>/);assert.ok(report.body.includes('bukan seluruh isi kurikulum'));
assert.equal((await parent('/kelola/rapor/2')).status,403);
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor2');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);for(const width of [1280,768,360]){await p.setViewport({width,height:900});for(const [i,url]of ['/materi/'+slug,path].entries()){assert.equal((await p.goto(base+url)).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));if(width===360)await p.screenshot({path:`/tmp/study-curriculum062-learner-${i}.png`,fullPage:true});}}assert.deepEqual(errors,[]);}finally{await browser.close();}
console.log('PASS QA only: paid gate -> manual entitlement -> lesson completion/CSRF -> flashcard -> 24-question save/finish/100% -> history/report/owner isolation -> desktop/tablet/mobile. Attempt '+attempt);
