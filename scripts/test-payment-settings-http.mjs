import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const base='http://127.0.0.1:8097', database=process.env.STUDY_MONITOR_TEST_DB;
assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body};};}
const token=b=>{const m=b.match(/name="study_csrf_token" value="([^"]+)"/);assert.ok(m);return m[1];};
const path='/kelola/pengaturan/pembayaran';
async function login(name){const request=client();let f=await request('/masuk');assert.equal((await request('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:token(f.body)})).status,303);return request;}
const admin=await login('monitor1'), editor=await login('monitor6');
assert.equal((await editor(path)).status,403);assert.equal((await editor('/payment_settings/index')).status,403);
let f=await admin(path);assert.equal(f.status,200);
const previous=()=>sql('SELECT COALESCE(MAX(id),0) FROM st_payment_configs WHERE environment=\'sandbox\';');
const payload={action:'save',environment:'sandbox',merchant_id:'QA-ONLY',server_key:'SB-Mid-server-NotARealCredential2026',client_key:'SB-Mid-client-NotARealCredential2026'};
const submit=async(data,id)=>{const page=await admin(path);return admin(path,{...data,previous_id:id??previous(),study_csrf_token:token(page.body)});};
assert.equal((await admin(path,payload)).status,403);
assert.equal((await submit({...payload,environment:'live'})).status,400);
assert.equal((await submit({...payload,server_key:'Mid-server-live-not-allowed'})).status,400);
const badServer=await submit({...payload,server_key:'Mid-server-live-not-allowed'});
assert.ok(badServer.body.includes('server_key-error')&&badServer.body.includes('Server Key harus sesuai lingkungan Sandbox'));
assert.ok(!badServer.body.includes('value="Mid-server-live-not-allowed"'));
const missingServer=await submit({...payload,merchant_id:'QA-NEW-MERCHANT',server_key:'',client_key:''});
assert.equal(missingServer.status,400);assert.ok(missingServer.body.includes('Merchant ID saja belum cukup'));
assert.ok(missingServer.body.includes('value="QA-NEW-MERCHANT"'),'Retain safe merchant input after validation failure');
const misplacedKey=await submit({...payload,merchant_id:payload.server_key});assert.equal(misplacedKey.status,400);
assert.ok(misplacedKey.body.includes('merchant_id-error')&&!misplacedKey.body.includes(payload.server_key),'Never reflect a key pasted in merchant field');
for(const field of ['merchant_id','server_key','client_key']){const bad={...payload};delete bad[field];bad[field+'[]']='bad';assert.equal((await submit(bad)).status,400,'Reject array '+field);}
for(const field of ['server_key','client_key']){const bad=await submit({...payload,[field]:'SB-Mid-'+(field==='server_key'?'client':'server')+'-NotARealCredential2026'});assert.equal(bad.status,400);assert.ok(bad.body.includes(field+'-error'));}
assert.equal((await submit({...payload,merchant_id:'  QA-ONLY \r\n',server_key:' \tSB-Mid-server-QAOnly.With+Safe/Token=2026\r\n',client_key:''})).status,303,'Clipboard padding and opaque token punctuation accepted');
assert.equal(sql('SELECT merchant_id FROM st_payment_configs WHERE environment=\'sandbox\' ORDER BY id DESC LIMIT 1;'),'QA-ONLY');
const firstId=previous();assert.equal((await submit(payload)).status,303);const savedId=previous();
assert.equal((await submit(payload,firstId)).status,409,'Stale version denied');
f=await admin(path);assert.ok(!f.body.includes(payload.server_key)&&!f.body.includes(payload.client_key),'Secrets never rendered');
assert.equal(sql("SELECT COUNT(*) FROM st_payment_configs WHERE credentials_cipher LIKE '%NotARealCredential%';"),'0');
assert.equal(sql("SELECT COUNT(*) FROM st_audit_events WHERE details_json LIKE '%NotARealCredential%';"),'0');
assert.equal((await submit({...payload,server_key:'',client_key:''})).status,303,'Blank preserves encrypted credentials');
assert.notEqual(previous(),savedId,'Rotation creates new immutable version');
assert.equal(sql(`SELECT COUNT(*) FROM st_payment_configs WHERE id=${savedId};`),'1','Old version retained');
assert.equal((await submit({...payload,merchant_id:'OTHER',server_key:'',client_key:''})).status,400);
const count=sql('SELECT COUNT(*) FROM st_payment_configs;');
try{sql("CREATE TRIGGER qa_payment_audit_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA synthetic failure';");assert.equal((await submit(payload)).status,503);assert.equal(sql('SELECT COUNT(*) FROM st_payment_configs;'),count);}finally{sql('DROP TRIGGER IF EXISTS qa_payment_audit_failure;');}
// Real network check is opt-in, sandbox only, fake credential, never a charge.
if(process.env.STUDY_PROBE_NEGATIVE==='1'){
 assert.equal((await submit({action:'probe'})).status,303);
 assert.equal(sql('SELECT probe_result FROM st_payment_configs WHERE environment=\'sandbox\' ORDER BY id DESC LIMIT 1;'),'unauthorized','Fake credential must be rejected');
 assert.equal((await submit({action:'probe'})).status,409,'Probe throttled');
 console.log('PASS sandbox negative API probe: fake key rejected, repeat throttled; no charge or callback change.');
}
for(const request of [admin,editor]){const page=await request('/beranda');await request('/keluar',{study_csrf_token:token(page.body)});}
console.log('PASS payment settings: sandbox-only, role/CSRF/validation, encrypted save, masking/audit, blank preservation, immutable rotation/conflict, atomic rollback.');
