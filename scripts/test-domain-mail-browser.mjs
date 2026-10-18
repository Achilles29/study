import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
const base='http://127.0.0.1:8097';
try{
 await page.goto(base+'/masuk');await page.type('[name=identity]','monitor1');await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);
 for(const width of [1280,768,360]){
  await page.setViewport({width,height:900});
  for(const provider of ['gmail','smtp']){
   const r=await page.goto(base+'/kelola/pengaturan/email?provider='+provider);assert.equal(r.status(),200);assert.ok(!(await page.content()).includes('Severity:'));
   assert.equal(await page.$eval('#mail-secret',e=>e.value),'');assert.equal(await page.$eval('[name=provider]',e=>e.value),provider);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No overflow');
   if(provider==='smtp')assert.equal(await page.$eval('#mail-host',e=>e.value),'smtp.example.com');
   await page.screenshot({path:'/tmp/study-domain-mail-'+provider+'-'+width+'.png',fullPage:true});
  }
 }
 // Real keyboard form submit preserves a stored secret, disables mail explicitly.
 await page.select('#mail-enabled','0');await page.type('#mail-admin-password','Monitor-QA-Only-2026!');await page.focus('form input[name=action][value=save]');
 await page.focus('#mail-admin-password');await page.keyboard.press('Tab');
 assert.equal(await page.evaluate(()=>document.activeElement.tagName),'BUTTON');
 await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);assert.ok((await page.content()).includes('Profil email dipilih dan disimpan'));
 assert.equal(await page.$eval('#mail-secret',e=>e.value),'');assert.deepEqual(errors,[]);
 console.log('PASS email profiles Chrome 1280/768/360, no overflow/JS errors, stored secrets blank, keyboard save.');
}finally{await browser.close();}
