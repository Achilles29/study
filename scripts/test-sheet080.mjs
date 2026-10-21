import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097', nonce=Date.now();
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();const c=async(path,data)=>{
 const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{'User-Agent':'ProjectPintar-QA/sheet080',Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});
 for(const x of r.headers.getSetCookie()){const s=x.split(';')[0],i=s.indexOf('=');jar.set(s.slice(0,i),s.slice(i+1));}
 const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body),body.slice(0,1200));
 return{status:r.status,body,location:r.headers.get('location')};
};c.jar=jar;return c;}
const field=(body,name)=>{const m=body.match(new RegExp(`name="${name}" value="([^"]*)"`));assert.ok(m,name);return m[1];};
async function login(name){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
const admin=await login('monitor1'),child=await login('monitor4'),other=await login('monitor11');
async function post(c,path,data){const page=await c(path);return c(path,{study_csrf_token:field(page.body,'study_csrf_token'),...data});}
async function save(c,path,data={}){const page=await c(path);return c(path,{action:'save',sheet_ajax:'1',revision:field(page.body,'revision'),study_csrf_token:field(page.body,'study_csrf_token'),...data});}
function decoded(r){assert.equal(r.status,200,r.body);const d=JSON.parse(r.body);assert.equal(d.ok,true);return d;}
function aid(path){return Number(path.split('/').at(-1));}
async function finish(path){return save(child,path,{action:'finish'});}
// Finish earlier QA-only active attempts through their owner API.
for(const id of sql("SELECT id FROM st_practice_attempts WHERE learner_user_id=4 AND status='in_progress'").split('\n').filter(Boolean)) await finish('/latihan/percobaan/'+id);
if (!process.env.SHEET_BROWSER_ONLY) {
sql("INSERT INTO st_drill_daily(learner_user_id,day_wib,used_count,extra_count) VALUES(4,CURDATE(),0,100) ON DUPLICATE KEY UPDATE extra_count=100;");
const mandiri={grade_id:'3',subject_id:'1',count:'3',minutes:'0',option_shuffle:'1',view_mode:'single',focus_guard:'0',focus_limit:'3'};
async function start(path,params={}){
 const page=await child(path);
 const r=await child(path,{...params,start_token:field(page.body,'start_token'),study_csrf_token:field(page.body,'study_csrf_token')});
 assert.equal(r.status,303,r.body);return new URL(r.location).pathname;
}
let page=await child('/latihan/mandiri');
assert.ok(!page.body.includes('name="difficulty"'));
assert.equal((await other('/latihan/buka/advanced/3')).status,404);
assert.equal((await post(child,'/latihan/mandiri',{...mandiri,view_mode:'invalid',start_token:field(page.body,'start_token')})).status,400);
// Mix basic/advanced without purchasing advanced; scope metadata remains intact.
const qids=sql("SELECT q.id FROM st_questions q JOIN st_bank_scopes s ON s.question_id=q.id WHERE s.subject_id=1 AND s.grade_id=3 AND q.status='published' LIMIT 3").split('\n');
const scopeBefore=sql(`SELECT difficulty FROM st_bank_scopes WHERE question_id=${qids[0]} AND grade_id=3 AND subject_id=1`);
sql(`UPDATE st_bank_scopes SET difficulty='advanced' WHERE question_id=${qids[0]} AND grade_id=3 AND subject_id=1;`);
const countAll=Number(sql("SELECT COUNT(DISTINCT q.id) FROM st_questions q JOIN st_bank_scopes s ON s.question_id=q.id WHERE s.subject_id=1 AND s.grade_id=3 AND q.status='published'"));
let path=await start('/latihan/mandiri',{...mandiri,count:String(Math.min(50,countAll))});
assert.equal(sql(`SELECT COUNT(*) FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=${aid(path)}) AND question_id=${qids[0]}`),'1');
sql(`UPDATE st_bank_scopes SET difficulty='${scopeBefore}' WHERE question_id=${qids[0]} AND grade_id=3 AND subject_id=1;`);
assert.equal((await other(path)).status,404);
assert.equal((await other(path+'?sheet_state=1')).status,404);
assert.equal((await child(path,{action:'save',sheet_ajax:'1',revision:'0'})).status,403);
assert.equal((await save(child,path,{view_mode:'ledger',focus_guard:'1',[`focus_events[${'a'.repeat(32)}]`]:'blur'})).status,200);
assert.equal(sql(`SELECT COUNT(*) FROM st_practice_focus_events WHERE attempt_id=${aid(path)}`),'0');
assert.equal(JSON.parse(sql(`SELECT s.sheet_rules_json FROM st_practice_sessions s JOIN st_practice_attempts a ON a.session_id=s.id WHERE a.id=${aid(path)}`)).view_mode,'single');
decoded(await finish(path));
// Course settings are snapshotted; off stays off regardless of request fields.
path=await start('/latihan/kursus/4',{view_mode:'ledger',focus_guard:'1',focus_limit:'2'});
const cid=aid(path);
let state=decoded(await save(child,path,{[`focus_events[${'b'.repeat(32)}]`]:'hidden'}));
assert.equal(state.focus_count,1);assert.equal(state.status,'in_progress');
state=decoded(await save(child,path,{[`focus_events[${'b'.repeat(32)}]`]:'hidden'}));
assert.equal(state.focus_count,1);
// Stale focus request must not overwrite newer answers; still enforces limit.
const question=JSON.parse(sql(`SELECT snapshot_json FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=${cid}) ORDER BY sort_order LIMIT 1`));
const qid=sql(`SELECT question_id FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=${cid}) ORDER BY sort_order LIMIT 1`);
const correct=question.options.find(o=>Number(o.is_correct)===1).id;
state=decoded(await save(child,path,{[`answers[${qid}]`]:String(correct)}));
const rev=state.revision;
assert.equal((await save(child,path,{revision:'0'})).status,409);
state=decoded(await save(child,path,{revision:'0',focus_guard:'0',focus_limit:'10',[`focus_events[${'c'.repeat(32)}]`]:'sleep'}));
assert.equal(state.status,'graded');assert.equal(state.focus_count,2);assert.equal(state.conflict,true);
assert.equal(sql(`SELECT selected_option_id FROM st_practice_answers WHERE attempt_id=${cid} AND question_id=${qid}`),String(correct));
// Final replay cannot add events or change score.
const finalBefore=sql(`SELECT CONCAT(score,':',answer_revision) FROM st_practice_attempts WHERE id=${cid}`);
const token=JSON.parse((await child(path+'?sheet_state=1')).body).csrf_token;
assert.equal((await child(path,{sheet_ajax:'1',revision:String(rev),action:'save',study_csrf_token:token,[`focus_events[${'d'.repeat(32)}]`]:'blur'})).status,200);
assert.equal(sql(`SELECT CONCAT(score,':',answer_revision) FROM st_practice_attempts WHERE id=${cid}`),finalBefore);
// Admin exam configuration, selecting existing bank IDs and publishing.
const ev={title:'QA080 '+nonce,grade_id:'3',duration_minutes:'0',max_daily:'20',credit_price:'0',status:'draft',question_shuffle:'1',option_shuffle:'1',revision:'0',action:'save',view_mode:'ledger',focus_guard:'1',focus_limit:'2'};
let r=await post(admin,'/kelola/simulasi-ujian/0',ev);assert.equal(r.status,303,r.body);
const examPath=new URL(r.location).pathname,eid=aid(examPath);
page=await admin(examPath);
r=await post(admin,examPath,{action:'add',revision:field(page.body,'revision'),'questions[0]':qids[0],'questions[1]':qids[1],'questions[2]':qids[2]});assert.equal(r.status,303,r.body);
page=await admin(examPath);
r=await post(admin,examPath,{...ev,status:'published',revision:field(page.body,'revision')});assert.equal(r.status,303);
page=await child('/latihan/ujian/'+eid);assert.ok(page.body.includes('perpindahan ke-2'));
path=await start('/latihan/ujian/'+eid,{view_mode:'single',focus_guard:'0',focus_limit:'10'});
const examAttempt=aid(path);
assert.equal(JSON.parse(sql(`SELECT sheet_rules_json FROM st_practice_sessions WHERE id=(SELECT session_id FROM st_practice_attempts WHERE id=${examAttempt})`)).focus_guard,'1');
page=await admin(examPath);
r=await post(admin,examPath,{...ev,status:'published',focus_guard:'0',revision:field(page.body,'revision')});assert.equal(r.status,303);
assert.equal(JSON.parse(sql(`SELECT sheet_rules_json FROM st_practice_sessions WHERE id=(SELECT session_id FROM st_practice_attempts WHERE id=${examAttempt})`)).focus_guard,'1');
// Invalid option/event must not write either answers or focus events.
assert.equal((await save(child,path,{[`answers[${qids[0]}]`]:'999999999',[`focus_events[${'e'.repeat(32)}]`]:'blur'})).status,400);
assert.equal(sql(`SELECT COUNT(*) FROM st_practice_focus_events WHERE attempt_id=${examAttempt}`),'0');
assert.equal((await save(child,path,{'focus_events[invalid]':'blur'})).status,400);
decoded(await finish(path));
// Quota remains the only Mandiri access gate; failed starts cannot consume it.
const day="DATE(CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+07:00'))";
const oldDaily=sql(`SELECT used_count,extra_count FROM st_drill_daily WHERE learner_user_id=4 AND day_wib=${day}`).split('\t');
sql(`UPDATE st_drill_daily SET used_count=10000,extra_count=0 WHERE learner_user_id=4 AND day_wib=${day}`);
try {
 page=await child('/latihan/mandiri');
 const beforeAttempts=sql("SELECT COUNT(*) FROM st_practice_attempts WHERE learner_user_id=4");
 r=await post(child,'/latihan/mandiri',{...mandiri,start_token:field(page.body,'start_token')});
 assert.equal(r.status,409);assert.ok(r.body.includes('Kuota hari ini habis'));
 assert.equal(sql("SELECT COUNT(*) FROM st_practice_attempts WHERE learner_user_id=4"),beforeAttempts);
} finally {sql(`UPDATE st_drill_daily SET used_count=${Number(oldDaily[0])},extra_count=${Number(oldDaily[1])} WHERE learner_user_id=4 AND day_wib=${day}`);}
// Legacy sessions retain ledger/guard-off; expiration grades last saved choices.
path=await start('/latihan/mandiri',{...mandiri,minutes:'1',count:'2'});
const timed=aid(path),sid=sql(`SELECT session_id FROM st_practice_attempts WHERE id=${timed}`);
const tqid=sql(`SELECT question_id FROM st_session_questions WHERE session_id=${sid} ORDER BY sort_order LIMIT 1`);
const opts=JSON.parse(sql(`SELECT snapshot_json FROM st_session_questions WHERE session_id=${sid} AND question_id=${tqid}`)).options;
decoded(await save(child,path,{[`answers[${tqid}]`]:String(opts[0].id)}));
sql(`UPDATE st_practice_sessions SET sheet_rules_json=NULL WHERE id=${sid}; UPDATE st_drill_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE session_id=${sid};`);
page=await child(path);assert.ok(page.body.includes('data-mode="ledger"'));assert.ok(page.body.includes('data-guard="0"'));
decoded(await save(child,path,{[`answers[${tqid}]`]:String(opts[1].id)}));
assert.equal(sql(`SELECT status FROM st_practice_attempts WHERE id=${timed}`),'graded');
assert.equal(sql(`SELECT selected_option_id FROM st_practice_answers WHERE attempt_id=${timed} AND question_id=${tqid}`),String(opts[0].id));
console.log('PASS HTTP: Mandiri mixed bank/no advanced gate; settings validation; course snapshot; event replay; stale protection/forced grade; admin exam snapshot; owner+CSRF+invalid input; final replay.');
}
if(process.env.SHEET_HTTP_ONLY) process.exit(0);
// Browser: real autosave, ledger scroll, keyboard navigation, focus events, mobile.
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try {
 const p=await browser.newPage(),errors=[];p.on('pageerror',e=>{errors.push(e.message);console.error('Browser error:',e.message);});p.on('dialog',d=>d.accept());
 await p.goto(base+'/masuk');await p.type('[name=identity]','monitor4');await p.type('[name=password]','Monitor-QA-Only-2026!');
 await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 async function browserStart(mode,guard='0',limit='2'){
  await p.goto(base+'/latihan/kursus/4');
  await p.select('[name=view_mode]',mode);await p.select('[name=focus_guard]',guard);
  await p.$eval('[name=focus_limit]',(el,v)=>{el.value=v;},limit);
  await Promise.all([p.waitForNavigation(),p.click('form[action$="/latihan/kursus/4"] button[type=submit]')]);
  await p.waitForSelector('#practice-answer-form');return aid(new URL(p.url()).pathname);
 }
 async function answer(index){
  await p.$eval(`.practice-question:nth-child(${index+1}) input[type=radio]`,el=>el.click());
  try {await p.waitForFunction(()=>document.querySelector('#sheet-save-status').textContent.startsWith('Tersimpan'));}
  catch(e){console.error('Save state:',await p.$eval('#sheet-save-status',el=>el.textContent));throw e;}
 }
 async function browserFinish(){
  await p.click('button[name=action][value=finish]');
  await Promise.all([p.waitForNavigation(),p.click('#sheet-confirm .btn-danger')]);
  assert.ok(await p.$eval('h1',el=>!!el.textContent));
 }
 if (!process.env.SHEET_FOCUS_ONLY) for(const width of [1280,360]){
  await p.setViewport({width,height:900});
  const id=await browserStart('single');
  assert.equal(await p.$$eval('.practice-question',els=>els.filter(e=>!e.hidden).length),1);
  await answer(0);
  assert.equal(sql(`SELECT COUNT(*) FROM st_practice_answers WHERE attempt_id=${id} AND selected_option_id IS NOT NULL`),'1');
  await p.focus('[data-nav-index="1"]');await p.keyboard.press('Enter');
  assert.equal(await p.$eval('#sheet-q-1',e=>e.hidden),false);
  await p.click('[data-sheet-prev]');
  assert.equal(await p.$eval('#sheet-q-0',e=>e.hidden),false);
  await p.reload({waitUntil:'networkidle0'});
  assert.ok(await p.$eval('[data-nav-index="0"]',e=>e.classList.contains('is-answered')));
  assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await p.screenshot({path:`/tmp/sheet080-single-${width}.png`,fullPage:true});
  await browserFinish();
  await browserStart('ledger');
  assert.equal(await p.$$eval('.practice-question',els=>els.filter(e=>!e.hidden).length),await p.$$('.practice-question').then(a=>a.length));
  await answer(0);
  await p.waitForFunction(()=>document.activeElement.id==='sheet-q-1');
  assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await p.screenshot({path:`/tmp/sheet080-ledger-${width}.png`,fullPage:true});
  await browserFinish();
 }
 const guarded=await browserStart('single','1','2');
 await answer(0);
 const otherTab=await browser.newPage();await otherTab.bringToFront();
 await p.waitForFunction(()=>document.querySelector('span[data-focus-count]').textContent==='1',{polling:100});
 await otherTab.close();await p.bringToFront();
 await p.waitForFunction(()=>document.querySelector('#sheet-save-status').textContent.startsWith('Tersimpan'));
 assert.equal(sql(`SELECT COUNT(*) FROM st_practice_focus_events WHERE attempt_id=${guarded}`),'1');
 const replayToken=sql(`SELECT event_token FROM st_practice_focus_events WHERE attempt_id=${guarded}`);
 await p.evaluate(({id,token})=>sessionStorage.setItem(`pp-focus-080:${id}`,JSON.stringify({[token]:'blur'})),{id:guarded,token:replayToken});
 await p.reload({waitUntil:'networkidle0'});
 await p.waitForFunction(()=>document.querySelector('#sheet-q-0 input').disabled===false);
 assert.equal(await p.$eval('span[data-focus-count]',e=>e.textContent),'1');
 await Promise.all([p.waitForNavigation(),p.evaluate(()=>window.dispatchEvent(new Event('blur')))]);
 assert.equal(sql(`SELECT status FROM st_practice_attempts WHERE id=${guarded}`),'graded');
 assert.equal(sql(`SELECT COUNT(*) FROM st_practice_focus_events WHERE attempt_id=${guarded}`),'2');
 // Offline focus event is retained locally; reconnect posts once and finishes.
 const offline=await browserStart('ledger','1','1');
 await answer(0);
 await p.setOfflineMode(true);
 await p.evaluate(()=>window.dispatchEvent(new Event('blur')));
 await p.waitForFunction(()=>document.querySelector('#sheet-save-status').textContent.includes('koneksi terganggu'));
 assert.equal(sql(`SELECT status FROM st_practice_attempts WHERE id=${offline}`),'in_progress');
 await p.setOfflineMode(false);
 await Promise.all([p.waitForNavigation(),p.evaluate(()=>window.dispatchEvent(new Event('online')))]);
 assert.equal(sql(`SELECT status FROM st_practice_attempts WHERE id=${offline}`),'graded');
 assert.equal(sql(`SELECT COUNT(*) FROM st_practice_focus_events WHERE attempt_id=${offline}`),'1');
 // Simulate browser timer suspension without waiting or changing OS clock.
 const sleep=await browserStart('single','1','1');
 await Promise.all([p.waitForNavigation(),p.evaluate(()=>{const original=Date.now;Date.now=()=>original()+31000;})]);
 assert.equal(sql(`SELECT status FROM st_practice_attempts WHERE id=${sleep}`),'graded');
 assert.equal(sql(`SELECT reason FROM st_practice_focus_events WHERE attempt_id=${sleep}`),'sleep');
 // A competing save rotates CSRF and revision; local stale answer must not win.
 const stale=await browserStart('single');
 await p.evaluate(async()=>{
  const f=document.querySelector('#practice-answer-form'),d=new URLSearchParams(new FormData(f));
  d.set('action','save');d.set('sheet_ajax','1');
  const r=await fetch(f.getAttribute('action'),{method:'POST',body:d});if(!r.ok)throw new Error('Competing save failed');
 });
 await p.$eval('#sheet-q-0 input[type=radio]',el=>el.click());
 await p.waitForFunction(()=>document.querySelector('#sheet-save-status').textContent.includes('tab lain'));
 assert.equal(sql(`SELECT COUNT(*) FROM st_practice_answers WHERE attempt_id=${stale} AND selected_option_id IS NOT NULL`),'0');
 await p.reload({waitUntil:'networkidle0'});await browserFinish();
 assert.deepEqual(errors,[]);
 console.log(process.env.SHEET_FOCUS_ONLY
  ? 'PASS focused browser: real tab switch/dedup, reload + acknowledged-event replay unlock, offline retry, sleep fallback, auto-finish, CSRF refresh and stale protection.'
  : 'PASS browser: 1280/360 single+ledger; autosave+reload; navigation+keyboard; next-scroll; real tab switch/dedup; guard/replay/auto-finish; offline retry+sleep; CSRF refresh/stale protection; no JS errors/overflow.');
} finally {await browser.close();}
