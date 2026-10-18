import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readdirSync, readFileSync, writeFileSync, unlinkSync} from 'node:fs';
const db=process.env.STUDY_MONITOR_TEST_DB,spool=process.env.STUDY_MAIL_TEST_DIRECTORY;
assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);assert.match(spool||'',/^\/tmp\/study-mail-qa-[a-zA-Z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const password='Monitor-QA-Only-2026!',secret='Domain-QA only-!Secret2026',path='/kelola/pengaturan/email';
function client(){const jar=new Map();return async(p,data)=>{const r=await fetch('http://127.0.0.1:8097'+p,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const a=c.split(';')[0],i=a.indexOf('=');jar.set(a.slice(0,i),a.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body));assert.ok(!body.includes(secret));return {status:r.status,body,location:r.headers.get('location')};};}
const field=(s,n)=>{const m=s.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(id){const r=client(),p=await r('/masuk');assert.equal((await r('/masuk',{identity:'monitor'+id,password,study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return r;}
async function post(r,p,data,status=303){const page=await r(p);assert.equal(page.status,200);const out=await r(p,{...data,study_csrf_token:field(page.body,'study_csrf_token')});assert.equal(out.status,status,p);return out;}
const admin=await login(1),user=await login(10),editor=await login(6),anon=client();
const revision=()=>sql('SELECT revision FROM st_mail_config WHERE id=1');
const state=()=>sql('SELECT CONCAT(provider,":",sender_email,":",revision,":",enabled,":",COALESCE(credentials_cipher,"")) FROM st_mail_config WHERE id=1');
const snapshot=()=>sql('SELECT * FROM st_mail_profiles ORDER BY provider');
const cool=()=>sql('UPDATE st_mail_deliveries SET created_at=UTC_TIMESTAMP()-INTERVAL 2 DAY');
const smtp={action:'save',provider:'smtp',sender_email:'noreply@example.com',smtp_host:'smtp.example.com',smtp_port:'587',smtp_username:'noreply@example.com',app_password:secret,enabled:'0',current_password:password};
async function save(extra={},status=303){return post(admin,path,{...smtp,revision:revision(),...extra},status);}
assert.equal((await user(path)).status,403);assert.equal((await editor(path)).status,403);assert.equal((await admin(path,smtp)).status,403);
const gmailBefore=sql("SELECT COALESCE(credentials_cipher,'') FROM st_mail_profiles WHERE provider='gmail'");
const unchanged=state();await admin(path+'?provider=smtp');assert.equal(state(),unchanged,'GET never switches');
await save({current_password:'wrong-password-2026'},403);
for(const extra of [{smtp_host:'127.0.0.1'},{smtp_host:'localhost'},{smtp_host:'https://smtp.example.com'},{smtp_host:'mail.internal'},{smtp_port:'25'},{smtp_port:'465junk'},{smtp_username:'bad\r\nname'},{sender_email:'bad\r\n@example.com'},{provider:'other'}])await save(extra,400);
await save({enabled:'1',inbox_confirm:'1'},400);await save();assert.ok(!state().includes(secret));
assert.equal(sql("SELECT credentials_cipher FROM st_mail_profiles WHERE provider='gmail'"),gmailBefore,'Gmail secret preserved');
assert.equal(sql('SELECT provider FROM st_mail_config'),'smtp');await save({app_password:'',enabled:'1',inbox_confirm:'1'},400);
const old=state(),profiles=snapshot();sql("CREATE TRIGGER qa_domain_mail_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA audit failure'");
try{await save({smtp_port:'465',app_password:''},503);assert.equal(state(),old);assert.equal(snapshot(),profiles);}finally{sql('DROP TRIGGER qa_domain_mail_audit_fail');}
await save({revision:'1'},409);await save({smtp_host:'other.example.com',app_password:''},400);
cool();await post(admin,path,{action:'probe',revision:revision(),current_password:password});
assert.equal(sql("SELECT probe_status FROM st_mail_profiles WHERE provider='smtp'"),'sent');
await save({app_password:'',enabled:'1',inbox_confirm:'1'});
cool();let seen=new Set(readdirSync(spool));await post(user,'/akun/email',{email:'qa-domain-ten@example.com',current_password:password});
let added=readdirSync(spool).filter(f=>f.endsWith('.json')&&!seen.has(f));assert.equal(added.length,1);let token=JSON.parse(readFileSync(spool+'/'+added[0],'utf8')).body.match(/#token=([a-f0-9]{64})/)[1];
// Delivered proofs remain valid across a provider switch; not tied to provider credentials.
await save({provider:'gmail',sender_email:'projectpintar.qa@gmail.com',app_password:'abcdefghijklmnop',enabled:'0'});
assert.equal(sql("SELECT sender_email FROM st_mail_profiles WHERE provider='smtp'"),'noreply@example.com');
await post(user,'/akun/verifikasi',{token});await post(user,'/akun/verifikasi',{token},400);
assert.equal(sql('SELECT email FROM st_users WHERE id=10'),'qa-domain-ten@example.com');
await save({app_password:'',enabled:'1',inbox_confirm:'1'},400);await save({app_password:''});
assert.equal(sql('SELECT probe_status FROM st_mail_config'),'untested','Switch requires reprobe');
cool();writeFileSync(spool+'/fail','QA');try{await post(admin,path,{action:'probe',revision:revision(),current_password:password},503);}finally{unlinkSync(spool+'/fail');}
await save({app_password:'',enabled:'1',inbox_confirm:'1'},400);
// 465 TLS profile, same stored SMTP secret, needs a fresh test.
await save({app_password:'',smtp_port:'465'});cool();await post(admin,path,{action:'probe',revision:revision(),current_password:password});
await save({app_password:'',smtp_port:'465',enabled:'1',inbox_confirm:'1'});
cool();seen=new Set(readdirSync(spool));await post(anon,'/lupa-sandi',{email:'qa-domain-ten@example.com'});added=readdirSync(spool).filter(f=>f.endsWith('.json')&&!seen.has(f));assert.equal(added.length,1);token=JSON.parse(readFileSync(spool+'/'+added[0],'utf8')).body.match(/#token=([a-f0-9]{64})/)[1];
await post(anon,'/pulihkan-sandi',{token,password:'Domain-reset-QA-2026!',password_confirm:'Domain-reset-QA-2026!'});await post(anon,'/pulihkan-sandi',{token,password:'Domain-reset-QA-2026!',password_confirm:'Domain-reset-QA-2026!'},400);
assert.equal((await user('/beranda')).status,303);
assert.equal(sql("SELECT COUNT(*) FROM st_audit_events WHERE details_json LIKE '%Domain-QA only-%' OR details_json LIKE '%#token=%'"),'0');
console.log('PASS dual profiles/preservation, encryption/redaction, role/CSRF/reauth/stale/audit rollback, validation, switch/reprobe/activation, SMTP 587/465 capture, failed delivery, verification across switch, reset/replay/session revocation. QA capture only, not real inbox.');
