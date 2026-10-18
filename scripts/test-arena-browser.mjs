import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const base='http://127.0.0.1:8097';
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage(),errors=[];
page.on('pageerror',error=>errors.push(error.message));
try {
 await page.goto(base+'/masuk');await page.type('[name=identity]','monitor3');await page.type('[name=password]','Monitor-QA-Only-2026!');
 await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});
  for(const path of ['/beranda','/belajar/arena','/belajar/arena/riwayat','/belajar/arena/pilih/word-sd-1']){
   const response=await page.goto(base+path);assert.equal(response.status(),200);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`Overflow ${width} ${path}`);
  }
  // A keyboard-first round: choose, start, submit an answer, stop explicitly.
  await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/pilih/"] button[type=submit]')]);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.focus('#arena-answer');await page.keyboard.type('BUKU');
  await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);
  assert.ok((await page.content()).includes('Soal 2 dari'));
  if(width===360)await page.screenshot({path:'/tmp/study-arena-mobile.png',fullPage:true});
  await page.click('summary');await Promise.all([page.waitForNavigation(),page.click('[value=stop]')]);
 }
 await page.goto(base+'/belajar/arena/pilih/pasangan-dasar-1');await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/pilih/"] button[type=submit]')]);
 await page.focus('button[name=value]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 await page.screenshot({path:'/tmp/study-arena-memory-mobile.png',fullPage:true});
 await page.click('summary');await Promise.all([page.waitForNavigation(),page.click('[value=stop]')]);
 await page.goto(base+'/belajar/arena/pilih/ekspedisi-angka-ceria');await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/pilih/"] button[type=submit]')]);
 await page.focus('input[type=radio]');await page.keyboard.press('Space');
 await page.click('details summary');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 await page.screenshot({path:'/tmp/study-arena-expedition-mobile.png',fullPage:true});
 await Promise.all([page.waitForNavigation(),page.click('form[action*="arena/main/"] button[type=submit]')]);
 await page.evaluate(()=>{for(const d of document.querySelectorAll('details'))d.open=true;});await Promise.all([page.waitForNavigation(),page.click('[value=stop]')]);
 await page.setViewport({width:1280,height:900});await page.goto(base+'/beranda');await Promise.all([page.waitForNavigation(),page.click('form[action$="keluar"] button')]);
 await page.goto(base+'/masuk');await page.type('[name=identity]','monitor1');await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);
 for(const width of [1280,360]){await page.setViewport({width,height:900});for(const path of ['/kelola/arena','/kelola/pengaturan/arena','/kelola/pembelajaran/pengguna/3?tab=arena']){const response=await page.goto(base+path);assert.equal(response.status(),200);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${width} ${path}`);}}
 assert.deepEqual(errors,[]);console.log('PASS Arena browser: desktop/mobile, keyboard answer/card flip, progress, management/settings and monitoring, no overflow or JS errors.');
} finally {await browser.close();}
