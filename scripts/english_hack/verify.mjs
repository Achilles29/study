import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const root='/www/wwwroot/study';
const data=JSON.parse(await fs.readFile(root+'/application/content/english_hack/v1/curriculum.json','utf8'));
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
const report={};
try{
 const page=await browser.newPage(),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.setViewport({width:1440,height:1000});
 await page.goto('file://'+root+'/application/content/english_hack/v1/english-hack-studio-v1.html');
 await page.waitForSelector('#eh-lesson .eh-question');
 assert.equal(await page.$$eval('#eh-levels .eh-level-card',els=>els.length),7);
 await page.screenshot({path:root+'/docs/english-hack-v1/studio-desktop.png',fullPage:false});
 await page.click('[data-tab=studio]');
 // Exercise every authored answer through the real DOM and checker, not a copied implementation.
 let checked=0;
 for(const level of data.levels){
  for(const unit of level.units){
   await page.evaluate(id=>document.querySelector(`[data-unit="${id}"]`).click(),unit.id);
   for(const q of unit.questions){
    await page.evaluate(q=>{
     const f=document.querySelector(`[data-question="${q.id}"]`);
     if(q.type==='choice') f.querySelector(`input[value="${q.answer_index}"]`).checked=true;
     else f.querySelector('input').value=q.answers[0];
     f.querySelector('button').click();
    },q);
    assert.ok(await page.$eval('#feedback-'+q.id,e=>e.classList.contains('is-correct')),q.id);
    checked++;
   }
   assert.match(await page.$eval('#eh-quiz-summary',e=>e.textContent),/6 benar pada percobaan pertama/);
  }
 }
 report.answers_checked=checked;
 await page.evaluate(()=>document.querySelector('[data-unit="A102"]').click());
 const q=data.levels[1].units[1].questions[0];
 assert.equal(q.type,'choice');
 await page.evaluate(q=>{const f=document.querySelector(`[data-question="${q.id}"]`);f.querySelector(`input[value="${(q.answer_index+1)%q.options.length}"]`).checked=true;f.querySelector('button').click();},q);
 assert.ok(await page.$eval('#feedback-'+q.id,e=>e.classList.contains('is-wrong')));
 await page.evaluate(q=>{const f=document.querySelector(`[data-question="${q.id}"]`);f.querySelector(`input[value="${q.answer_index}"]`).checked=true;f.querySelector('button').click();},q);
 assert.match(await page.$eval('#eh-quiz-summary',e=>e.textContent),/0 benar pada percobaan pertama · 1 benar setelah perbaikan/);
 await page.type('#eh-unit-search','negosiasi');
 assert.match(await page.$eval('#eh-unit-list',e=>e.textContent),/C106/);
 await page.$eval('#eh-unit-search',e=>{e.value='unfindableword';e.dispatchEvent(new Event('input'));});
 assert.match(await page.$eval('#eh-unit-list',e=>e.textContent),/Tidak ada unit/);
 await page.$eval('#eh-unit-search',e=>{e.value='';e.dispatchEvent(new Event('input'));});
 await page.click('[data-tab=atlas]');
 await page.click('[data-chart=tenses]');
 assert.equal(await page.$eval('#eh-chart-dialog',e=>e.open),true);
 await page.keyboard.press('Escape');
 assert.equal(await page.$eval('#eh-chart-dialog',e=>e.open),false);
 await page.focus('[data-tab=atlas]');await page.keyboard.press('ArrowRight');
 assert.equal(await page.$eval('[data-tab=method]',e=>e.getAttribute('aria-selected')),'true');
 await page.click('[data-tab=sources]');
 assert.ok(await page.$eval('#eh-downloads a',e=>e.href.startsWith('blob:')&&e.download.endsWith('.html')));
 // Inspect all diagram text bounds in their native SVG documents.
 const chartPage=await browser.newPage();const chartOverflows=[];
 for(const c of data.charts){
  await chartPage.goto('file://'+root+'/'+c.file);
  const bad=await chartPage.evaluate(()=>[...document.querySelectorAll('text')].filter(t=>{const b=t.getBBox();return b.x<0||b.x+b.width>1200;}).map(t=>t.textContent));
  if(bad.length)chartOverflows.push({id:c.id,bad});
 }
 assert.deepEqual(chartOverflows,[]);
 await chartPage.goto('file://'+root+'/assets/images/english-hack/tenses.svg');
 await chartPage.setViewport({width:1200,height:800});
 await chartPage.screenshot({path:root+'/docs/english-hack-v1/atlas-tenses.png'});
 await chartPage.close();
 await page.setViewport({width:390,height:844});
 const widths=[];
 for(const tab of ['overview','roadmap','studio','atlas','method','tracks','sources']){
  await page.click(`[data-tab=${tab}]`);
  widths.push(await page.evaluate(tab=>({tab,client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}),tab));
 }
 assert.ok(widths.every(w=>w.scroll<=w.client+1),JSON.stringify(widths));report.mobile_widths=widths;
 await page.click('[data-tab=studio]');await page.evaluate(()=>document.querySelector('[data-unit="A102"]').click());
 await page.screenshot({path:root+'/docs/english-hack-v1/studio-mobile.png',fullPage:false});
 assert.deepEqual(errors,[]);report.browser_errors=errors;
 // Static book contains all units, questions, models, and can render without scripts.
 const book=await browser.newPage();await book.setJavaScriptEnabled(false);
 await book.goto('file://'+root+'/application/content/english_hack/v1/english-hack-coursebook-v1.html');
 assert.equal(await book.$$eval('article.eh-book-unit',els=>els.length),56);
 assert.equal(await book.$$eval('.eh-question',els=>els.length),364);
 assert.equal(await book.$$eval('.eh-production',els=>els.length),112);
 await book.pdf({path:root+'/application/content/english_hack/v1/english-hack-coursebook-v1.pdf',format:'A4',printBackground:true,preferCSSPageSize:true,displayHeaderFooter:true,headerTemplate:'<span></span>',footerTemplate:'<div style="font-size:8px;width:100%;text-align:center;color:#556">Project Pintar · English Hack v1 · <span class="pageNumber"></span> / <span class="totalPages"></span></div>'});
 report.static_book='56 units / 364 objective questions / 112 production tasks, JavaScript disabled';
 // QA application access and real admin integration. Uses isolated QA database.
 const base=process.env.ENGLISH_HACK_QA_BASE;
 if(base){
  assert.equal(base,'http://127.0.0.1:8098');
  const unauth=await fetch(base+'/kelola/kelas-inggris/konsep',{redirect:'manual'});assert.ok([302,303,307].includes(unauth.status));
  const app=await browser.newPage();await app.setViewport({width:1440,height:1000});
  const appErrors=[];app.on('pageerror',e=>appErrors.push(e.message));
  await app.goto(base+'/masuk');await app.type('[name=identity]','monitor1');await app.type('[name=password]','Monitor-QA-Only-2026!');
  await Promise.all([app.waitForNavigation(),app.click('form[action$="/masuk"] button[type=submit]')]);
  const response=await app.goto(base+'/kelola/kelas-inggris/konsep');
  assert.equal(response.status(),200);assert.match(response.headers()['cache-control'],/no-store/);
  await app.waitForSelector('#eh-lesson .eh-question');
  await app.screenshot({path:root+'/docs/english-hack-v1/dashboard-desktop.png'});
  const download=await app.evaluate(async()=>{const r=await fetch('/kelola/kelas-inggris/konsep/unduh/buku');return {status:r.status,disposition:r.headers.get('content-disposition'),body:(await r.text()).slice(0,80)};});
  assert.equal(download.status,200);assert.match(download.disposition,/attachment/);assert.match(download.body,/doctype html/);
  const invalid=await app.evaluate(async()=> (await fetch('/kelola/kelas-inggris/konsep/unduh/invalid')).status);assert.equal(invalid,404);
  await app.setViewport({width:390,height:844});await app.click('[data-tab=studio]');await app.screenshot({path:root+'/docs/english-hack-v1/dashboard-mobile.png'});
  const appWidth=await app.evaluate(()=>({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));assert.ok(appWidth.scroll<=appWidth.client+1,JSON.stringify(appWidth));
  assert.deepEqual(appErrors,[]);report.application='anonymous redirected; admin preview 200/no-store; valid download attachment; invalid download 404; desktop/mobile render';
  const parentContext=await browser.createBrowserContext();const parent=await parentContext.newPage();await parent.goto(base+'/masuk');await parent.type('[name=identity]','monitor2');await parent.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([parent.waitForNavigation(),parent.click('form[action$="/masuk"] button[type=submit]')]);
  const denied=await parent.goto(base+'/kelola/kelas-inggris/konsep');assert.equal(denied.status(),403);
  const deniedDownload=await parent.goto(base+'/kelola/kelas-inggris/konsep/unduh/data');assert.equal(deniedDownload.status(),403);report.non_admin='403 for preview and download';
 }
 await fs.writeFile(root+'/docs/english-hack-v1/browser-validation.json',JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify(report,null,2));
}finally{await browser.close();}
