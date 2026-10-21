import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB, 'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
sql("INSERT INTO st_drill_daily(learner_user_id,day_wib,used_count,extra_count) VALUES(4,DATE(CONVERT_TZ(UTC_TIMESTAMP(),'+00:00','+07:00')),0,100) ON DUPLICATE KEY UPDATE extra_count=100");
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
const errors=[];
const p=await browser.newPage();p.on('pageerror',e=>errors.push(e.message));
const pause=()=>new Promise(r=>setTimeout(r,850));
async function login(name){
 await p.goto(base+'/masuk');await p.type('[name=identity]',name);await p.type('[name=password]','Monitor-QA-Only-2026!');
 await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);
 assert.ok(!p.url().endsWith('/masuk'));
}
async function defaults(path){
 await p.goto(base+path);
 if(!await p.$('[name=view_mode]')) console.log('Missing settings:',p.url(),await p.$eval('body',e=>e.innerText.slice(0,1200)));
 assert.deepEqual(await p.$$eval('[name=view_mode] option',els=>els.map(e=>e.value)),['ledger','single']);
 assert.equal(await p.$eval('[name=view_mode]',e=>e.value),'ledger');
}
async function finish(){
 await p.click('button[name=action][value=finish]');
 await Promise.all([p.waitForNavigation(),p.click('#sheet-confirm .btn-danger')]);
}
async function geometry(index){
 await p.evaluate(i=>{window.dispatchEvent(new WheelEvent('wheel'));document.querySelector('#sheet-q-'+i).scrollIntoView({block:'start',behavior:'instant'});},index);await pause();
 const g=await p.evaluate(i=>{
  const nav=document.querySelector('.sheet-navigation').getBoundingClientRect(),header=document.querySelector('.site-header-member').getBoundingClientRect(),q=document.querySelector('#sheet-q-'+i).getBoundingClientRect();
  return {navTop:nav.top,navBottom:nav.bottom,navHeight:nav.height,headerBottom:header.bottom,qTop:q.top,overflow:document.documentElement.scrollWidth>innerWidth,sticky:getComputedStyle(document.querySelector('.sheet-navigation')).position};
 },index);
 assert.equal(g.overflow,false);assert.equal(g.sticky,'sticky');assert.ok(g.navTop>=g.headerBottom-1,JSON.stringify(g));
 assert.ok(g.navTop<g.headerBottom+12,JSON.stringify(g));
 if((await p.viewport()).width<768){assert.ok(g.navHeight<=135,JSON.stringify(g));assert.ok(g.qTop>=g.navBottom,JSON.stringify(g));}
 return g;
}
try {
 await p.setViewport({width:360,height:740});await login('monitor1');await defaults('/kelola/simulasi-ujian/0');
 await p.goto(base+'/kelola/simulasi-ujian/15');assert.equal(await p.$eval('[name=view_mode]',e=>e.value),'single');
 await p.deleteCookie(...await p.cookies());await login('monitor4');
 for(const [path,mode,width] of [['/latihan/mandiri','ledger',360],['/latihan/kursus/4','ledger',360],['/latihan/kursus/4','single',360],['/latihan/kursus/4','ledger',1280],['/latihan/ujian/14','ledger',360]]){
  await p.setViewport({width,height:740});
  if(!path.includes('/ujian/')){await defaults(path);await p.select('[name=view_mode]',mode);}
  else await p.goto(base+path);
  if(path.endsWith('/mandiri')){await p.select('[name=grade_id]','3');await p.select('[name=subject_id]','1');await p.$eval('[name=count]',e=>e.value='10');}
  await Promise.all([p.waitForNavigation(),p.click(`form[action$="${path}"] button[type=submit]`)]);
  await p.waitForSelector('#practice-answer-form');assert.equal(await p.$eval('#practice-sheet',e=>e.dataset.mode),mode);
  const count=await p.$$eval('.practice-question',els=>els.length);
  const last=count-1;
  if(mode==='single'){await p.$eval(`[data-nav-index="${last}"]`,e=>e.click());await pause();}
  if(mode==='single')await geometry(last);
  else {const middle=Math.floor(count/2);await geometry(middle);assert.equal(await p.$eval('.sheet-number[aria-current=true]',e=>Number(e.dataset.navIndex)),middle);await p.$eval(`[data-nav-index="${last}"]`,e=>e.click());await pause();assert.equal(await p.$eval('.sheet-number[aria-current=true]',e=>Number(e.dataset.navIndex)),last);}
  // Keyboard activates an offscreen number, not a page jump to the sidebar.
  await p.focus('[data-nav-index="0"]');await p.keyboard.press('Enter');await pause();
  assert.equal(await p.$eval('#sheet-q-0',e=>e.hidden),false);
  await p.$eval('#sheet-q-0 input[type=radio]',e=>e.click());
  await p.waitForFunction(()=>document.querySelector('#sheet-save-status').textContent.startsWith('Tersimpan'));await pause();
  assert.ok(await p.$eval('[data-nav-index="0"]',e=>e.classList.contains('is-answered')));
  if(mode==='ledger'){assert.equal(await p.evaluate(()=>document.activeElement.id),'sheet-q-1');await geometry(1);}
  await p.screenshot({path:`/tmp/sheet082-${path.split('/')[2]}-${mode}-${width}.png`});
  if(width===360){
   await p.click('.sheet-navigation summary');await pause();assert.equal(await p.$eval('.sheet-navigation details',e=>e.open),false);
   await geometry(mode==='single'?0:1);
   await p.click('.sheet-navigation summary');await pause();
   await p.click('.navbar-toggler');await pause();await p.click('.navbar-toggler');await pause();await geometry(mode==='single'?0:1);
  }
  await finish();
  console.log('PASS',path,mode,width);
 }
 await p.deleteCookie(...await p.cookies());await login('monitor2');await p.setViewport({width:360,height:740});await defaults('/latihan/kursus/4');
 await Promise.all([p.waitForNavigation(),p.click('form[action$="/latihan/kursus/4"] button[type=submit]')]);await p.waitForSelector('#practice-answer-form');
 await geometry(4);assert.ok(await p.$('.member-donation-badge'));await p.screenshot({path:'/tmp/sheet082-parent-360.png'});await finish();
 console.log('PASS adult navbar with donation row, sticky offset 360');
 assert.deepEqual(errors,[]);
 console.log('PASS defaults/order, existing single preserved, three exercise types, sticky/offset, horizontal strip, keyboard, autosave, auto-scroll, menu/collapse, no overflow or JS errors.');
} finally {await browser.close();}
