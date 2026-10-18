import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const dir=process.env.STUDY_PAYMENT_TEST_DIRECTORY;assert.match(dir||'',/^\/tmp\/study-payment-qa-[A-Za-z0-9]+$/);
const id=readFileSync(dir+'/donation-browser-id','utf8').trim(),base='http://127.0.0.1:8097';assert.match(id,/^[0-9]+$/);
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']}),errors=[];
try{
 async function page(user){const context=await browser.createBrowserContext(),p=await context.newPage();p.on('pageerror',e=>errors.push(e.message));await p.setRequestInterception(true);p.on('request',r=>r.url().startsWith('https://api.midtrans.com/')?r.respond({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="white"/><text x="30" y="150">QA QR illustration only</text></svg>'}):r.continue());if(user){await p.goto(base+'/masuk');await p.type('[name=identity]','monitor'+user);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);}return p;}
 const guest=await page(),adult=await page(19),admin=await page(1);
 for(const width of [1280,360]){
  for(const [p,paths] of [[guest,['/donasi']],[adult,['/donasi','/donasi/pesanan/'+id]],[admin,['/kelola/donasi','/kelola/pengaturan/donasi','/donasi/pesanan/'+id]]]){
   await p.setViewport({width,height:900});for(const path of paths){assert.equal((await p.goto(base+path)).status(),200,path);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path+' overflow');}
  }
  await adult.goto(base+'/donasi');await adult.click('[data-donation-amount="10000"]');assert.equal(await adult.$eval('#donation-amount',e=>e.value),'10000');assert.equal(await adult.$eval('#donation-amount',e=>e===document.activeElement),true);await adult.screenshot({path:'/tmp/study-donation043-'+width+'.png',fullPage:true});
  await admin.goto(base+'/kelola/donasi');await admin.screenshot({path:'/tmp/study-donation043-admin-'+width+'.png',fullPage:true});
 }
 // Generic settings persist through the actual browser form, including pause/resume and revision.
 await admin.goto(base+'/kelola/pengaturan/donasi');await admin.select('#setting-enabled','0');await admin.focus('form[action$="/donasi"] button[type=submit]');await Promise.all([admin.waitForNavigation(),admin.keyboard.press('Enter')]);await adult.goto(base+'/donasi');assert.ok((await adult.content()).includes('Donasi baru sedang dijeda'));
 await admin.goto(base+'/kelola/pengaturan/donasi');await admin.select('#setting-enabled','1');await Promise.all([admin.waitForNavigation(),admin.click('form[action$="/donasi"] button[type=submit]')]);await adult.goto(base+'/donasi');assert.ok(await adult.$('#donation-amount'));
 await adult.setJavaScriptEnabled(false);await adult.reload();assert.ok(await adult.$eval('#donation-amount',e=>e.required));assert.ok(await adult.$eval('[data-donation-presets]',e=>getComputedStyle(e).display==='none'));assert.deepEqual(errors,[]);
 console.log('PASS donations browser 1280/360: public/adult/admin, private QR detail (synthetic image), presets/focus, settings keyboard pause/resume persistence, no-JS form, no overflow/JS errors.');
}finally{await browser.close();}
