import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.equal(db,'study_qa_monitor_sso039');
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097',password='Monitor-QA-Only-2026!';
function client(){const jar=new Map();return async(path,data,extra={})=>{const r=await fetch(base+path,{redirect:'manual',method:extra.method||(data?'POST':'GET'),headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),'User-Agent':'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/110.0 Mobile Safari/537.36',...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{}),...(extra.headers||{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,headers:r.headers,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(id){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:'monitor'+id,password,study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
const count=()=>Number(sql('SELECT COUNT(*) FROM st_operational_events'));
const saved=sql("SELECT HEX(values_json) FROM st_site_settings WHERE section='privasi'");
const root=await login(1),parent=await login(2),child=await login(4),guest=client();
try{
sql("UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.operational_enabled','0') WHERE section='privasi'");let n=count();await guest('/');assert.equal(count(),n);
sql("UPDATE st_site_settings SET values_json=JSON_SET(values_json,'$.operational_enabled','1') WHERE section='privasi'");
const optional=sql('SELECT COUNT(*) FROM st_traffic_events');
const out=await guest('/?token=OP042_SECRET',undefined,{headers:{'CF-Connecting-IP':'8.8.8.8','CF-IPCountry':'US','X-Forwarded-For':'9.9.9.9'}});assert.equal(out.status,200);assert.equal(count(),n+1);assert.ok(out.headers.getSetCookie().filter(c=>c.startsWith('pp_traffic=')).every(c=>/Max-Age=0/i.test(c)),'No active tracking cookie; existing consent code may clear an expired one');assert.equal(sql('SELECT COUNT(*) FROM st_traffic_events'),optional);
assert.equal(sql("SELECT CONCAT(ip_address,'|',COALESCE(country,'NULL'),'|',device,'|',browser,'|',os) FROM st_operational_events ORDER BY id DESC LIMIT 1"),'127.0.0.1|NULL|Ponsel|Chrome|Android');
assert.ok(!sql('SELECT CONCAT_WS("|",page_key,device,browser,os) FROM st_operational_events').includes('OP042_SECRET'));
n=count();for(const path of ['/masuk','/daftar','/akun/pengaturan','/assets/css/app.css'])await guest(path);await guest('/',undefined,{method:'HEAD'});await guest('/',undefined,{headers:{'X-Requested-With':'XMLHttpRequest'}});await guest('/',undefined,{headers:{'Sec-Fetch-Dest':'empty'}});await root('/beranda');await child('/beranda');await child('/belajar/arena');assert.equal(count(),n,'Sensitive pages, AJAX/HEAD, admin and children excluded');
await parent('/belajar/arena');assert.equal(count(),n+1);assert.equal(sql('SELECT user_id FROM st_operational_events ORDER BY id DESC LIMIT 1'),'2');await guest('/',undefined,{headers:{'User-Agent':'Googlebot'}});assert.equal(sql('SELECT is_bot FROM st_operational_events ORDER BY id DESC LIMIT 1'),'1');
for(const tab of ['ringkasan','halaman','pengguna','log']){const r=await root('/kelola/trafik/'+tab);assert.equal(r.status,200,tab);assert.match(r.headers.get('cache-control'),/no-store/);}
assert.ok(Number(sql("SELECT COUNT(*) FROM st_audit_events WHERE event_type='traffic.operational_viewed'"))>0);
for(const c of [parent,child])assert.equal((await c('/kelola/trafik/log')).status,403);assert.ok([302,303,307].includes((await guest('/kelola/trafik/log')).status));
assert.equal((await root('/traffic/operational')).status,404);
for(const q of ['from[]=x','from=2026-02-31','audience=child','sumber[]=x','sumber=oops','bots=bad','device[]=x','halaman=0','user_id=1%20OR%201=1'])assert.equal((await root('/kelola/trafik?'+q)).status,400,q);
assert.equal((await root('/kelola/trafik/log',{study_csrf_token:field((await root('/kelola')).body,'study_csrf_token')})).status,405);
const settings=await root('/kelola/pengaturan/privasi');assert.ok(settings.body.includes('name="operational_enabled"'));assert.equal((await root('/kelola/pengaturan/privasi',{operational_enabled:'0'})).status,403);
sql("CREATE TRIGGER qa_op042_fail BEFORE INSERT ON st_operational_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA failure'");try{n=count();assert.equal((await guest('/')).status,200);assert.equal(count(),n);}finally{sql('DROP TRIGGER qa_op042_fail');}
sql("CREATE TRIGGER qa_op042_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA audit failure'");try{const r=await root('/kelola/trafik/log');assert.equal(r.status,503);assert.ok(!r.body.includes('IP jaringan')&&!r.body.includes('<code>127.0.0.1</code>'));}finally{sql('DROP TRIGGER qa_op042_audit_fail');}
sql("INSERT INTO st_operational_events(created_at,expires_at,page_key,account_kind,ip_address,device,browser,os) VALUES(UTC_TIMESTAMP()-INTERVAL 8 DAY,UTC_TIMESTAMP()-INTERVAL 1 DAY,'home/index','guest','192.0.2.42','Desktop','Lainnya','Lainnya')");assert.ok(!(await root('/kelola/trafik/log')).body.includes('192.0.2.42'));
const env={...process.env,CI_ENV:'development',DB_HOST:'localhost',DB_PORT:'3308',DB_USERNAME:'root',DB_PASSWORD:'',DB_DATABASE:db,APP_BASE_URL:base+'/'};
const result=execFileSync('/www/server/php/84/bin/php',['-d','mysqli.default_socket=/tmp/mysql-study.sock','index.php','cli/traffic_retention','index','scheduled'],{env,encoding:'utf8'});assert.ok(result.includes('"ok":true'));assert.equal(sql("SELECT COUNT(*) FROM st_operational_events WHERE ip_address='192.0.2.42'"),'0');
assert.equal((await root('/kelola/trafik?sumber=opsional')).status,200);
console.log('PASS operational: enable/off, no consent/cookie required for operational only, spoofed IP/country rejected, normalized metadata/no URL secret, child/admin/sensitive exclusion, bot label, Superadmin/method/CSRF/filter guards, audit fail closed, recording fail open, scheduled expiry, optional analytics unchanged.');
}finally{sql("UPDATE st_site_settings SET values_json=UNHEX('"+saved+"') WHERE section='privasi'");}
