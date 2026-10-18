import assert from 'node:assert/strict';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const base='http://127.0.0.1:8097';
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try {
 const p=await browser.newPage(),errors=[]; p.on('pageerror',e=>errors.push(e.message));
 await p.goto(base+'/masuk'); await p.type('[name=identity]','monitor1'); await p.type('[name=password]','Monitor-QA-Only-2026!');
 await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 for (const width of [1280,360]) {
  await p.setViewport({width,height:900});
  for (let id=4;id<=9;id++) {
   assert.equal((await p.goto(base+`/kelola/kursus/4/pelajaran/${id}/pratinjau?tema=primary`)).status(),200);
   const result=await p.evaluate(()=>{
    const content=document.querySelector('.lesson-content');
    return {overflow:document.documentElement.scrollWidth>innerWidth,headings:content.querySelectorAll('h2').length,
      bold:[...content.querySelectorAll('h2')].every(e=>parseInt(getComputedStyle(e).fontWeight)>=700),
      paragraphs:[...content.querySelectorAll('p')].every(e=>getComputedStyle(e).color===getComputedStyle(content).color),
      images:[...document.querySelectorAll('.lesson-image img')].every(i=>i.complete&&i.naturalWidth>0),
      saved:document.querySelector('#reading-material').hasAttribute('data-saved-position')};
   });
   assert.equal(result.overflow,false);assert.ok(result.headings>=7);assert.ok(result.bold&&result.paragraphs&&result.images&&result.saved);
   if(id===4){await p.screenshot({path:`/tmp/study-typography049-${width}.png`,fullPage:true});await p.focus('#preview-theme');await p.keyboard.press('Tab');assert.equal(await p.evaluate(()=>document.activeElement.textContent.trim()),'Terapkan tema');}
  }
 }
 assert.deepEqual(errors,[]);
 console.log('PASS six lesson previews desktop1280/mobile360: bold hierarchy, readable body, images, no overflow/JS errors, preview keyboard navigation.');
} finally { await browser.close(); }
