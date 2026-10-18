import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',db],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097',browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const errors=[],page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
async function visit(path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!(await page.content()).includes('Severity:'));assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);}
async function login(id){await page.goto(base+'/masuk');await page.type('[name=identity]','monitor'+id);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);}
async function logout(){await page.setViewport({width:1280,height:900});await visit('/beranda');await Promise.all([page.waitForNavigation(),page.click('form[action$="keluar"] button')]);}
async function click(selector){await Promise.all([page.waitForNavigation(),page.click(selector)]);assert.ok(!(await page.content()).includes('Severity:'));}
try{
 await login(23);
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});await visit('/belajar/arena');assert.equal(await page.$$eval('[data-arena-game]',e=>e.length),9);await page.focus('[data-arena-game=battle]');await Promise.all([page.waitForNavigation(),page.keyboard.press('Enter')]);
  await page.type('#battle-title','QA Browser '+width);await page.$eval('#battle-start',e=>e.value=new Date(Date.now()+7*3600000-60000).toISOString().slice(0,16));await page.$eval('#battle-end',e=>e.value=new Date(Date.now()+8*3600000).toISOString().slice(0,16));await click('form[action$="battle/buat"] button');
  const id=Number(page.url().split('/').pop());await page.type('#battle-prompt','Berapa dua tambah dua?');for(const [key,value] of Object.entries({A:'Empat',B:'Tiga',C:'Dua',D:'Satu'}))await page.type('#battle-option-'+key,value);await page.type('#battle-explanation','Dua ditambah dua adalah empat.');await page.type('#battle-source','Karya QA');await page.type('#battle-rights','Materi uji proyek');await click('form:has([name=action][value=question]) button');
  await page.focus('details summary');await page.keyboard.press('Enter');assert.ok(await page.$eval('details',e=>e.open));assert.ok((await page.content()).includes('Email penyelenggara belum terverifikasi'));assert.equal(await page.$('[value=publish]'),null);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:`/tmp/study-battle-draft-${width}.png`,fullPage:true});
  await page.evaluate(()=>{for(const d of document.querySelectorAll('details'))d.open=true;});await page.type('#battle-cancel-reason','Selesai uji browser');await click('[value=cancel]');await click('[value=archive]');assert.equal(sql(`SELECT status FROM st_battle_rooms WHERE id=${id}`),'archived');
 }
 await logout();await login(24);const room=sql("SELECT id FROM st_battle_rooms WHERE creator_user_id=3 AND status='published' ORDER BY id DESC LIMIT 1"),code=sql(`SELECT invite_code FROM st_battle_rooms WHERE id=${room}`);
 await page.setViewport({width:360,height:900});await visit('/belajar/battle');await page.type('#battle-code',code);await click('form[action$="battle/gabung"] button');await page.focus('#battle-choice-A');await page.keyboard.press('Space');await click('form:has([name=action][value=answer]) button');assert.ok((await page.content()).includes('Semua jawabanmu'));assert.ok(!(await page.content()).includes('PRIVATE_CORRECT_EXPLANATION_82'));
 await logout();await login(2);await visit('/belajar/battle');assert.ok(await page.$('#battle-consent'));await logout();await login(1);
 for(const width of [1280,360]){await page.setViewport({width,height:900});for(const path of ['/kelola/battle','/kelola/pengaturan/battle','/kelola/pembelajaran/pengguna/24?tab=battle','/kelola/rapor/24'])await visit(path);await visit('/kelola/battle');assert.ok((await page.content()).includes('admin-navigation'));await page.screenshot({path:`/tmp/study-battle-admin-${width}.png`,fullPage:true});}
 assert.deepEqual(errors,[]);console.log('PASS Battle browser desktop/mobile: 9-game hub, keyboard navigation, draft creation/question preview, verification guard, cancel/archive, join/answer, parent consent UI, admin shell/settings/monitoring, no overflow or JS errors.');
}finally{await browser.close();}
