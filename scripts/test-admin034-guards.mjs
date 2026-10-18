import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const jar=new Map();async function req(path,data){const r=await fetch('http://127.0.0.1:8097'+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text()};}
const field=(s,n)=>{const m=s.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
let page=await req('/masuk');assert.equal((await req('/masuk',{identity:'monitor1',password:'Monitor-QA-Only-2026!',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
const path='/kelola/arena?jenis=word';page=await req(path);const id=field(page.body,'pack_id'),revision=field(page.body,'revision'),before=sql('SELECT CONCAT(enabled,":",revision) FROM st_arena_packs WHERE id='+Number(id));
assert.equal((await req(path,{pack_id:id,revision,enabled:'0'})).status,403);
page=await req(path);assert.equal((await req(path,{pack_id:id,revision:'999999',enabled:'0',study_csrf_token:field(page.body,'study_csrf_token')})).status,409);
page=await req(path);sql("CREATE TRIGGER qa_admin034_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA audit failure'");
try{assert.equal((await req(path,{pack_id:id,revision,enabled:'0',study_csrf_token:field(page.body,'study_csrf_token')})).status,503);assert.equal(sql('SELECT CONCAT(enabled,":",revision) FROM st_arena_packs WHERE id='+Number(id)),before);}finally{sql('DROP TRIGGER qa_admin034_audit_fail');}
page=await req('/kelola/kursus/tambah');assert.equal(page.status,200);for(const name of ['Matematika','PAUD Terpadu','IPAS'])assert.ok(page.body.includes(name),'Master usable in authoring '+name);
page=await req('/belajar/arena');assert.equal(page.status,200);assert.ok(page.body.includes('English Quest'));
const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
page=await req(`/kelola/trafik/ringkasan?from=${today}&to=${today}&stage=primary&audience=child`);assert.equal(page.status,200);assert.ok(page.body.includes('stage=primary'));assert.ok(page.body.includes('audience=child'));assert.ok(!page.body.includes('Data trafik belum dapat dibaca'));
console.log('PASS Arena toggle CSRF/stale/audit rollback unchanged; imported categories usable in course authoring; participant Arena intact; empty traffic combined filter navigation preserved.');
