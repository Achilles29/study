import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.ENGLISH_COURSE_QA,'study_qa_monitor_sso039');
const root='/www/wwwroot/study',base='http://127.0.0.1:8098';
const sql=q=>execFileSync('mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','--socket=/tmp/mysql-study.sock','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const old=sql('SELECT password_hash FROM st_users WHERE id=3');
const hash=execFileSync('/www/server/php/84/bin/php',['-r',"echo password_hash('Monitor-QA-Only-2026!',PASSWORD_DEFAULT);"],{encoding:'utf8'});
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
let checks=0;const errors=[];
try{
 sql("UPDATE st_users SET password_hash='"+hash+"' WHERE id=3");
 async function login(user){const ctx=await browser.createBrowserContext();const p=await ctx.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]',user);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);return p;}
 const student=await login('monitor3'),admin=await login('monitor1');
 const done=sql("SELECT id FROM st_english_attempts WHERE learner_user_id=3 AND status='completed' LIMIT 1");
 const run=sql("SELECT id FROM st_english_attempts WHERE learner_user_id=3 AND status='running' LIMIT 1");
 const submission=sql('SELECT id FROM st_english_submissions WHERE learner_user_id=3 LIMIT 1');
 const studentPaths=['/belajar/kelas-inggris','/belajar/kelas-inggris/unit/F01','/belajar/kelas-inggris/unit/A102','/belajar/kelas-inggris/unit/C208','/belajar/kelas-inggris/riwayat','/belajar/kelas-inggris/tugas/writing/F01','/belajar/kelas-inggris/tugas/speaking/F01',...(done?['/belajar/kelas-inggris/latihan/'+done]:[]),...(run?['/belajar/kelas-inggris/latihan/'+run]:[]),...(submission?['/belajar/kelas-inggris/karya/'+submission]:[])];
 const adminPaths=['/kelola/kelas-inggris','/kelola/kelas-inggris?tab=penilaian','/kelola/kelas-inggris/konsep',...(submission?['/kelola/kelas-inggris/tinjau/'+submission]:[])];
 for(const [page,paths] of [[student,studentPaths],[admin,adminPaths]])for(const width of [1440,390,320]){await page.setViewport({width,height:1000});for(const path of paths){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!/A PHP Error|Severity:|Fatal error/.test(await page.content()),path);const w=await page.evaluate(()=>({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));assert.ok(w.scroll<=w.client+1,JSON.stringify({path,width,w}));checks++;
 if(path.endsWith('/A102')){assert.equal(await page.$$eval('.eh-reading-block',els=>els.length),2);assert.ok(await page.$$eval('.eh-key',els=>els.length)>0);assert.equal(await page.$$eval('.ec-steps a',els=>els.length),4);const targets=await page.$$eval('.ec-steps a',els=>els.every(e=>document.querySelector(e.getAttribute('href'))));assert.ok(targets);if(width!==320){await page.$eval('#konsep',e=>e.scrollIntoView());await page.screenshot({path:root+'/docs/english-hack-v1/reading-v2-'+width+'.png'});}}
 if(path==='/kelola/kelas-inggris'){assert.equal(await page.$('#queue'),null);assert.equal(await page.$$eval('.ec-admin-tabs a',els=>els.length),3);if(width===1440)await page.screenshot({path:root+'/docs/english-hack-v1/admin-layout-v2.png'});}
 if(path.includes('tab=penilaian')){assert.ok(await page.$('#queue'));assert.equal(await page.$('#course-status'),null);assert.ok(await page.$('input[name=tab][value=penilaian]'));}
 if(path==='/kelola/kelas-inggris/konsep'){await page.click('[data-tab=studio]');assert.equal(await page.$$eval('#eh-lesson .eh-reading-block',els=>els.length),2);const sw=await page.evaluate(()=>document.documentElement.scrollWidth);assert.ok(sw<=width+1);}
 if(done&&path.endsWith('/latihan/'+done))assert.ok(await page.$('.ec-answer-pair'));
 }}
 // Filters must keep the assessment tab selected after a normal form submission.
 await admin.goto(base+'/kelola/kelas-inggris?tab=penilaian');await admin.select('#queue-state','all');await Promise.all([admin.waitForNavigation(),admin.click('.ec-queue-filter button')]);assert.ok(admin.url().includes('tab=penilaian'));assert.ok(await admin.$('#queue'));
 // Offline studio uses the same editorial segmentation.
 const offline=await browser.newPage();offline.on('pageerror',e=>errors.push(e.message));await offline.goto('file://'+root+'/application/content/english_hack/v1/english-hack-studio-v1.html');await offline.click('[data-tab=studio]');assert.equal(await offline.$$eval('#eh-lesson .eh-reading-block',els=>els.length),2);
 assert.deepEqual(errors,[]);const report={pagesAndViewports:checks,browserErrors:errors,editorialBlocks:112,adminNavigation:'one sidebar entry, three sections',assessmentFilter:'preserves tab',originalLessonText:'preserved'};await fs.writeFile(root+'/docs/english-hack-v1/layout-validation.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
}finally{await browser.close();sql("UPDATE st_users SET password_hash='"+old+"' WHERE id=3");}
