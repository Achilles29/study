// Targeted, isolated QA. No production writes or real payment requests.
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097';
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(body,n)=>{const m=body.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(name,password='Monitor-QA-Only-2026!'){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password,study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const suffix=Date.now(),name='qa063_'+suffix;
sql(`INSERT INTO st_users(username,email,password_hash,display_name,account_kind,learning_stage,status,email_verified_at,created_at,updated_at) SELECT '${name}','${name}@example.test',password_hash,'QA 063 Parent','parent','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users WHERE id=2`);
const parent=await login(name),admin=await login('monitor1'),other=await login('monitor2');
const pid=Number(sql(`SELECT id FROM st_users WHERE username='${name}'`));
let r=await parent('/keluarga/anak/tambah');assert.equal(r.status,200);assert.ok(r.body.includes('name="grade_id"'));
const childname='child063_'+suffix;
const payload={username:childname,display_name:'QA child 063',password:'a',password_confirm:'a',grade_id:'8'};
assert.equal((await parent('/keluarga/anak/tambah',payload)).status,403);
r=await parent('/keluarga/anak/tambah');
let bad=await parent('/keluarga/anak/tambah',{...payload,grade_id:'999999',study_csrf_token:field(r.body,'study_csrf_token')});assert.ok(bad.body.includes('Pilih kelas anak'));
assert.equal(sql(`SELECT COUNT(*) FROM st_users WHERE username='${childname}'`),'0');
r=await parent('/keluarga/anak/tambah');assert.ok([302,303].includes((await parent('/keluarga/anak/tambah',{...payload,study_csrf_token:field(r.body,'study_csrf_token')})).status));
const cid=Number(sql(`SELECT id FROM st_users WHERE username='${childname}'`));assert.ok(cid>0);
assert.equal(sql(`SELECT CONCAT(grade_id,':',learning_stage) FROM st_users WHERE id=${cid}`),'8:primary');
const child=await login(childname,'a');
for(const path of ['/kursus','/latihan/kursus']){r=await child(path);assert.equal(r.status,200);assert.ok(r.body.includes('value="sd_6" selected'));assert.ok(!r.body.includes('Matematika Kelas 1'));
 r=await child(path+'?kelas=sd_1');assert.equal(r.status,200);assert.ok(r.body.includes('Matematika Kelas 1'));
 r=await child(path+'?kelas=&halaman=2');assert.equal(r.status,200);assert.ok(r.body.includes('value="" selected'));}
r=await other('/keluarga/anak');await other(`/keluarga/anak/${cid}/kelompok`,{grade_id:'3',study_csrf_token:field(r.body,'study_csrf_token')});assert.equal(sql(`SELECT grade_id FROM st_users WHERE id=${cid}`),'8','Cross-family grade write denied');
assert.equal((await child(`/keluarga/anak/${cid}/kelompok`,{grade_id:'3'})).status,403);
r=await parent('/keluarga/anak');assert.equal((await parent(`/keluarga/anak/${cid}/kelompok`,{grade_id:'14',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);
assert.equal(sql(`SELECT CONCAT(grade_id,':',learning_stage) FROM st_users WHERE id=${cid}`),'14:secondary');
r=await child('/kursus');assert.ok(r.body.includes('value="sma_12" selected'),'Grade refresh without logout');
r=await parent('/keluarga/anak');await parent(`/keluarga/anak/${cid}/kelompok`,{grade_id:'3',study_csrf_token:field(r.body,'study_csrf_token')});
const [course,slug]=sql("SELECT id,slug FROM st_courses WHERE status='published' AND grade_id=3 ORDER BY id LIMIT 1").split('\t');
r=await child('/latihan/kursus/'+course);assert.equal(r.status,200);assert.ok(r.body.includes('name="start_token"'));
r=await child('/kursus/'+slug);assert.ok(r.body.includes('Latihan kursus ·'));
assert.equal((await parent('/kelola/trafik/log')).status,403);
r=await admin('/kelola/trafik/log?from=');assert.equal(r.status,200);assert.ok(r.body.includes('traffic-log-table'));assert.ok(r.body.includes('scope="col">IP jaringan'));
assert.equal((await admin('/kelola/trafik/log?from=not-a-date')).status,400);
assert.equal(sql("SELECT COUNT(*) FROM st_courses WHERE is_free=0"),'0');
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 async function browserLogin(identity,password='Monitor-QA-Only-2026!'){await p.deleteCookie(...await p.cookies());await p.goto(base+'/masuk');await p.type('[name=identity]',identity);await p.type('[name=password]',password);await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);}
 await browserLogin('monitor1');for(const width of [1280,360]){await p.setViewport({width,height:900});assert.equal((await p.goto(base+'/kelola/trafik/log?from=')).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await p.focus('.traffic-log-scroll');await p.keyboard.press('ArrowRight');assert.ok(await p.$eval('.traffic-log-scroll',e=>e===document.activeElement));await p.screenshot({path:`/tmp/study063-traffic-${width}.png`,fullPage:true});}
 await browserLogin(name);for(const width of [1280,360]){await p.setViewport({width,height:900});for(const path of ['/keluarga/anak','/latihan/kursus','/beranda']){assert.equal((await p.goto(base+path)).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path+' width '+width);}await p.goto(base+'/latihan/kursus');await p.screenshot({path:`/tmp/study063-practice-${width}.png`,fullPage:true});}assert.deepEqual(errors,[]);
}finally{await browser.close();}
console.log('PASS 063: free courses/quiz discovery, grade create+update+default+other filters, weak child password preserved, CSRF, cross-family isolation, traffic rows/date/permissions, desktop+mobile+keyboard. QA fixture parent '+pid+' child '+cid);
