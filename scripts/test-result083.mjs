import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
const errors=[],p=await browser.newPage();p.on('pageerror',e=>errors.push(e.message));
async function login(page,name){await page.goto(base+'/masuk');await page.type('[name=identity]',name);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);}
async function finish(){await p.click('button[name=action][value=finish]');try {await Promise.all([p.waitForNavigation(),p.click('#sheet-confirm .btn-danger')]);}catch(e){console.log('Finish diagnosis',p.url(),errors,await p.$eval('#sheet-save-status',e=>e.textContent));await p.screenshot({path:'/tmp/result083-finish-error.png'});throw e;}await p.waitForSelector('#practice-result');}
async function answer(q,correct){const option=q.options.find(o=>Boolean(Number(o.is_correct))===correct);await p.$eval(`input[name="answers[${q.id}]"][value="${option.id}"]`,e=>e.click());await p.waitForFunction(()=>document.querySelector('#sheet-save-status').textContent.startsWith('Tersimpan'));}
try {
 await p.setViewport({width:360,height:800});await login(p,'monitor4');
 const context=await browser.createBrowserContext(),outsider=await context.newPage();await login(outsider,'monitor11');
 for(const [path,pattern] of [['/latihan/mandiri','mixed'],['/latihan/kursus/4','empty'],['/latihan/ujian/14','perfect']]){
  await p.goto(base+path);
  if(path.endsWith('/mandiri')){await p.select('[name=grade_id]','3');await p.select('[name=subject_id]','1');await p.$eval('[name=count]',e=>e.value='3');await p.select('[name=view_mode]','single');}
  await Promise.all([p.waitForNavigation(),p.click(`form[action$="${path}"] button[type=submit]`)]);await p.waitForSelector('#practice-answer-form');
  const url=p.url(),id=Number(new URL(url).pathname.split('/').at(-1));
  assert.equal((await p.evaluate(async url=>(await fetch(url+'/pembahasan')).status,url)),404,'Active review must not expose keys');
  const qs=sql(`SELECT question_id,snapshot_json FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=${id}) ORDER BY sort_order`).split('\n').map(row=>{const i=row.indexOf('\t');return {id:Number(row.slice(0,i)),...JSON.parse(row.slice(i+1))};});
  if(pattern==='mixed'){await answer(qs[0],true);await p.$eval('[data-nav-index="1"]',e=>e.click());await answer(qs[1],false);}
  if(pattern==='perfect')for(const q of qs)await answer(q,true);
  await finish();
  const expectedCorrect=pattern==='mixed'?1:pattern==='perfect'?qs.length:0,expectedWrong=pattern==='mixed'?1:0,expectedEmpty=qs.length-expectedCorrect-expectedWrong;
  const saved=sql(`SELECT score FROM st_practice_attempts WHERE id=${id}`);
  assert.equal(await p.$eval('[data-result-score]',e=>e.textContent),saved.replace('.',','));
  for(const [name,value] of [['correct',expectedCorrect],['wrong',expectedWrong],['empty',expectedEmpty]])assert.equal(await p.$eval(`[data-result-${name}]`,e=>Number(e.textContent)),value);
  assert.ok(await p.$eval('[data-result-equation]',e=>e.textContent.includes('÷')&&e.textContent.includes('× 100')));
  assert.equal(await p.$('.practice-question'),null);assert.equal(await p.$('input[name^="answers"]'),null);
  for(const width of [360,1280]){
   await p.setViewport({width,height:800});await p.evaluate(()=>scrollTo(0,0));
   assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   assert.ok(await p.$$eval('.result-hero h1,.result-hero p',els=>els.every(e=>getComputedStyle(e).color==='rgb(255, 255, 255)')));
   await p.screenshot({path:`/tmp/result083-${pattern}-${width}.png`,fullPage:true});
  }
  assert.equal((await outsider.goto(url)).status(),404);assert.equal((await outsider.goto(url+'/pembahasan')).status(),404);
  await p.focus('a[href$="/pembahasan"]');await Promise.all([p.waitForNavigation(),p.keyboard.press('Enter')]);
  assert.equal(await p.$$eval('.practice-question',els=>els.length),qs.length);assert.equal(await p.$('#practice-answer-form'),null);
  assert.ok((await p.$eval('body',e=>e.innerText)).includes('Jawaban benar'));
  assert.ok(await p.$$eval('.sheet-hero p',els=>els.every(e=>getComputedStyle(e).color==='rgb(255, 255, 255)'||getComputedStyle(e).color==='rgb(237, 245, 255)')));
  const review=p.url();
  const csrf=await p.$eval('input[name=study_csrf_token]',e=>e.value);
  assert.equal(await p.evaluate(async({url,csrf})=>(await fetch(url,{method:'POST',body:new URLSearchParams({study_csrf_token:csrf,action:'finish',revision:'0'})})).status,{url:review,csrf}),405);
  assert.equal(sql(`SELECT score FROM st_practice_attempts WHERE id=${id}`),saved);
  await Promise.all([p.waitForNavigation(),p.click(`a[href="${url}"]`)]);assert.ok(await p.$('#practice-result'));
  await p.reload();assert.ok(await p.$('#practice-result'));
  console.log('PASS result/review',path,pattern,'score',saved,'360/1280, owner-only, GET-only, keyboard, contrast');
 }
 await context.close();assert.deepEqual(errors,[]);console.log('PASS no JavaScript errors, no production database writes.');
} finally {await browser.close();}
