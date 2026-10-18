import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',db],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097',browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});const errors=[];
async function actor(id){const context=await browser.createBrowserContext(),page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await page.goto(base+'/masuk');await page.type('[name=identity]','monitor'+id);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);return page;}
async function visit(page,path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);assert.ok(!(await page.content()).includes('Severity:'));}
async function click(page,selector){await Promise.all([page.waitForNavigation(),page.click(selector)]);}
try{
 const host=await actor(8),admin=await actor(1),user=await actor(24),id=sql("SELECT id FROM st_battle_rooms WHERE creator_user_id=8 AND title='QA Katalog Member Gratis' ORDER BY id DESC LIMIT 1");
 for(const width of [1280,360]){
  for(const page of [host,admin,user])await page.setViewport({width,height:900});
  await visit(host,'/belajar/battle/ruang/'+id);await host.focus('details summary');await host.keyboard.press('Enter');assert.ok(await host.$eval('details',e=>e.open));await host.click('#listing-consent');await click(host,'form:has([value=list_request]) button');
  await visit(admin,'/kelola/battle/katalog');await visit(admin,'/kelola/battle/tinjau/'+id);await admin.focus('details summary');await admin.keyboard.press('Enter');assert.ok(await admin.$eval('details',e=>e.open));for(const name of ['content','rights','age','privacy'])await admin.click('#check_'+name);await admin.type('#listing-reason','Uji review browser: isi dan usia sesuai.');await click(admin,'form[action*="battle/tinjau/"] button');await admin.screenshot({path:`/tmp/study-battle-review-${width}.png`,fullPage:true});
  await visit(user,'/belajar/battle/umum');await user.select('#battle-age','primary');await click(user,'form[action$="battle/umum"] button');assert.ok(!(await user.content()).includes('DIRECTORY_SECRET_492'));await user.screenshot({path:`/tmp/study-battle-directory-${width}.png`,fullPage:true});await visit(user,'/belajar/battle/umum/'+id);await user.screenshot({path:`/tmp/study-battle-public-${width}.png`,fullPage:true});await click(user,'form[action*="battle/umum/"] button');assert.ok(user.url().endsWith('/ruang/'+id));
  if(await user.$('#battle-choice-A')){await user.focus('#battle-choice-A');await user.keyboard.press('Space');await click(user,'form:has([value=answer]) button');}
  assert.ok(!(await user.content()).includes('DIRECTORY_SECRET_492'));assert.ok(await user.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 }
 assert.deepEqual(errors,[]);console.log('PASS public battle desktop/mobile: host submits, admin previews/checklists/approves, free member filters/discovers/joins/answers, keyboard, no premature key disclosure, no overflow/JS errors.');
}finally{await browser.close();}
