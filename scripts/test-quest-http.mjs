import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {createHmac} from 'node:crypto';
const database=process.env.STUDY_MONITOR_TEST_DB;assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch('http://127.0.0.1:8097'+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(id){const req=client(),p=await req('/masuk');assert.equal((await req('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return req;}

const user=await login(14),other=await login(3),admin=await login(1);
assert.equal(sql('SELECT COUNT(*) FROM st_arena_runs WHERE learner_user_id=14'),'0','Fresh synthetic learner');
const packs=sql("SELECT content_json FROM st_arena_packs WHERE game_type='quest' ORDER BY id").split('\n').map(JSON.parse);assert.equal(packs.length,15);
const bank=sql('SELECT COUNT(*) FROM st_questions');
async function start(pack){const p=await user('/belajar/arena/pilih/'+pack.slug);const data={start_token:field(p.body,'start_token'),mode:'calm',study_csrf_token:field(p.body,'study_csrf_token')};const r=await user('/belajar/arena/pilih/'+pack.slug,data);assert.equal(r.status,303);const fresh=await user('/belajar/arena/pilih/'+pack.slug);assert.equal((await user('/belajar/arena/pilih/'+pack.slug,{...data,study_csrf_token:field(fresh.body,'study_csrf_token')})).location,r.location);return Number(r.location.match(/main\/(\d+)/)[1]);}
async function act(id,action,value='',expected=303,revision){const p=await user('/belajar/arena/main/'+id);assert.equal(p.status,200);const rev=revision||field(p.body,'revision');const r=await user('/belajar/arena/main/'+id,{revision:rev,action,value,score:'100',study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(r.status,expected,`${id} ${action} ${r.status}`);return rev;}
let page=await user('/belajar/arena/pilih/'+packs[1].slug);assert.ok(!page.body.includes('name="start_token"'));
const nonce='c'.repeat(32),slug=packs[1].slug,token=nonce+'.'+createHmac('sha256','monitor-qa-placeholder-key').update('14:arena:'+slug+':'+nonce).digest('hex');
assert.equal((await user('/belajar/arena/pilih/'+slug,{start_token:token,mode:'calm',study_csrf_token:field(page.body,'study_csrf_token')})).status,403,'Signed direct locked request rejected');
let count=0;
for(const pack of packs){
 const id=await start(pack),snapshot=JSON.parse(sql(`SELECT snapshot_json FROM st_arena_runs WHERE id=${id}`));assert.equal((await other('/belajar/arena/main/'+id)).status,404);
 if(count===0){assert.equal((await user('/belajar/arena/main/'+id,{revision:'1',action:'answer',value:'A'})).status,403);sql("CREATE TRIGGER qa_quest_fail BEFORE UPDATE ON st_arena_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");try{await act(id,'answer','A',503);assert.equal(sql(`SELECT revision FROM st_arena_runs WHERE id=${id}`),'1');}finally{sql('DROP TRIGGER qa_quest_fail');}}
 for(const item of snapshot.items){
  page=await user('/belajar/arena/main/'+id);assert.ok(!page.body.includes('choices_json')&&!page.body.includes('"correct":'));
  await act(id,'next','',400);const value=item.kind==='sentence'?item.answer:Object.keys(item.choices).find(k=>item.kind==='story'||item.choices[k].correct);
  const old=await act(id,'answer',value);await act(id,'answer',value,409,old);page=await user('/belajar/arena/main/'+id);assert.ok(page.body.includes(item.kind==='story'?'Pilihan cerita tersimpan':'Jawaban sesuai'));await act(id,'next');count++;
 }
 assert.equal(sql(`SELECT CONCAT(status,':',correct_count,':',question_count) FROM st_arena_runs WHERE id=${id}`),'completed:13:13');
 page=await user('/belajar/arena/main/'+id);assert.ok(page.body.includes('Jurnal petualangan'));console.log('PASS Quest '+pack.title);
}
assert.equal(count,225);const again=await start(packs[0]);await act(again,'answer','A');await act(again,'next');await act(again,'stop');
assert.equal(sql("SELECT COUNT(DISTINCT pack_id) FROM st_arena_runs WHERE learner_user_id=14 AND game_type='quest' AND status='completed'"),'15');
page=await admin('/kelola/pembelajaran/pengguna/14?tab=arena');assert.equal(page.status,200);assert.ok(page.body.includes('The Lost Library'));
assert.equal(sql('SELECT COUNT(*) FROM st_questions'),bank);console.log('PASS all 225 live-source Quest scenes, private journal, server scoring, story choices ungraded, locked episodes, IDOR/CSRF/replay/stale tabs/save rollback and no exercise bank import.');
