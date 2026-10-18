import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {createHmac} from 'node:crypto';
const database=process.env.STUDY_MONITOR_TEST_DB;assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch('http://127.0.0.1:8097'+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(id){const req=client(),p=await req('/masuk');assert.equal((await req('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return req;}
const user=await login(12),other=await login(4),admin=await login(1);
assert.equal(sql('SELECT COUNT(*) FROM st_arena_runs WHERE learner_user_id=12'),'0','Fresh synthetic learner required');
const bank=sql('SELECT COUNT(*) FROM st_questions');
const packs=sql("SELECT content_json FROM st_arena_packs WHERE game_type='english' ORDER BY id").split('\n').map(JSON.parse);assert.equal(packs.length,75);
const nonce='b'.repeat(32),slug='inggris-final-stage-1',token=nonce+'.'+createHmac('sha256','monitor-qa-placeholder-key').update('12:arena:'+slug+':'+nonce).digest('hex');
let page=await user('/belajar/arena/pilih/'+slug);assert.ok(!page.body.includes('name="start_token"'));
assert.equal((await user('/belajar/arena/pilih/'+slug,{start_token:token,mode:'calm',study_csrf_token:field(page.body,'study_csrf_token')})).status,403,'Valid signed request cannot bypass prerequisites');
async function start(pack){const p=await user('/belajar/arena/pilih/'+pack.slug);const data={start_token:field(p.body,'start_token'),mode:'calm',study_csrf_token:field(p.body,'study_csrf_token')};const r=await user('/belajar/arena/pilih/'+pack.slug,data);assert.equal(r.status,303);return Number(r.location.match(/main\/(\d+)/)[1]);}
async function act(id,action,value='',expected=303,revision){const p=await user('/belajar/arena/main/'+id);assert.equal(p.status,200);const rev=revision||field(p.body,'revision');const r=await user('/belajar/arena/main/'+id,{revision:rev,action,value,score:'100',passed:'1',study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(r.status,expected,`${action} ${id}: ${r.status}`);return rev;}
let count=0;
for(const pack of packs){
 const id=await start(pack),snapshot=JSON.parse(sql(`SELECT snapshot_json FROM st_arena_runs WHERE id=${id}`));
 if(count===0){assert.equal((await other('/belajar/arena/main/'+id)).status,404);assert.equal((await user('/belajar/arena/main/'+id,{revision:'1',action:'next'})).status,403);
  sql("CREATE TRIGGER qa_english_fail BEFORE UPDATE ON st_arena_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");try{await act(id,'next','',503);assert.equal(sql(`SELECT revision FROM st_arena_runs WHERE id=${id}`),'1');}finally{sql('DROP TRIGGER qa_english_fail');}
 }
 for(const item of snapshot.items){
  if(['read','mission'].includes(item.kind)){const revision=await act(id,'next','repeat');if(count===0)await act(id,'next','',409,revision);}
  else{const value=item.kind==='sentence'?item.english:Object.keys(item.options).find(k=>item.options[k]===item.answer);await act(id,'next','',400);await act(id,'answer',value);if(pack.kind==='lesson'){page=await user('/belajar/arena/main/'+id);assert.ok(page.body.includes('Susunannya sesuai'));await act(id,'next');}}
  count++;
 }
 assert.equal(sql(`SELECT status FROM st_arena_runs WHERE id=${id}`),'completed');
 if(pack.kind!=='lesson')assert.equal(sql(`SELECT passed FROM st_arena_runs WHERE id=${id}`),'1');
 if(pack.kind==='checkpoint')console.log('PASS chapter '+pack.chapter+' lessons and checkpoint');
}
assert.equal(count,814);assert.equal(sql("SELECT COUNT(*) FROM st_arena_runs WHERE learner_user_id=12 AND status='completed'"),'75');
page=await admin('/kelola/pembelajaran/pengguna/12?tab=english_lesson');assert.equal(page.status,200);assert.ok(page.body.includes('dari 60 pelajaran unik'));assert.ok(!page.body.includes('snapshot_json'));
page=await admin('/kelola/pembelajaran/pengguna/12?tab=english_test');assert.equal(page.status,200);assert.ok(page.body.includes('dari 15 percobaan evaluasi'));
const repeat=await start(packs[0]);await act(repeat,'next');await act(repeat,'stop');page=await admin('/kelola/rapor/12');assert.ok(page.body.includes('dari 60 pelajaran unik'),'Replay does not double count lesson');
assert.equal(sql('SELECT COUNT(*) FROM st_questions'),bank);console.log('PASS English HTTP: all 60 lessons/600 steps and 15 evaluations/214 questions; gates, CSRF, ownership, stale tabs, rollback, typed feedback, server scoring, unique progress/reporting and unchanged exercise bank.');
