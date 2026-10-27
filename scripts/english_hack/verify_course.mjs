import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.ENGLISH_COURSE_QA,'study_qa_monitor_sso039');
const root='/www/wwwroot/study',base='http://127.0.0.1:8098';
const sql=q=>execFileSync('mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','--socket=/tmp/mysql-study.sock','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const field=(html,name)=>{const m=html.match(new RegExp(`name="${name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}" value="([^"]*)"`));assert.ok(m,'Missing '+name);return m[1];};
const form=(html,path)=>{const f=[...html.matchAll(/<form\b[^>]*action="([^"]*)"[^>]*>([\s\S]*?)<\/form>/g)].find(m=>m[1].endsWith(path));assert.ok(f,'Missing form '+path);return f[2];};
function client(){const cookies=new Map();return async(path,data,headers={})=>{if(data&&!(data instanceof FormData)&&data.study_csrf_token&&cookies.has('study_csrf_cookie'))data={...data,study_csrf_token:cookies.get('study_csrf_cookie')};const r=await fetch(base+path,{method:data?'POST':'GET',redirect:'manual',headers:{Cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; '),...(data&&!(data instanceof FormData)?{'Content-Type':'application/x-www-form-urlencoded'}:{}),...headers},body:data?(data instanceof FormData?data:new URLSearchParams(data)):undefined});for(const item of r.headers.getSetCookie()){const v=item.split(';')[0],i=v.indexOf('=');cookies.set(v.slice(0,i),v.slice(i+1));}const bytes=Buffer.from(await r.arrayBuffer());const body=bytes.toString('utf8');assert.ok(!/A PHP Error|Severity:|Fatal error|Database Error/.test(body),body.slice(0,1000));return {status:r.status,body,bytes,headers:r.headers,location:r.headers.get('location')};};}
async function login(name){const c=client(),p=await c('/masuk');const r=await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(r.status,303,r.body.slice(0,300));return c;}
async function postForm(c,pagePath,postPath,values){const p=await c(pagePath);assert.equal(p.status,200,p.body.slice(0,300));const f=form(p.body,postPath);return c(postPath,{study_csrf_token:field(f,'study_csrf_token'),...values});}
async function start(c,unit,kind='practice'){const pagePath=kind==='checkpoint'?'/belajar/kelas-inggris?level='+unit:'/belajar/kelas-inggris/unit/'+unit;const p=await c(pagePath);const path='/belajar/kelas-inggris/mulai/'+kind+'/'+unit;const f=form(p.body,path);const body={study_csrf_token:field(f,'study_csrf_token'),start_token:field(f,'start_token')};const r=await c(path,body);assert.equal(r.status,303,r.body.slice(0,400));return {id:Number(r.location.split('/').at(-1)),body,path};}
async function answersFor(id){const snap=JSON.parse(sql('SELECT snapshot_json FROM st_english_attempts WHERE id='+id));return {snap,fields:Object.fromEntries(snap.questions.map(q=>['answers['+q.id+']',q.type==='choice'?String(q.answer_index):q.answers[0]]))};}
const original=sql('SELECT password_hash FROM st_users WHERE id=3');const original5=sql('SELECT password_hash FROM st_users WHERE id=5');
const password=execFileSync('/www/server/php/84/bin/php',['-r',"echo password_hash('Monitor-QA-Only-2026!', PASSWORD_DEFAULT);"],{encoding:'utf8'});
let browser;const report={};
try{
 sql("DELETE FROM st_english_submissions; DELETE FROM st_english_attempts; DELETE FROM st_english_progress; UPDATE st_english_settings SET enabled=1; UPDATE st_english_levels SET status='published',pass_score=80; UPDATE st_users SET password_hash='"+password+"' WHERE id IN (3,5);");
 const anon=client();assert.ok([302,303,307].includes((await anon('/belajar/kelas-inggris')).status));
 const admin=await login('monitor1'),parent=await login('monitor2'),child=await login('monitor3');
 assert.equal((await child('/kelola/kelas-inggris')).status,403);
 assert.equal((await child('/belajar/kelas-inggris/unit/invalid')).status,404);
 // Complete practice, validate grading, stale submission, idempotency, no answer leakage.
 const run=await start(child,'F01');let p=await child('/belajar/kelas-inggris/latihan/'+run.id);
 assert.ok(!/answer_index|snapshot_json|"answers":/.test(p.body));
 const {snap,fields}=await answersFor(run.id);assert.equal(snap.questions.length,6);
 let r=await child('/belajar/kelas-inggris/latihan/'+run.id,{study_csrf_token:field(p.body,'study_csrf_token'),revision:'99',...fields});assert.equal(r.status,409);
 p=await child('/belajar/kelas-inggris/latihan/'+run.id);const missing={...fields};delete missing[Object.keys(missing)[0]];
 r=await child('/belajar/kelas-inggris/latihan/'+run.id,{study_csrf_token:field(p.body,'study_csrf_token'),revision:'1',...missing});assert.equal(r.status,400);
 p=await child('/belajar/kelas-inggris/latihan/'+run.id);r=await child('/belajar/kelas-inggris/latihan/'+run.id,{study_csrf_token:field(p.body,'study_csrf_token'),revision:'1',...fields,score:'0'});assert.equal(r.status,303);
 assert.equal(Number(sql('SELECT score FROM st_english_attempts WHERE id='+run.id)),100);
 r=await child('/belajar/kelas-inggris/latihan/'+run.id,{study_csrf_token:run.body.study_csrf_token,revision:'1',...missing});assert.equal(r.status,303);
 assert.equal(Number(sql("SELECT attempt_count FROM st_english_progress WHERE learner_user_id=3 AND unit_id='F01'")),1);
 assert.equal((await parent('/belajar/kelas-inggris/latihan/'+run.id)).status,404);
 const replay=await child(run.path,run.body);assert.equal(Number(replay.location.split('/').at(-1)),run.id);
 // A future-due review does not artificially increase the spaced-review stage.
 const rev=await start(child,'F01','review');let rv=await answersFor(rev.id);p=await child('/belajar/kelas-inggris/latihan/'+rev.id);await child('/belajar/kelas-inggris/latihan/'+rev.id,{study_csrf_token:field(p.body,'study_csrf_token'),revision:'1',...rv.fields});assert.equal(sql("SELECT review_stage FROM st_english_progress WHERE learner_user_id=3 AND unit_id='F01'"),'0');
 sql("UPDATE st_english_progress SET review_due=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE learner_user_id=3 AND unit_id='F01'");
 const rev2=await start(child,'F01','review');rv=await answersFor(rev2.id);p=await child('/belajar/kelas-inggris/latihan/'+rev2.id);await child('/belajar/kelas-inggris/latihan/'+rev2.id,{study_csrf_token:field(p.body,'study_csrf_token'),revision:'1',...rv.fields});assert.equal(sql("SELECT review_stage FROM st_english_progress WHERE learner_user_id=3 AND unit_id='F01'"),'1');
 // Checkpoint is server-gated even when a valid token is obtained from another form is forged.
 p=await child('/belajar/kelas-inggris?level=foundation');assert.ok(!p.body.includes('name="start_token"')); // no checkpoint form yet
 for(const u of ['F02','F03','F04','F05','F06','F07','F08']){const x=await start(child,u);const a=await answersFor(x.id);p=await child('/belajar/kelas-inggris/latihan/'+x.id);assert.equal((await child('/belajar/kelas-inggris/latihan/'+x.id,{study_csrf_token:field(p.body,'study_csrf_token'),revision:'1',...a.fields})).status,303);}
 const cp=await start(child,'foundation','checkpoint');assert.equal((await answersFor(cp.id)).snap.questions.length,20);
 // Private writing, escaped rendering, rubric feedback, parent scope, revision conflict.
 p=await child('/belajar/kelas-inggris/tugas/writing/F01');const taskBody={study_csrf_token:field(p.body,'study_csrf_token'),start_token:field(p.body,'start_token'),response_text:'ship (sh); shop (sh). My answer includes <script>alert(1)</script> as plain text.'};
 r=await child('/belajar/kelas-inggris/tugas/writing/F01',taskBody);assert.equal(r.status,303,r.body.slice(0,400));const sid=Number(r.location.split('/').at(-1));
 p=await child('/belajar/kelas-inggris/karya/'+sid);assert.ok(p.body.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));assert.equal((await parent('/belajar/kelas-inggris/karya/'+sid)).status,200);
 const outsider=await login('monitor5');assert.equal((await outsider('/belajar/kelas-inggris/karya/'+sid)).status,404);
 p=await admin('/kelola/kelas-inggris/tinjau/'+sid);const scoreBody={study_csrf_token:field(p.body,'study_csrf_token'),revision:'1','scores[0]':'3','scores[1]':'3','scores[2]':'3','scores[3]':'3','scores[4]':'3',feedback:'Pola sh tepat. Lengkapi contoh baru dan jelaskan bunyinya saat membaca.'};r=await admin('/kelola/kelas-inggris/tinjau/'+sid,scoreBody);assert.equal(r.status,303);assert.equal(sql('SELECT status FROM st_english_submissions WHERE id='+sid),'accepted');assert.equal((await admin('/kelola/kelas-inggris/tinjau/'+sid,scoreBody)).status,409);
 // Audio upload stays private; linked parent may hear it, unrelated accounts cannot.
 p=await child('/belajar/kelas-inggris/tugas/speaking/F01');const fd=new FormData();fd.set('study_csrf_token',field(p.body,'study_csrf_token'));fd.set('start_token',field(p.body,'start_token'));fd.set('response_text','QA synthetic recording for a fictional character.');fd.set('recording',new Blob([await fs.readFile(root+'/application/content/english_hack/v1/audio/F01.wav')],{type:'audio/wav'}),'recording.wav');
 r=await child('/belajar/kelas-inggris/tugas/speaking/F01',fd);assert.equal(r.status,303,r.body.slice(0,600));const aid=Number(r.location.split('/').at(-1));
 assert.equal((await outsider('/belajar/kelas-inggris/karya/'+aid+'/audio')).status,404);
 const media=await parent('/belajar/kelas-inggris/karya/'+aid+'/audio',null,{Range:'bytes=0-43'});assert.equal(media.status,206);assert.equal(media.bytes.length,44);assert.equal(media.bytes.subarray(0,4).toString(),'RIFF');
 const sample=await child('/belajar/kelas-inggris/audio/F01',null,{Range:'bytes=0-43'});assert.equal(sample.status,206);
 assert.equal((await child('/belajar/kelas-inggris/audio/F01',null,{Range:'bytes=999999999-'})).status,416);
 // Publication changes are enforced by backend and stale admin revisions rejected.
 p=await admin('/kelola/kelas-inggris');let cfg=form(p.body,'/kelola/kelas-inggris');const revision=field(cfg,'revision');
 r=await admin('/kelola/kelas-inggris',{study_csrf_token:field(cfg,'study_csrf_token'),level_id:'global',revision,status:'0'});assert.equal(r.status,303);
 assert.equal((await child('/belajar/kelas-inggris/unit/F01')).status,403);assert.equal((await child('/belajar/kelas-inggris/audio/F01')).status,403);assert.equal((await child('/belajar/kelas-inggris/riwayat')).status,200);
 assert.equal((await admin('/kelola/kelas-inggris',{study_csrf_token:field(cfg,'study_csrf_token'),level_id:'global',revision,status:'1'})).status,409);
 sql('UPDATE st_english_settings SET enabled=1');
 sql("UPDATE st_english_levels SET status='draft' WHERE level_id='c2'");assert.equal((await child('/belajar/kelas-inggris/unit/C201')).status,403);sql("UPDATE st_english_levels SET status='published' WHERE level_id='c2'");
 assert.equal((await parent('/keluarga/anak/3/kelas-inggris')).status,200);assert.equal((await parent('/keluarga/anak/4/kelas-inggris')).status,404);
 report.http='server grading; no keys before submit; stale revision; idempotent submit/start; spaced review; 20-question checkpoint; escaped writing; rubric review; private audio/ranges; linked parent scope; publication enforcement';
 browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(base+'/masuk');await page.type('[name=identity]','monitor3');await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);
 const paths=['/belajar/kelas-inggris','/belajar/kelas-inggris/unit/F01','/belajar/kelas-inggris/latihan/'+cp.id,'/belajar/kelas-inggris/riwayat','/belajar/kelas-inggris/tugas/writing/F02','/belajar/kelas-inggris/karya/'+sid];
 const widths=[];for(const width of [1440,390]){await page.setViewport({width,height:950});for(const path of paths){const res=await page.goto(base+path);assert.equal(res.status(),200);const w=await page.evaluate(()=>({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));assert.ok(w.scroll<=w.client+1,JSON.stringify({path,width,...w}));widths.push({path,width});if(path==='/belajar/kelas-inggris'||path==='/belajar/kelas-inggris/unit/F01')await page.screenshot({path:root+'/docs/english-hack-v1/user-'+(path.endsWith('F01')?'unit':'index')+'-'+width+'.png'});}}
 await page.goto(base+'/belajar/kelas-inggris/unit/F01');await page.$eval('audio',a=>a.load());await page.waitForFunction(()=>document.querySelector('audio').readyState>=1);const duration=await page.$eval('audio',a=>a.duration);assert.ok(duration>5);assert.deepEqual(errors,[]);report.browser={layouts:widths.length,errors,audioDuration:duration};
 await fs.writeFile(root+'/docs/english-hack-v1/course-validation.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
}finally{if(browser)await browser.close();sql("UPDATE st_users SET password_hash='"+original+"' WHERE id=3; UPDATE st_users SET password_hash='"+original5+"' WHERE id=5;");}
