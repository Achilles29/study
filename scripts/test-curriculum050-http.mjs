import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const courses=sql("SELECT id,slug FROM st_courses WHERE slug IN ('matematika-kelas-1-operasi-bentuk-pengukuran-data','bahasa-inggris-kelas-1-pengenalan-sehari-hari') ORDER BY id").split('\n').map(s=>s.split('\t'));
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body};};}
async function login(name){const c=client(),r=await c('/masuk'),csrf=r.body.match(/name="study_csrf_token" value="([^"]+)"/)[1];assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:csrf})).status,303);return c;}
const admin=await login('monitor1'),parent=await login('monitor2'),guest=client(),first=[];
assert.equal((await guest('/cli/import_curriculum/index/050/apply/1')).status,404);
for(const [id,slug] of courses){
 assert.equal((await guest('/kursus/'+slug)).status,404);assert.equal((await parent('/kelola/kursus/'+id)).status,403);
 const detail=await admin('/kelola/kursus/'+id);assert.equal(detail.status,200);assert.ok(detail.body.includes('Bab 6.'));
 const listed=await admin('/kelola/kursus?kelas=sd_1');assert.ok(listed.body.includes(slug)||listed.body.includes(`/kelola/kursus/${id}`));
 const lessons=sql(`SELECT l.id,l.slug FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${id} ORDER BY s.sort_order,l.sort_order`).split('\n').map(s=>s.split('\t'));assert.equal(lessons.length,12);
 first.push(`/kelola/kursus/${id}/pelajaran/${lessons[0][0]}/pratinjau`);
 for(const [lid,lslug] of lessons){
  assert.equal((await guest('/materi/'+lslug)).status,404);assert.equal((await parent(`/kelola/kursus/${id}/pelajaran/${lid}/pratinjau`)).status,403);
  const r=await admin(`/kelola/kursus/${id}/pelajaran/${lid}/pratinjau`);assert.equal(r.status,200);assert.ok(r.body.includes('<h2>Tujuan belajar</h2>'));assert.ok(r.body.includes('<h2>Cek dan ceritakan</h2>'));assert.ok(!r.body.includes('id="complete-reading-lesson"'));
 }
 assert.equal((await admin(`/kelola/kursus/${id}/soal`)).status,200);
}
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor1');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){await p.setViewport({width,height:900});for(const path of first){assert.equal((await p.goto(base+path)).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.ok(await p.$$eval('.lesson-content h2',hs=>hs.length>=5&&hs.every(h=>parseInt(getComputedStyle(h).fontWeight)>=700)));await p.focus('#preview-theme');await p.keyboard.press('Tab');assert.equal(await p.evaluate(()=>document.activeElement.textContent.trim()),'Terapkan tema');await p.screenshot({path:`/tmp/study-curriculum050-${first.indexOf(path)}-${width}.png`,fullPage:true});}}
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
console.log('PASS all24 draft previews/body headings, public/parent denial, class list and question admin; desktop1280/mobile360 no overflow/JS errors, keyboard.');
