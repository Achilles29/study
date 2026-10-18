import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body};};}
const field=(body,n)=>{const m=body.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(name){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const admin=await login('monitor1'),parent=await login('monitor2'),guest=client(),paths=[];
assert.equal((await guest('/cli/publish_curriculum/index/050/apply/1')).status,404);
const courses=sql('SELECT id,slug FROM st_courses WHERE id IN (31,32,33,34) ORDER BY id').split('\n').map(x=>x.split('\t'));
for(const [id,slug] of courses){
 assert.equal((await guest('/kursus/'+slug)).status,200);assert.ok((await guest('/sitemap.xml')).body.includes(slug));
 const lessons=sql(`SELECT l.id,l.slug FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${id} ORDER BY s.sort_order,l.sort_order`).split('\n').map(x=>x.split('\t'));
 for(const [lid,lSlug] of lessons){
  assert.ok([302,303,307].includes((await guest('/materi/'+lSlug)).status));
  const r=await admin(`/kelola/kursus/${id}/pelajaran/${lid}/pratinjau`);assert.equal(r.status,200);assert.ok(r.body.includes('<h2>Cek dan ceritakan</h2>'));
 }
 if(Number(id)>=33){paths.push('/kursus/'+slug,`/kelola/kursus/${id}/pelajaran/${lessons[0][0]}/pratinjau`);}
 assert.equal((await parent('/kelola/kursus/'+id)).status,403);
}
// Isolated QA entitlement only; publication itself must not grant access.
const cid=33,lid=46,lSlug=sql(`SELECT slug FROM st_lessons WHERE id=${lid}`);
assert.ok([302,303,307].includes((await parent('/materi/'+lSlug)).status));
sql(`INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,status,enrolled_at,updated_at) VALUES(2,${cid},'manual','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())`);
const r=await parent('/materi/'+lSlug);assert.equal(r.status,200);assert.ok(r.body.includes('id="complete-reading-lesson"'));
assert.equal((await parent('/materi/'+lSlug+'/selesai',{})).status,403);
const latest=await parent('/materi/'+lSlug);assert.ok([302,303].includes((await parent('/materi/'+lSlug+'/selesai',{study_csrf_token:field(latest.body,'study_csrf_token')})).status));
assert.equal(sql(`SELECT status FROM st_lesson_progress WHERE lesson_id=${lid} AND learner_user_id=2`),'completed');
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor1');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){await p.setViewport({width,height:900});for(const [i,path] of paths.entries()){assert.equal((await p.goto(base+path)).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path);if(path.includes('pratinjau')){await p.focus('#preview-theme');await p.keyboard.press('Tab');assert.equal(await p.evaluate(()=>document.activeElement.textContent.trim()),'Terapkan tema');}await p.screenshot({path:`/tmp/study-curriculum051-${i}-${width}.png`,fullPage:true});}}assert.deepEqual(errors,[]);
}finally{await browser.close();}
console.log('PASS four published catalogs/sitemap, 40 admin previews, paid access/role/CLI protection, QA manual access + completion/CSRF, desktop1280/mobile360 and keyboard.');
