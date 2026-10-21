import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'});
const snapshot=()=>sql('SELECT * FROM st_reviews ORDER BY id'),before=snapshot();
const base='http://127.0.0.1:8097',path='/kelola/ulasan/pratinjau';
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 async function login(name){await p.deleteCookie(...await p.cookies());await p.goto(base+'/masuk');await p.type('[name=identity]',name);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);}
 await p.goto(base+path);assert.ok(p.url().includes('/masuk'));
 for(const name of ['monitor2','monitor4']){await login(name);assert.equal((await p.goto(base+path)).status(),403);}
 await login('monitor1');
 await p.goto(base+'/kelola/ulasan');assert.ok(await p.$('a[href$="/kelola/ulasan/pratinjau"]'));
 const r=await p.goto(base+path);assert.equal(r.status(),200);assert.match(r.headers()['cache-control'],/no-store/);assert.match(r.headers()['x-robots-tag'],/noindex/);
 assert.equal(await p.$$eval('[data-review-card]',es=>es.length),20);
 assert.ok((await p.content()).includes('4,9 / 5'));assert.ok(!(await p.content()).includes('Akun terverifikasi</p>'));
 assert.equal(await p.$('a[href$="/ulasan/tulis"]'),null);
 const first=await p.$eval('[data-review-card] h2',e=>e.textContent);
 await p.focus('nav[aria-label="Halaman ulasan"] a');await Promise.all([p.waitForNavigation(),p.keyboard.press('Enter')]);assert.ok(p.url().endsWith('halaman=2'));assert.notEqual(await p.$eval('[data-review-card] h2',e=>e.textContent),first);
 await p.goto(base+path+'?halaman=9999');assert.ok((await p.content()).includes('Halaman 50'));assert.equal(await p.$$eval('nav[aria-label="Halaman ulasan"] a',es=>es.length),1);
 for(const width of[1280,360]){await p.setViewport({width,height:900});await p.goto(base+path);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await p.screenshot({path:`/tmp/review087-${width}.png`});}
 const post=await p.evaluate(async path=>{const token=document.querySelector('input[name="study_csrf_token"]');const r=await fetch(path,{method:'POST',body:new URLSearchParams({study_csrf_token:token.value})});return r.status;},path);assert.equal(post,405);
 await p.deleteCookie(...await p.cookies());await p.goto(base+'/ulasan?simulasi=1&review_preview=1');assert.ok(!(await p.content()).includes('Profil contoh'));assert.ok(!(await p.content()).includes('Pratinjau khusus pengelola'));
 assert.equal(snapshot(),before);assert.deepEqual(errors,[]);
 console.log('PASS guest/parent/child denied; admin preview, no-store/noindex, GET only, pagination/keyboard, desktop/mobile, no public fixture leakage, review rows unchanged.');
}finally{await browser.close();}
