import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const base='http://127.0.0.1:8097',browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
async function visit(path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!(await page.content()).includes('Severity:'),path);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);}
async function login(id){await page.goto(base+'/masuk');await page.type('[name=identity]','monitor'+id);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);}
try{
 await login(13);
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});await visit('/belajar/arena');assert.equal(await page.$$eval('[data-arena-game=english]',e=>e.length),1);
  await page.focus('[data-arena-game=english]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);assert.ok(page.url().endsWith('/game/kelas-inggris'));assert.equal(await page.$$eval('details[id^=bab-]',e=>e.length),12);
  await page.focus('#bab-1 summary');await page.keyboard.press('Enter');assert.ok(await page.$eval('#bab-1',e=>e.open));await page.screenshot({path:`/tmp/study-english-home-${width}.png`,fullPage:true});
  await visit('/belajar/arena/pilih/inggris-hello-english-dialogue');await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/pilih/"] button[type=submit]')]);assert.ok((await page.content()).includes('Hello! My name is Rani.'));await page.focus('[value=next]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);assert.ok((await page.content()).includes('Langkah 2 dari 10'));assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:`/tmp/study-english-lesson-${width}.png`,fullPage:true});
  await page.evaluate(()=>{for(const d of document.querySelectorAll('details'))d.open=true;});await Promise.all([page.waitForNavigation(),page.click('[value=stop]')]);assert.ok((await page.content()).includes('Aktivitas diakhiri lebih awal'));
 }
 await page.setViewport({width:1280,height:900});await visit('/beranda');await Promise.all([page.waitForNavigation(),page.click('form[action$="keluar"] button')]);await login(1);
 for(const width of [1280,360]){await page.setViewport({width,height:900});for(const path of ['/kelola/arena?jenis=english','/kelola/arena?jenis=english&halaman=4','/kelola/pengaturan/english','/kelola/pembelajaran/pengguna/12?tab=english_lesson','/kelola/pembelajaran/pengguna/12?tab=english_test','/kelola/rapor/12'])await visit(path);}
 assert.deepEqual(errors,[]);console.log('PASS English browser desktop/mobile, seven-type hub, chapter disclosure/keyboard, read/resume/stop, private report/settings/admin pagination; no overflow or JS errors.');
}finally{await browser.close();}
