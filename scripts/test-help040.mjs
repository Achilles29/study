import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
import {readFileSync,readdirSync} from 'node:fs';
const db=process.env.STUDY_MONITOR_TEST_DB;
assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097',password='Monitor-QA-Only-2026!';
function client(){const jar=new Map();return async(path,data,method)=>{const r=await fetch(base+path,{redirect:'manual',method:method||(data?'POST':'GET'),headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return{status:r.status,body,location:r.headers.get('location'),headers:r.headers};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
const fresh=()=>randomBytes(32).toString('hex');
async function send(c,path,data,from=path){const p=await c(from);assert.equal(p.status,200,from);return c(path,{request_key:field(p.body,'request_key'),...data,study_csrf_token:field(p.body,'study_csrf_token')});}
async function login(id){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:'monitor'+id,password,study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
const root=await login(1),parent=await login(2),other=await login(19),editor=await login(6),child=await login(4),guest=client();
const initial={subject:'QA help '+Date.now(),body:'Saya memerlukan bantuan pendaftaran akun. <script>alert(1)</script>',category:'privacy',consent:'1',website:''};
assert.equal((await guest('/bantuan',initial)).status,403);
assert.equal((await guest('/help/actor')).status,404);
assert.equal((await guest('/bantuan',undefined,'PUT')).status,405);
assert.equal((await child('/bantuan')).status,403);
assert.equal((await parent('/kelola/bantuan')).status,403);
assert.equal((await editor('/help/manage')).status,403);
for(const data of [{subject:'x'},{body:'x'},{consent:'0'},{website:'spam'},{category:'invalid'},{'body[]':'x',body:undefined}]){const payload={...initial,...data};if(payload.body===undefined)delete payload.body;assert.equal((await send(guest,'/bantuan',payload)).status,400);}
const request_key=fresh(),created=await send(guest,'/bantuan',{...initial,request_key});assert.equal(created.status,303);const path=new URL(created.location).pathname,id=Number(path.split('/').at(-1));
const first=await guest(path);assert.equal(first.status,200);assert.match(first.headers.get('cache-control'),/no-store/);assert.equal(first.headers.get('referrer-policy'),'no-referrer');assert.ok(first.body.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));assert.ok(!first.body.includes('<script>alert(1)</script>'));
const code=first.body.match(/id="help-code"[^>]*>([^<]+)</)[1];assert.match(code,/^[0-9]+\.[a-f0-9]{64}$/);assert.ok(!created.location.includes(code));
assert.equal((await send(guest,'/bantuan',{...initial,request_key})).location,created.location);assert.equal(sql('SELECT COUNT(*) FROM st_help_messages WHERE ticket_id='+id),'1');
assert.equal((await other(path)).status,404);assert.equal((await client()(path)).status,404);
const restored=client();let p=await restored('/bantuan');assert.equal((await restored('/bantuan/buka',{code,study_csrf_token:field(p.body,'study_csrf_token')})).location,created.location);assert.equal((await restored(path)).status,200);
p=await root('/kelola/bantuan/'+id);assert.equal(p.status,200);const adminRevision=field(p.body,'revision');
assert.equal((await send(root,'/kelola/bantuan/'+id,{body:'Silakan coba lagi sekarang.',revision:adminRevision,status:'waiting'})).status,303);
assert.ok((await restored(path)).body.includes('Silakan coba lagi sekarang.'));
const stale=await send(restored,path,{body:'Pesan ini memakai revisi lama.',revision:'1'});assert.equal(stale.status,409);assert.equal(field(stale.body,'revision'),'1');assert.ok(stale.body.includes('disabled'));
p=await restored(path);const rev=field(p.body,'revision'),replyKey=fresh(),payload={body:'Sekarang sudah berhasil, terima kasih.',revision:rev,request_key:replyKey};
assert.equal((await send(restored,path,payload)).status,303);assert.equal((await send(restored,path,payload)).status,303);assert.equal(sql('SELECT COUNT(*) FROM st_help_messages WHERE ticket_id='+id),'3');
const owned=await send(parent,'/bantuan',{...initial,subject:'Pesan privat akun keluarga'});assert.equal(owned.status,303);const ownPath=new URL(owned.location).pathname,ownId=Number(ownPath.split('/').at(-1));assert.equal((await other(ownPath)).status,404);assert.equal((await guest(ownPath)).status,404);assert.equal((await parent(ownPath)).status,200);assert.equal(sql('SELECT access_hash IS NULL FROM st_help_tickets WHERE id='+ownId),'1');
const before=sql('SELECT COUNT(*) FROM st_help_messages WHERE ticket_id='+id);
sql("DELIMITER //\nCREATE TRIGGER qa_help040_fail BEFORE INSERT ON st_audit_events FOR EACH ROW BEGIN IF NEW.event_type='help.replied' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA rollback'; END IF; END//\nDELIMITER ;");
try{p=await restored(path);assert.equal((await send(restored,path,{body:'Balasan harus dibatalkan saat audit gagal.',revision:field(p.body,'revision')})).status,503);assert.equal(sql('SELECT COUNT(*) FROM st_help_messages WHERE ticket_id='+id),before);}finally{sql('DROP TRIGGER qa_help040_fail');}
// Two independent sessions with the same guest grant race on the same revision.
const second=client();p=await second('/bantuan');await second('/bantuan/buka',{code,study_csrf_token:field(p.body,'study_csrf_token')});const forms=await Promise.all([restored(path),second(path)]);const raced=await Promise.all([restored,second].map((c,i)=>c(path,{body:'Balasan paralel '+i,revision:field(forms[i].body,'revision'),request_key:field(forms[i].body,'request_key'),study_csrf_token:field(forms[i].body,'study_csrf_token')})));assert.deepEqual(raced.map(r=>r.status).sort(),[303,409]);assert.equal(Number(sql('SELECT COUNT(*) FROM st_help_messages WHERE ticket_id='+id)),Number(before)+1);
p=await root('/kelola/bantuan/'+id);assert.equal((await send(root,'/kelola/bantuan/'+id,{body:'Permintaan sudah ditangani dan ditutup.',revision:field(p.body,'revision'),status:'closed'})).status,303);
const closed=await restored(path);assert.ok(!closed.body.includes('name="revision"'));assert.equal((await restored(path,{body:'Tidak boleh masuk setelah ditutup.',revision:sql('SELECT revision FROM st_help_tickets WHERE id='+id),request_key:fresh(),study_csrf_token:field((await restored('/bantuan')).body,'study_csrf_token')})).status,409);
p=await root('/kelola/bantuan/'+id);assert.equal((await send(root,'/kelola/bantuan/'+id,{body:'Dibuka kembali untuk pengecekan.',revision:field(p.body,'revision'),status:'open'})).status,303);
assert.equal((await root('/kelola/bantuan?status=closed')).status,200);assert.equal((await root('/kelola/bantuan?halaman[]=1&status[]=x')).status,200);
assert.equal(sql("SELECT COUNT(*) FROM st_audit_events WHERE entity_type='help_ticket' AND details_json LIKE '%"+code.split('.')[1]+"%'"),'0');
// Rate limit uses hashed buckets, no raw address. Fill only this isolated QA table.
sql('UPDATE st_help_limits SET hits=1000');assert.equal((await send(guest,'/bantuan',initial)).status,409);
sql('UPDATE st_help_limits SET hits=0');
const marker='PrivateMarker'+fresh(),ticketCount=sql('SELECT COUNT(*) FROM st_help_tickets');
sql("CREATE TRIGGER qa_help040_body_fail BEFORE INSERT ON st_help_messages FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA private insert failed'");
try{assert.equal((await send(guest,'/bantuan',{...initial,body:marker})).status,503);assert.equal(sql('SELECT COUNT(*) FROM st_help_tickets'),ticketCount);}finally{sql('DROP TRIGGER qa_help040_body_fail');}
const logs=readdirSync('application/logs').filter(f=>f.endsWith('.php')).map(f=>readFileSync('application/logs/'+f,'utf8')).join('\n');assert.ok(!logs.includes(marker),'No private message in failed SQL logs');assert.ok(logs.includes('Help database operation failed; SQL and correspondence omitted.'));
for(let i=0;i<21;i++)sql(`INSERT INTO st_help_messages(ticket_id,body,request_hash,created_at) VALUES(${id},'Pagination QA ${i}','${fresh()}',UTC_TIMESTAMP())`);
assert.ok((await restored(path+'?halaman=2')).body.includes('Halaman 2 dari 2'));
assert.equal((await restored(path+'?halaman[]=2')).status,200);
p=await guest('/bantuan');await guest('/bantuan/buka',{code:id+'.'+fresh(),study_csrf_token:field(p.body,'study_csrf_token')});assert.ok((await guest('/bantuan')).body.includes('Kode tidak dapat dibuka'));
console.log('PASS help: guest/account creation, recovery, admin reply/status, private scope/child/role/direct-method/CSRF/type/XSS guards, replay, conflict, parallel race, closed/reopen, audit rollback, message-insert rollback/log redaction, pagination and throttle; no real users or emails.');
