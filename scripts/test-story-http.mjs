import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const database=process.env.STUDY_MONITOR_TEST_DB,base='http://127.0.0.1:8097';assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
const jar=new Map();async function request(path,data){const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body));return {status:r.status,body,location:r.headers.get('location')};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
let page=await request('/masuk');assert.equal((await request('/masuk',{identity:'monitor8',password:'Monitor-QA-Only-2026!',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
const packs=sql("SELECT slug FROM st_arena_packs WHERE game_type='story' ORDER BY id").split('\n');assert.equal(packs.length,22);
const bankBefore=sql('SELECT COUNT(*) FROM st_questions;');
const oldSnapshots=sql("SELECT id,SHA2(snapshot_json,256) FROM st_arena_runs WHERE game_type='story' AND content_version=1 ORDER BY id");
for(const slug of packs){
 const choose=await request('/belajar/arena/pilih/'+slug);assert.equal(choose.status,200);
 const result=await request('/belajar/arena/pilih/'+slug,{start_token:field(choose.body,'start_token'),mode:'calm',study_csrf_token:field(choose.body,'study_csrf_token')});assert.equal(result.status,303);
 const id=Number(result.location.match(/main\/(\d+)/)[1]);
 const snapshot=JSON.parse(sql(`SELECT snapshot_json FROM st_arena_runs WHERE id=${id}`));assert.ok(snapshot.reading.length>100);
 for(const item of snapshot.items){page=await request('/belajar/arena/main/'+id);assert.equal(page.status,200);assert.ok(page.body.includes('Baca ceritanya'));assert.ok(!page.body.includes('name="correct_option"'));assert.ok(page.body.includes('type="radio"'));const answer=await request('/belajar/arena/main/'+id,{revision:field(page.body,'revision'),action:'answer',value:item.term,study_csrf_token:field(page.body,'study_csrf_token')});assert.equal(answer.status,303);}
 page=await request('/belajar/arena/main/'+id);assert.ok(page.body.includes('100%'));assert.ok(page.body.includes(snapshot.items[0].explanation));
 assert.equal(sql(`SELECT status FROM st_arena_runs WHERE id=${id}`),'completed');
}
assert.equal(sql('SELECT COUNT(*) FROM st_questions;'),bankBefore,'No session/question bank import');
assert.equal(sql("SELECT id,SHA2(snapshot_json,256) FROM st_arena_runs WHERE game_type='story' AND content_version=1 ORDER BY id"),oldSnapshots,'Historical snapshots unchanged');
assert.equal(sql("SELECT SUM(JSON_LENGTH(content_json,'$.items')) FROM st_arena_packs WHERE game_type='story'"),'101');
console.log('PASS reviewed Story Quiz: all 22 unique readings and 101 comprehension questions playable, shuffled choices, server grading/final explanations; old run snapshots and exercise bank unchanged.');
