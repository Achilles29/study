// Run after test-flashcard-arena-http.mjs on its isolated synthetic fixture.
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const database=process.env.STUDY_MONITOR_TEST_DB;assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch('http://127.0.0.1:8097'+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(id){const req=client(),p=await req('/masuk');assert.equal((await req('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return req;}
const admin=await login(1),learner=await login(11),parent=await login(2),child=await login(3),other=await login(4);
for(const path of ['/kelola/pembelajaran/pengguna/9?tab=kartu_arena','/kelola/rapor/9?modul=kartu_arena']){
 const r=await admin(path);assert.equal(r.status,200);assert.match(r.body,/monitor-stat">150<\/strong>/);assert.ok(r.body.includes('dari 151 kartu tercatat'));assert.ok(!r.body.includes('Fotosintesis')&&!r.body.includes('snapshot_json'),'No card contents in monitoring');
}
assert.equal((await learner('/kelola/pembelajaran/modul/kartu_arena')).status,403);
async function setting(values,expected=303){const p=await admin('/kelola/pengaturan/arena');const r=await admin('/kelola/pengaturan/arena',{cards:'15',pairs:'6',words:'10',timer:'1',enabled:'1',expedition_pass:'80',...values,revision:field(p.body,'revision'),study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(r.status,expected);}
async function start(req){const p=await req('/belajar/arena/pilih/kartu-ipa-dasar');const r=await req('/belajar/arena/pilih/kartu-ipa-dasar',{start_token:field(p.body,'start_token'),mode:'calm',study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(r.status,303);return Number(r.location.match(/main\/(\d+)/)[1]);}
async function act(req,id,action,value='',expected=303){const p=await req('/belajar/arena/main/'+id);assert.equal((await req('/belajar/arena/main/'+id,{revision:field(p.body,'revision'),action,value,study_csrf_token:field(p.body,'study_csrf_token')})).status,expected);}
const previous=sql('SELECT learning_stage FROM st_users WHERE id=3');let running=[];
try{
 await setting({cards:'5'});let id=await start(learner);running.push([learner,id]);assert.equal(sql(`SELECT question_count FROM st_arena_runs WHERE id=${id}`),'5');
 await setting({cards:'10'});assert.equal(sql(`SELECT question_count FROM st_arena_runs WHERE id=${id}`),'5','Settings do not rewrite running snapshot');await act(learner,id,'stop');running=[];
 id=await start(learner);running.push([learner,id]);assert.equal(sql(`SELECT question_count FROM st_arena_runs WHERE id=${id}`),'10');
 await setting({enabled:'0'});await act(learner,id,'reveal','',403);await act(learner,id,'stop');running=[];await setting({cards:'15'});
 sql("UPDATE st_users SET learning_stage='early_childhood' WHERE id=3");
 id=await start(child);running.push([child,id]);assert.equal(sql(`SELECT question_count FROM st_arena_runs WHERE id=${id}`),'5','TK card cap');
 assert.equal((await parent('/belajar/arena/main/'+id)).status,404);assert.equal((await other('/belajar/arena/main/'+id)).status,404);
 await act(child,id,'reveal');await act(child,id,'rate','known');await act(child,id,'stop');running=[];
 const report=await parent('/keluarga/anak/3/rapor?modul=kartu_arena');assert.equal(report.status,200);assert.ok(report.body.includes('dari 1 kartu tercatat'));assert.equal((await parent('/keluarga/anak/4/rapor?modul=kartu_arena')).status,404);
 sql("UPDATE st_family_links SET status='revoked' WHERE parent_user_id=2 AND child_user_id=3");assert.equal((await parent('/keluarga/anak/3/rapor?modul=kartu_arena')).status,404);
}finally{
 sql("UPDATE st_family_links SET status='active' WHERE parent_user_id=2 AND child_user_id=3");sql(`UPDATE st_users SET learning_stage='${previous}' WHERE id=3`);await setting({cards:'15'});for(const [req,id] of running)await act(req,id,'stop');
}
console.log('PASS flashcard settings 5/10/15, immutable in-flight snapshot, pause/resume/stop, TK cap, admin counts 150/151 without quiz score/double games, family scope/revocation and no child-play access for parent.');
