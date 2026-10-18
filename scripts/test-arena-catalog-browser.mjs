import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const base='http://127.0.0.1:8097';
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
async function visit(path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!(await page.content()).includes('Severity:'),path);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);}
async function login(user){await page.goto(base+'/masuk');await page.type('[name=identity]',user);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);}
try{
 await login('monitor10');
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});await visit('/belajar/arena');
  assert.equal(await page.$$eval('[data-arena-game]',e=>e.length),9);
  assert.equal(await page.$$eval('a[href*="arena/pilih/"]',e=>e.length),0,'Hub contains game titles, not individual sets');
  await page.screenshot({path:`/tmp/study-arena-hub-${width}.png`,fullPage:true});
  await page.focus('[data-arena-game=flashcard]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);
  assert.ok(page.url().endsWith('/game/flashcard'));assert.equal(await page.$$eval('a[href*="arena/pilih/"]',e=>e.length),12);
  await visit('/belajar/arena/game/flashcard?jenjang=secondary');assert.equal(await page.$$eval('a[href*="arena/pilih/"]',e=>e.length),3);
  await visit('/belajar/arena/game/flashcard?halaman=3');assert.equal(await page.$$eval('a[href*="arena/pilih/"]',e=>e.length),5);
  for(const game of ['pasangan','susun-kata','hitung-cepat','math-expedition','story-quiz'])await visit('/belajar/arena/game/'+game);
  await visit('/belajar/arena/game/pasangan?jenjang=secondary');await page.screenshot({path:`/tmp/study-arena-sets-${width}.png`,fullPage:true});
  await visit('/belajar/arena/pilih/kartu-ipa-dasar');await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/pilih/"] button[type=submit]')]);
  assert.equal(await page.$('[data-card-back]'),null);await page.focus('[value=reveal]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);assert.ok(await page.$('[data-card-back]'));
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:`/tmp/study-arena-flashcard-${width}.png`,fullPage:true});
  await Promise.all([page.waitForNavigation(),page.click('[value=repeat]')]);assert.ok((await page.content()).includes('Kartu 2 dari'));
  await page.evaluate(()=>{for(const d of document.querySelectorAll('details'))d.open=true;});await Promise.all([page.waitForNavigation(),page.click('[value=stop]')]);assert.ok((await page.content()).includes('Tinjauan kartu'));
 }
 assert.equal((await page.goto(base+'/belajar/arena/game/unknown')).status(),404);
 assert.equal((await page.goto(base+'/belajar/arena/game/flashcard?jenjang[]=primary')).status(),400);
 await page.setViewport({width:1280,height:900});await visit('/beranda');await Promise.all([page.waitForNavigation(),page.click('form[action$="keluar"] button')]);await login('monitor1');
 for(const width of [1280,360]){await page.setViewport({width,height:900});for(const path of ['/kelola/pembelajaran/pengguna/9?tab=kartu_arena','/kelola/pembelajaran/modul/kartu_arena','/kelola/pengaturan/arena'])await visit(path);}
 assert.deepEqual(errors,[]);console.log('PASS Arena hub/detail 1280/360px: nine titles, no set dump, keyboard navigation, stage filters/pagination, six module pages, flashcard reveal/rate/stop, admin card monitoring/settings; no overflow or JS errors.');
}finally{await browser.close();}
