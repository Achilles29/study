// Content-only smoke, reusing the 051 checks without repeating account/payment regression.
import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const batch=process.env.STUDY_CONTENT_BATCH;assert.match(batch,/^\d{3}$/);
const manifests=JSON.parse(execFileSync('/www/server/php/84/bin/php',['-r',"define('BASEPATH',getcwd().'/system/');define('APPPATH',getcwd().'/application/');require APPPATH.'libraries/Curriculum_batch.php';echo json_encode((new Curriculum_batch)->load($argv[1]));",batch],{encoding:'utf8'}));
const base='http://127.0.0.1:8097',sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body};};}
async function login(name){const c=client(),r=await c('/masuk'),token=r.body.match(/name="study_csrf_token" value="([^"]+)"/)[1];assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:token})).status,303);return c;}
const admin=await login('monitor1'),parent=await login('monitor2'),guest=client(),paths=[];let total=0;
const sitemap=(await guest('/sitemap.xml')).body;
for(const manifest of manifests){
 const {slug,sections}=manifest;assert.match(slug,/^[a-z0-9-]+$/);
 const [id,status,free]=sql(`SELECT id,status,is_free FROM st_courses WHERE slug='${slug}'`).split('\t');assert.match(id,/^\d+$/);assert.equal(status,'published');assert.equal(free,'0');
 assert.equal(sql(`SELECT COUNT(*) FROM st_package_courses WHERE course_id=${id}`),'0');
 assert.equal((await guest('/kursus/'+slug)).status,200);assert.ok(sitemap.includes(slug));
 const lessons=sql(`SELECT l.id,l.slug,l.status,l.is_preview FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=${id} ORDER BY s.sort_order,l.sort_order`).split('\n').map(x=>x.split('\t'));
 assert.equal(lessons.length,sections.reduce((n,s)=>n+s.lessons.length,0));
 for(const [lid,lSlug,lstatus,preview] of lessons){total++;assert.equal(lstatus,'published');assert.equal(preview,'0');assert.ok([302,303,307].includes((await guest('/materi/'+lSlug)).status));const r=await admin(`/kelola/kursus/${id}/pelajaran/${lid}/pratinjau`);assert.equal(r.status,200);assert.ok(r.body.includes('<h2>Cek dan ceritakan</h2>'));}
 paths.push('/kursus/'+slug,`/kelola/kursus/${id}/pelajaran/${lessons[0][0]}/pratinjau`);
 assert.equal((await parent('/kelola/kursus/'+id)).status,403);
}
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor1');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){await p.setViewport({width,height:900});for(const [i,path] of paths.entries()){assert.equal((await p.goto(base+path)).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path);if(path.includes('pratinjau')){await p.focus('#preview-theme');await p.keyboard.press('Tab');assert.equal(await p.evaluate(()=>document.activeElement.textContent.trim()),'Terapkan tema');}if(width===360)await p.screenshot({path:`/tmp/study-curriculum${batch}-${i}-${width}.png`,fullPage:true});}}assert.deepEqual(errors,[]);
}finally{await browser.close();}
console.log(`PASS batch${batch}: ${manifests.length} catalogs/sitemap, ${total} previews, statuses/access/roles, desktop/mobile/keyboard.`);
