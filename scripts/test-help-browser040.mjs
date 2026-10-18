import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const base='http://127.0.0.1:8097',browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
const errors=[];
try{
 const p=await browser.newPage();p.on('pageerror',e=>errors.push(e.message));
 for(const width of [1280,360]){await p.setViewport({width,height:900});assert.equal((await p.goto(base+'/bantuan')).status(),200);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await p.screenshot({path:'/tmp/study-help040-form-'+width+'.png',fullPage:true});}
 await p.type('#subject','Bantuan browser QA');await p.type('#body','Saya ingin memastikan pesan dan balasan tampil dengan baik.');await p.click('#consent');await p.focus('form[action$="/bantuan"] button');await Promise.all([p.waitForNavigation(),p.keyboard.press('Enter')]);assert.match(p.url(),/bantuan\/tiket\/[0-9]+$/);
 const id=p.url().split('/').at(-1),code=await p.$eval('#help-code',e=>e.value);assert.ok(!p.url().includes(code));assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await p.screenshot({path:'/tmp/study-help040-ticket-360.png',fullPage:true});
 const adminContext=await browser.createBrowserContext(),a=await adminContext.newPage();a.on('pageerror',e=>errors.push(e.message));await a.goto(base+'/masuk');await a.type('[name=identity]','monitor1');await a.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([a.waitForNavigation(),a.click('form[action$="/masuk"] button')]);
 for(const width of [1280,360]){await a.setViewport({width,height:900});for(const path of ['/kelola/bantuan','/kelola/bantuan/'+id]){assert.equal((await a.goto(base+path)).status(),200);assert.ok(await a.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));}await a.screenshot({path:'/tmp/study-help040-admin-'+width+'.png',fullPage:true});}
 await a.type('#body','Balasan pengelola untuk pengujian browser.');await a.focus('form[action$="/'+id+'"] button');await Promise.all([a.waitForNavigation(),a.keyboard.press('Enter')]);
 const newContext=await browser.createBrowserContext(),r=await newContext.newPage();await r.setViewport({width:360,height:900});await r.goto(base+'/bantuan');await r.type('#code',code);await r.focus('form[action$="/buka"] button');await Promise.all([r.waitForNavigation(),r.keyboard.press('Enter')]);assert.ok((await r.content()).includes('Balasan pengelola untuk pengujian browser.'));assert.ok(await r.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 assert.deepEqual(errors,[]);console.log('PASS browser help desktop/mobile 1280/360, guest keyboard submit, secret recovery, admin inbox/detail/reply, no overflow or JS error.');
}finally{await browser.close();}
