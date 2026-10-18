import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const password='Parent-QA-Only-2026!',prefix='qa046_'+Date.now(),base='http://127.0.0.1:8097',path='/keluarga/anak/tambah';
const hash=execFileSync('/www/server/php/84/bin/php',['-r','echo password_hash("Parent-QA-Only-2026!", PASSWORD_DEFAULT);'],{encoding:'utf8'});
for(const suffix of ['parent','other'])sql(`INSERT INTO st_users(username,email,password_hash,display_name,account_kind,learning_stage,status,email_verified_at,created_at,updated_at) VALUES('${prefix}_${suffix}','${prefix}_${suffix}@example.invalid','${hash}','QA child password','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
const parentId=sql(`SELECT id FROM st_users WHERE username='${prefix}_parent'`),count=()=>sql(`SELECT COUNT(*) FROM st_family_links WHERE parent_user_id=${parentId}`);
function client(){const jar=new Map();return async(p,data)=>{const r=await fetch(base+p,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body));return {status:r.status,body};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(name,pw){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:pw,study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const parent=await login(prefix+'_parent',password),other=await login(prefix+'_other',password);
async function post(c,p,data,status=303){const r=await c(p);assert.equal(r.status,200);const out=await c(p,{...data,study_csrf_token:field(r.body,'study_csrf_token')});assert.equal(out.status,status,p);return out;}
const data={username:prefix+'_child',display_name:'QA Anak',learning_stage:'primary',password:'7',password_confirm:'7'};
assert.equal((await parent(path,data)).status,403);
for(const pw of ['', 'x'.repeat(73),'😀'.repeat(19)]){await post(parent,path,{...data,password:pw,password_confirm:pw},200);assert.equal(count(),'0');}
await post(parent,path,{...data,password_confirm:'different'},200);await post(parent,path,{...data,password:undefined,'password[]':'7'},200);assert.equal(count(),'0');
sql("CREATE TRIGGER qa_child046_audit_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA rollback'");try{await post(parent,path,data,200);assert.equal(count(),'0');assert.equal(sql(`SELECT COUNT(*) FROM st_users WHERE username='${data.username}'`),'0');}finally{sql('DROP TRIGGER qa_child046_audit_failure');}
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]',prefix+'_parent');await p.type('[name=password]',password);await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){await p.setViewport({width,height:900});assert.equal((await p.goto(base+path)).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.equal(await p.$eval('#password',e=>e.hasAttribute('minlength')),false);assert.equal(await p.$eval('#password',e=>e.closest('.mb-3').querySelector('.form-text')),null);await p.screenshot({path:'/tmp/study-child046-'+width+'.png',fullPage:true});}
 await p.type('#display_name',data.display_name);await p.select('#learning_stage','primary');await p.type('#username',data.username);await p.type('#password','7');await p.type('#password_confirm','7');await p.focus('form[action$="/tambah"] button[type=submit]');await Promise.all([p.waitForNavigation(),p.keyboard.press('Enter')]);assert.equal(new URL(p.url()).pathname,'/keluarga/anak');assert.equal(count(),'1');assert.deepEqual(errors,[]);
}finally{await browser.close();}
const id=sql(`SELECT id FROM st_users WHERE username='${data.username}'`),child=await login(data.username,'7');assert.equal((await child(path)).status,403);
await post(parent,path,{...data,username:prefix+'_letters',password:'budi',password_confirm:'budi'});await login(prefix+'_letters','budi');assert.equal(count(),'2');await post(parent,path,{...data,username:prefix+'_overflow'},200);assert.equal(count(),'2');
const reset='/keluarga/anak/'+id+'/pulihkan-sandi';assert.equal((await other(reset)).status,404);
const version=sql('SELECT auth_version FROM st_users WHERE id='+id),resetData={version,new_password:'1234',password_confirm:'1234',current_password:password,confirm:'1'};
await post(parent,reset,{...resetData,new_password:'',password_confirm:''},400);await post(parent,reset,resetData);await post(parent,reset,resetData,409);assert.ok([302,303,307].includes((await child('/beranda')).status));
const recovered=await login(data.username,'1234');await post(recovered,'/akun/keamanan',{current_password:'1234',new_password:'abc',password_confirm:'abc'});await login(data.username,'abc');await post(parent,'/akun/keamanan',{current_password:password,new_password:'abc',password_confirm:'abc'},400);
console.log('PASS child password: one digit/alphabet-only creation+login, reset and self-change; parent minimum preserved; empty/array/mismatch/byte limits/CSRF/roles/family isolation/slot limit/audit rollback/stale reset/session revocation; browser1280/360 keyboard and no helper text. QA only.');
