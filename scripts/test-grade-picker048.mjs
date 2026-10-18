import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
for(let i=1;i<=21;i++)sql(`INSERT INTO st_courses(title,slug,author_user_id,grade_id,target_group,status,created_at,updated_at) SELECT 'QA048 page ${i}','qa048-page-${i}',1,3,'primary','draft',UTC_TIMESTAMP(),UTC_TIMESTAMP() WHERE NOT EXISTS(SELECT 1 FROM st_courses WHERE slug='qa048-page-${i}')`);
const before=sql('SELECT GROUP_CONCAT(course_id ORDER BY course_id) FROM st_package_courses WHERE package_id=3');
const b=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{const p=await b.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));const base='http://127.0.0.1:8097';await p.goto(base+'/masuk');await p.type('[name=identity]','monitor1');await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 await p.goto(base+'/kelola/kursus?kelas=sd_1&status=draft&q=QA048%20page');
 const next=await p.$$eval('a[href]',as=>as.map(a=>a.href).find(h=>h.includes('halaman=2')));assert.ok(next);const u=new URL(next);assert.equal(u.searchParams.get('kelas'),'sd_1');assert.equal(u.searchParams.get('status'),'draft');assert.equal(u.searchParams.get('q'),'QA048 page');assert.equal((await p.goto(next)).status(),200);assert.ok((await p.content()).includes('QA048 page'));
 for(const width of [1280,360]){await p.setViewport({width,height:900});await p.goto(base+'/kelola/paket/3');await p.waitForSelector('#package-course-grade:not([disabled])');const selected=await p.$$eval('[name="courses[]"]:checked',es=>es.map(e=>e.value));assert.ok(selected.length);
 await p.select('#package-course-grade','sd_2');assert.deepEqual(await p.$$eval('[name="courses[]"]:checked',es=>es.map(e=>e.value)),selected);assert.ok(await p.$$eval('[name="courses[]"]:checked',es=>es.every(e=>!e.closest('[data-course-grade]').hidden)));
 assert.ok(await p.$$eval('[data-course-grade]',es=>es.filter(e=>!e.hidden).every(e=>e.dataset.courseGrade==='sd_2'||e.querySelector('input').checked)));
 await p.focus('#package-course-grade');await p.keyboard.press('Home');await p.keyboard.press('Tab');assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await p.screenshot({path:`/tmp/study-grade-picker048-${width}.png`,fullPage:true});}
 await p.setJavaScriptEnabled(false);await p.goto(base+'/kelola/paket/3');assert.equal(await p.$eval('#package-course-grade',e=>e.disabled),true);assert.ok(await p.$$eval('[data-course-grade]',es=>es.every(e=>!e.hidden)));assert.deepEqual(errors,[]);
}finally{await b.close();}
assert.equal(sql('SELECT GROUP_CONCAT(course_id ORDER BY course_id) FROM st_package_courses WHERE package_id=3'),before);
console.log('PASS pagination retains class/search/status; package course filter preserves checked choices and SQL links, keyboard/mobile/desktop/no-JS fallback.');
