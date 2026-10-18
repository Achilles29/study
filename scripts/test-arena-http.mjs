import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const base='http://127.0.0.1:8097', database=process.env.STUDY_MONITOR_TEST_DB;
assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,`field ${n}`);return m[1];};
const csrf=b=>field(b,'study_csrf_token');
async function login(name){const req=client(),f=await req('/masuk');assert.equal((await req('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:csrf(f.body)})).status,303);return req;}
const admin=await login('monitor1'),child=await login('monitor3'),parent=await login('monitor2'),other=await login('monitor4');
assert.equal((await client()('/belajar/arena')).status,307);
for(const req of [child,parent,other]) assert.equal((await req('/kelola/arena')).status,403);
assert.equal((await child('/belajar/arena')).status,200);
assert.equal(sql("SELECT COUNT(*) FROM st_arena_packs WHERE game_type IN ('memory','word','math');"),'37');
async function start(req,slug,mode='calm'){
 const f=await req('/belajar/arena/pilih/'+slug),data={start_token:field(f.body,'start_token'),mode,study_csrf_token:csrf(f.body)};
 const result=await req('/belajar/arena/pilih/'+slug,data);assert.equal(result.status,303,`${slug}: ${result.body.slice(-500)}`);
 const id=Number(result.location.match(/main\/(\d+)/)[1]);
 const refresh=await req('/belajar/arena/pilih/'+slug);
 const again=await req('/belajar/arena/pilih/'+slug,{...data,study_csrf_token:csrf(refresh.body)});assert.equal(again.location,result.location,'Start replay');
 return id;
}
async function act(req,id,action,value='',revision){const f=await req('/belajar/arena/main/'+id);return req('/belajar/arena/main/'+id,{revision:revision||field(f.body,'revision'),action,value,study_csrf_token:csrf(f.body)});}
const row=id=>JSON.parse(sql(`SELECT JSON_OBJECT('snapshot',snapshot_json,'state',state_json,'status',status,'count',question_count,'correct',correct_count,'revision',revision) FROM st_arena_runs WHERE id=${id};`));
const id=await start(child,'kata-dasar-1');
assert.equal(await start(child,'hitung-1'),id,'One running game per user');
assert.equal((await other('/belajar/arena/main/'+id)).status,404);
assert.equal((await parent('/belajar/arena/main/'+id)).status,404,'Parent cannot mutate child run');
assert.equal((await child('/belajar/arena/main/'+id,{revision:'1',action:'answer',value:'BOLA'})).status,403,'CSRF');
let snapshot=JSON.parse(row(id).snapshot);
let page=await child('/belajar/arena/main/'+id);
assert.ok(!page.body.includes('snapshot_json')&&!page.body.includes('"term"'),'No answer payload');
assert.equal((await act(child,id,'answer','<bad>')).status,303,'Answer text safely stored');
assert.equal((await act(child,id,'answer','WRONG','1')).status,409,'Stale tab');
for(let i=1;i<snapshot.items.length;i++) assert.equal((await act(child,id,'answer',snapshot.items[i].term)).status,303);
assert.equal(row(id).status,'completed');assert.equal(Number(row(id).correct),snapshot.items.length-1);
page=await child('/belajar/arena/main/'+id);assert.ok(page.body.includes('&lt;BAD&gt;'));assert.ok(!page.body.includes('<BAD>'));
const finalRevision=row(id).revision;await act(child,id,'answer','tampered-score','1');assert.equal(row(id).revision,finalRevision,'Final replay immutable');
assert.equal((await parent('/keluarga/anak/3/arena')).status,200);
assert.equal((await parent('/keluarga/anak/4/arena')).status,404);
sql("UPDATE st_family_links SET status='revoked' WHERE parent_user_id=2 AND child_user_id=3;");
assert.equal((await parent('/keluarga/anak/3/arena')).status,404);
sql("UPDATE st_family_links SET status='active' WHERE parent_user_id=2 AND child_user_id=3;");
const memory=await start(child,'pasangan-dasar-3');snapshot=JSON.parse(row(memory).snapshot);
const pairs=new Map();snapshot.board.forEach((card,i)=>pairs.set(card.pair,[...(pairs.get(card.pair)||[]),i]));
page=await child('/belajar/arena/main/'+memory);assert.ok(!page.body.includes('name="pair"'));
const first=[...pairs.values()][0];assert.equal((await act(child,memory,'flip',String(first[0]))).status,303);
assert.equal((await act(child,memory,'flip',String(first[0]))).status,400,'Cannot flip same card twice');
assert.equal((await act(child,memory,'flip',String(first[1]))).status,303);
for(const pair of [...pairs.values()].slice(1)) for(const index of pair) assert.equal((await act(child,memory,'flip',String(index))).status,303);
assert.equal(row(memory).status,'completed');
const math=await start(child,'hitung-1','timed');snapshot=JSON.parse(row(math).snapshot);
assert.equal((await act(child,math,'answer','abc')).status,400);
sql(`UPDATE st_arena_runs SET deadline_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=${math};`);
assert.equal((await act(child,math,'answer',snapshot.items[0].term)).status,303);assert.equal(Number(row(math).correct),0);
sql("UPDATE st_users SET learning_stage='early_childhood' WHERE id=3;");
page=await child('/belajar/arena/pilih/hitung-1');assert.ok(!page.body.includes('value="timed"'));
assert.equal((await child('/belajar/arena/pilih/hitung-1',{start_token:field(page.body,'start_token'),mode:'timed',study_csrf_token:csrf(page.body)})).status,400,'TK timer denied server-side');
const tk=await start(child,'kata-dasar-1');assert.equal(Number(row(tk).count),5);await act(child,tk,'stop');
sql("UPDATE st_users SET learning_stage='primary' WHERE id=3;");
// Admin availability: persisted, protected, optimistic conflict, audit rollback.
const pack=Number(sql("SELECT id FROM st_arena_packs WHERE slug='hitung-1';"));
async function toggle(enabled,revision){const f=await admin('/kelola/arena');return admin('/kelola/arena',{pack_id:String(pack),revision:revision||sql(`SELECT revision FROM st_arena_packs WHERE id=${pack}`),enabled,study_csrf_token:csrf(f.body)});}
const live=await start(child,'hitung-1');assert.equal((await toggle('0')).status,303);
assert.equal((await act(child,live,'answer','1')).status,403);assert.equal((await act(child,live,'stop')).status,303);
assert.equal((await toggle('1','1')).status,409);
const before=sql(`SELECT CONCAT(enabled,':',revision) FROM st_arena_packs WHERE id=${pack}`);
try {sql("CREATE TRIGGER qa_arena_audit_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA audit failure';");assert.equal((await toggle('1')).status,503);assert.equal(sql(`SELECT CONCAT(enabled,':',revision) FROM st_arena_packs WHERE id=${pack}`),before);}finally{sql('DROP TRIGGER IF EXISTS qa_arena_audit_failure;');}
assert.equal((await toggle('1')).status,303);
// Simulated save failure must retain cursor/score/revision.
const rollback=await start(child,'word-sd-1');const old=row(rollback);
try {sql("CREATE TRIGGER qa_arena_save_failure BEFORE UPDATE ON st_arena_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA save failure';");assert.equal((await act(child,rollback,'answer','BUKU')).status,503);assert.deepEqual(row(rollback),old);}finally{sql('DROP TRIGGER IF EXISTS qa_arena_save_failure;');}
await act(child,rollback,'stop');
for(const path of ['/kelola/pembelajaran/modul/arena','/kelola/pembelajaran/pengguna/3?tab=arena','/kelola/rapor/3']) assert.equal((await admin(path)).status,200,path);
const report=await admin('/kelola/rapor/3');assert.ok(report.body.includes('Arena Belajar (di luar kursus)'),'Arena included in report');
assert.ok(/Nilai terbaik: <strong>80(?:\.00)?%/.test(report.body),'Game scores do not replace course quiz scores');
const details=await admin('/kelola/pembelajaran/pengguna/3?tab=arena');assert.ok(details.body.includes('Benda di Sekitarku'),'Real arena activity in monitoring');
async function settings(values){const f=await admin('/kelola/pengaturan/arena');return admin('/kelola/pengaturan/arena',{enabled:'1',pairs:'6',words:'10',cards:'15',timer:'1',expedition_pass:'80',...values,revision:field(f.body,'revision'),study_csrf_token:csrf(f.body)});}
assert.equal((await settings({pairs:'100'})).status,400);
assert.equal((await settings({enabled:'0'})).status,303);
page=await child('/belajar/arena/pilih/word-sd-1');assert.ok(!page.body.includes('name="start_token"'),'Paused catalog cannot start');
assert.equal((await child('/belajar/arena/riwayat')).status,200,'Pause retains history');
assert.equal((await settings({words:'5',timer:'0'})).status,303);
const short=await start(child,'word-sd-1');assert.equal(Number(row(short).count),5);await act(child,short,'stop');
assert.equal((await settings({})).status,303);
console.log('PASS Arena HTTP: catalog, word/memory/math gameplay, resume, replay, CSRF/ownership, stale tabs, escaping, timer, TK, family revocation, admin toggle/audit rollback, progress save rollback and monitoring routes.');
