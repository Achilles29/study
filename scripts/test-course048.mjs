import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(name){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const admin=await login('monitor1'),parent=await login('monitor2'),guest=client();
async function post(path,data,getPath=path){const r=await admin(getPath);assert.equal(r.status,200);return admin(path,{...data,study_csrf_token:field(r.body,'study_csrf_token')});}
const prefix='QA048 '+Date.now(),make={title:prefix+' Kelas 1',summary:'Materi kelas untuk pengujian terisolasi.',learning_objectives:'Menguji klasifikasi dan filter kelas.',prerequisites:'',category_id:'1',grade_id:'3',target_group:'primary',age_min:'',age_max:'',estimated_minutes:'10',rights_status:'owned',source_note:'Naskah asli pengujian QA048',is_free:'yes'};
assert.equal((await admin('/kelola/kursus/tambah',make)).status,403);
for(const grade of ['99999','12','3 OR 1=1']){assert.equal((await post('/kelola/kursus/tambah',{...make,grade_id:grade})).status,200);assert.equal(sql(`SELECT COUNT(*) FROM st_courses WHERE title='${make.title}'`),'0');}
assert.equal((await post('/kelola/kursus/tambah',make)).status,303);
const id=sql(`SELECT id FROM st_courses WHERE title='${make.title}'`);assert.equal(sql('SELECT grade_id FROM st_courses WHERE id='+id),'3');
assert.equal((await post(`/kelola/kursus/${id}/edit`,{...make,grade_id:'4'})).status,303);assert.equal(sql('SELECT grade_id FROM st_courses WHERE id='+id),'4');
const version=sql('SELECT content_version FROM st_courses WHERE id='+id);
sql("CREATE TRIGGER qa_grade048_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA grade rollback'");
try{assert.equal((await post(`/kelola/kursus/${id}/edit`,{...make,grade_id:'3'})).status,200);assert.equal(sql('SELECT CONCAT(grade_id,\':\',content_version) FROM st_courses WHERE id='+id),'4:'+version);}finally{sql('DROP TRIGGER qa_grade048_fail');}
assert.equal((await post(`/kelola/kursus/${id}/edit`,make)).status,303);
const cid=sql("SELECT id FROM st_courses WHERE slug='matematika-kelas-1-mengenal-bilangan-1-20'");
sql(`UPDATE st_courses SET status='published' WHERE id=${cid}`);
for(const [path,c] of [['/kursus',guest],['/kelola/kursus',admin],['/kelola/kategori?tab=mapel',admin],['/kelola/arena',admin],...['pasangan','susun-kata','hitung-cepat','math-expedition','flashcard','story-quiz','kelas-inggris','english-quest'].map(p=>['/belajar/arena/game/'+p,parent])]){
 for(const value of ['sd_1','sd_2','sma_12','lintas-kelas']){const r=await c(path+(path.includes('?')?'&':'?')+'kelas='+value);assert.equal(r.status,200,path);assert.ok(r.body.includes('name="kelas"'));}
 for(const query of ['kelas=bogus','kelas[]=sd_1'])assert.equal((await c(path+(path.includes('?')?'&':'?')+query)).status,400,path);
}
assert.ok((await admin('/kelola/kursus?kelas=sd_1')).body.includes(prefix));assert.ok(!(await admin('/kelola/kursus?kelas=sd_2')).body.includes(prefix));
assert.ok((await guest('/kursus?kelas=sd_1')).body.includes('Mengenal Bilangan'));assert.ok(!(await guest('/kursus?kelas=sd_2')).body.includes('Mengenal Bilangan'));
assert.equal((await parent('/kelola/kursus?kelas=sd_1')).status,403);
// Review UI only: clone the complete sample into review state in isolated QA, never publish production.
const originalStatus=sql('SELECT status FROM st_courses WHERE id='+cid),setting=JSON.parse(sql("SELECT values_json FROM st_site_settings WHERE section='pembelajaran'"));
sql(`UPDATE st_courses SET status='review' WHERE id=${cid}`);
const mode=async value=>{const r=await admin('/kelola/pengaturan/pembelajaran');return admin('/kelola/pengaturan/pembelajaran',{solo_review:value,revision:field(r.body,'revision'),study_csrf_token:field(r.body,'study_csrf_token')});};
await mode('0');assert.ok((await admin('/kelola/kursus/'+cid)).body.includes('Mengapa belum bisa diterbitkan?'));
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor1');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){await p.setViewport({width,height:900});for(const path of ['/kelola/kursus?kelas=sd_1','/kelola/kursus/'+cid,`/kelola/kursus/${id}/edit`,`/kelola/kursus/${cid}/soal`,'/kelola/kategori?tab=mapel&kelas=sd_1','/kelola/arena?jenis=memory&kelas=sd_1','/kursus?kelas=sd_1','/kelola/paket/3','/belajar/arena/game/kelas-inggris?kelas=sd_1','/belajar/arena/game/english-quest?kelas=lintas-kelas']){assert.equal((await p.goto(base+path)).status(),200,path);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`overflow ${width} ${path}`);const bad=await p.$$eval('.course-workspace .btn',es=>es.filter(e=>e.getClientRects().length && (e.scrollWidth>e.clientWidth+2||e.scrollHeight>e.clientHeight+2||parseFloat(getComputedStyle(e).borderTopLeftRadius)>30)).map(e=>e.textContent.trim()));assert.deepEqual(bad,[],`buttons ${width} ${path}`);}await p.goto(base+'/kelola/kursus/'+cid);await p.screenshot({path:'/tmp/study-course048-'+width+'.png',fullPage:true});}assert.deepEqual(errors,[]);
}finally{await browser.close();}
assert.equal((await mode('1')).status,303);assert.ok((await admin('/kelola/kursus/'+cid)).body.includes('quality_content'));
await post(`/kelola/kursus/${cid}/tinjau`,{decision:'approved',notes:'QA must reject incomplete checklist'},'/kelola/kursus/'+cid);assert.equal(sql('SELECT status FROM st_courses WHERE id='+cid),'review');
await post(`/kelola/kursus/${cid}/tinjau`,{decision:'approved',notes:'QA checked original content rights and age fit',quality_content:'1',quality_rights:'1',quality_age:'1'},'/kelola/kursus/'+cid);assert.equal(sql('SELECT status FROM st_courses WHERE id='+cid),'published');
await mode(setting.solo_review??'0');
console.log('PASS grade create/edit/validation/CSRF/audit rollback, catalog/admin/master/arena filter incl invalid input, draft visibility, parent role denial, browser1280/360 buttons, review guidance/off/checklist/publish QA only.');
