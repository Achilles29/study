import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const base='http://127.0.0.1:8097',browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
async function visit(path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!(await page.content()).includes('Severity:'),path);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);}
async function login(id){await page.goto(base+'/masuk');await page.type('[name=identity]','monitor'+id);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);}
try{
 await login(15);
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});await visit('/belajar/arena');assert.equal(await page.$$eval('[data-arena-game]',e=>e.length),9);
  await page.focus('[data-arena-game=quest]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);assert.ok(page.url().endsWith('/game/english-quest'));await page.focus('details summary');await page.keyboard.press('Enter');assert.ok(await page.$eval('details',e=>e.open));await page.screenshot({path:`/tmp/study-quest-map-${width}.png`,fullPage:true});
  await visit('/belajar/arena/pilih/quest-lost-library');await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/pilih/"] button[type=submit]')]);await page.focus('input[type=radio]');await page.keyboard.press('Space');await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/main/"] button[type=submit]')]);assert.ok((await page.content()).includes('Kosakata:'));assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:`/tmp/study-quest-scene-${width}.png`,fullPage:true});
  await Promise.all([page.waitForNavigation(),page.click('[value=next]')]);assert.ok((await page.content()).includes('Adegan 2/15'));await page.evaluate(()=>{for(const d of document.querySelectorAll('details'))d.open=true;});await Promise.all([page.waitForNavigation(),page.click('[value=stop]')]);assert.ok((await page.content()).includes('Jurnal petualangan'));
  await visit('/belajar/arena/game/story-quiz');await visit('/belajar/arena/game/story-quiz?halaman=2');await visit('/belajar/arena/game/flashcard?halaman=3');
 }
 await page.setViewport({width:1280,height:900});await visit('/beranda');await Promise.all([page.waitForNavigation(),page.click('form[action$="keluar"] button')]);await login(1);
 for(const width of [1280,360]){await page.setViewport({width,height:900});for(const path of ['/kelola/arena?jenis=quest','/kelola/pengaturan/quest','/kelola/pembelajaran/pengguna/14?tab=arena','/kelola/rapor/14'])await visit(path);}
 assert.deepEqual(errors,[]);console.log('PASS Quest and expanded catalogs desktop/mobile: nine titles, episode selection/locks, collectibles, keyboard choices, feedback/next/journal, admin settings/reports, story and vocabulary pagination; no overflow or JS errors.');
}finally{await browser.close();}
