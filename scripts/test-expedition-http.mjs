import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {createHmac} from 'node:crypto';
// Run sequentially: test-arena-http deliberately injects DB failures/settings changes.
const base='http://127.0.0.1:8097', database=process.env.STUDY_MONITOR_TEST_DB;
assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
const jar=new Map();
async function request(path,data){const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body));return {status:r.status,body,location:r.headers.get('location')};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
let page=await request('/masuk');assert.equal((await request('/masuk',{identity:'monitor7',password:'Monitor-QA-Only-2026!',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
assert.equal(sql("SELECT COUNT(*) FROM st_arena_runs WHERE learner_user_id=7;"),'0','Use a fresh isolated fixture');
const packs=sql("SELECT slug FROM st_arena_packs WHERE game_type='expedition' ORDER BY CAST(JSON_EXTRACT(content_json,'$.level') AS UNSIGNED)").split('\n');
assert.equal(packs.length,12);
async function start(slug){const f=await request('/belajar/arena/pilih/'+slug);const result=await request('/belajar/arena/pilih/'+slug,{start_token:field(f.body,'start_token'),mode:'calm',study_csrf_token:field(f.body,'study_csrf_token')});assert.equal(result.status,303);return Number(result.location.match(/main\/(\d+)/)[1]);}
async function complete(id,right){const snapshot=JSON.parse(sql(`SELECT snapshot_json FROM st_arena_runs WHERE id=${id}`));for(const [i,item] of snapshot.items.entries()){const f=await request('/belajar/arena/main/'+id);assert.ok(f.body.includes('Petunjuk pertama'));assert.ok(!f.body.includes(item.explanation),'No explanation before final');const wrong=Object.keys(item.options).find(k=>k!==item.term);const result=await request('/belajar/arena/main/'+id,{revision:field(f.body,'revision'),action:'answer',value:i<right?item.term:wrong,score:'100',passed:'1',study_csrf_token:field(f.body,'study_csrf_token')});assert.equal(result.status,303);}const result=await request('/belajar/arena/main/'+id);assert.ok(result.body.includes(snapshot.items[0].explanation));}
page=await request('/belajar/arena/pilih/'+packs[1]);assert.ok(!page.body.includes('name="start_token"'),'Locked level hidden');
const nonce='a'.repeat(32),lockedToken=nonce+'.'+createHmac('sha256','monitor-qa-placeholder-key').update(`7:arena:${packs[1]}:${nonce}`).digest('hex');
assert.equal((await request('/belajar/arena/pilih/'+packs[1],{start_token:lockedToken,mode:'calm',study_csrf_token:field(page.body,'study_csrf_token')})).status,403,'Server rejects valid signed start for locked level');
// A valid token from the first level cannot be replayed against a different level.
const first=await request('/belajar/arena/pilih/'+packs[0]);assert.equal((await request('/belajar/arena/pilih/'+packs[1],{start_token:field(first.body,'start_token'),mode:'calm',study_csrf_token:field(first.body,'study_csrf_token')})).status,400);
let id=await start(packs[0]);await complete(id,3);assert.equal(sql(`SELECT passed FROM st_arena_runs WHERE id=${id}`),'0');
page=await request('/belajar/arena/pilih/'+packs[1]);assert.ok(!page.body.includes('name="start_token"'),'60% does not pass 80% threshold');
id=await start(packs[0]);
// Changing global rules after start must not alter the existing snapshot.
sql("UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.expedition_pass','100') WHERE section='arena';");
await complete(id,4);assert.equal(sql(`SELECT passed FROM st_arena_runs WHERE id=${id}`),'1','Original 80% rule remains');
sql("UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.expedition_pass','80') WHERE section='arena';");
for(const slug of packs.slice(1)){id=await start(slug);await complete(id,5);assert.equal(sql(`SELECT passed FROM st_arena_runs WHERE id=${id}`),'1');}
page=await request('/belajar/arena');assert.ok(page.body.includes('Math Expedition 12: Menara Logika'));
assert.equal(sql("SELECT COUNT(DISTINCT pack_id) FROM st_arena_runs WHERE learner_user_id=7 AND passed=1"),'12');
console.log('PASS Math Expedition HTTP: all 12 levels playable; locked-level token isolation, server scoring, 60/80% boundary, immutable threshold, 60 answers/explanations and unlock progression.');
