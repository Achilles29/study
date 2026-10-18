import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const base='http://127.0.0.1:8097',errors=[];
async function login(id){const c=await browser.createBrowserContext(),p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor'+id);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);return p;}
async function visit(p,path){const r=await p.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!/Severity:|Fatal error|Database Error/.test(await p.content()));assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);}
const count=(p,attr)=>p.$$eval('['+attr+']',e=>e.length);
const rootPath='/kelola/kategori',gamePath=rootPath+'?tab=permainan';
try{
 const root=await login(1);
 for(const width of [1280,768,360]){
  await root.setViewport({width,height:900});
  const cases=[['home',rootPath,'data-master-group',3],['games',gamePath,'data-master-game',2],['stages',gamePath+'&jenis=word','data-master-stage',4],['classes',gamePath+'&jenis=word&jenjang=upper_secondary','data-master-grade',3],['leaf',gamePath+'&jenis=word&jenjang=upper_secondary&kelas=sma_10','data-master-category',1],['grades',rootPath+'?tab=jenjang','data-master-stage',4],['grade-detail',rootPath+'?tab=jenjang&jenjang=upper_secondary','data-master-grade',3]];
  for(const [name,path,attr,n] of cases){await visit(root,path);assert.equal(await count(root,attr),n);if(name!=='leaf')assert.equal(await count(root,'data-master-category'),0,'Do not render descendants early');if(name==='leaf'){assert.ok((await root.content()).includes('Belum ada set permainan tertaut'));assert.equal(await root.$$eval('main a[href*="kelola/arena"]',e=>e.length),0);}if(width!==768)await root.screenshot({path:`/tmp/study-master035-${name}-${width}.png`,fullPage:true});}
 }
 // Visit every real leaf and compare counts to the local reference tables.
 const branches=sql('SELECT c.game_type,g.learning_stage,g.code,COUNT(*) FROM st_arena_categories c JOIN st_learning_grades g ON g.id=c.grade_id GROUP BY c.game_type,g.learning_stage,g.code').split('\n').map(l=>l.split('\t'));
 let total=0;for(const [game,stage,grade,n] of branches){await visit(root,gamePath+'&'+new URLSearchParams({jenis:game,jenjang:stage,kelas:grade}));assert.equal(await count(root,'data-master-category'),Number(n));total+=Number(n);}assert.equal(total,35);
 // Entire hierarchy is operable without JavaScript event handlers, including Back.
 await visit(root,gamePath);for(const selector of ['[data-master-game="word"] a','[data-master-stage="upper_secondary"] a','[data-master-grade="sma_10"] a']){await root.focus(selector);await Promise.all([root.waitForNavigation(),root.keyboard.press('Enter')]);}
 assert.equal(await count(root,'data-master-category'),1);await root.goBack();assert.equal(await count(root,'data-master-grade'),3);
 await visit(root,gamePath+'&jenis=memory&jenjang=primary&kelas=sd_1');await Promise.all([root.waitForNavigation(),root.click('main a[href*="kelola/arena"]')]);assert.equal(await root.$$eval('[data-arena-pack]',e=>e.length),1,'Old category activity link works');
 await visit(root,rootPath+'?tab=mapel');assert.ok((await root.content()).includes('Fisika'));await visit(root,'/kelola/trafik');await visit(root,'/kelola/arena');
 for(const query of ['?tab[]=jenjang','?tab=permainan&jenis[]=word','?tab=permainan&jenis=word&jenjang[]=primary','?tab=permainan&kelas=sd_1','?tab=mapel&jenjang=primary'])assert.equal((await root.goto(base+rootPath+query)).status(),400);
 for(const query of ['?tab=permainan&jenis=word&jenjang=primary&kelas=sma_10','?tab=permainan&jenis=math'])assert.equal((await root.goto(base+rootPath+query)).status(),404);
 const editor=await login(6);await visit(editor,gamePath+'&jenis=memory&jenjang=primary&kelas=sd_1');assert.equal(await editor.$$eval('main a[href*="kelola/arena"]',e=>e.length),0);
 const parent=await login(2);assert.equal((await parent.goto(base+gamePath)).status(),403);
 assert.deepEqual(errors,[]);console.log('PASS master hierarchy 1280/768/360, no premature descendants, all 35 category leaves, SMA master-only status, keyboard/back, old activity links, roles/invalid parent-child filters and traffic/Arena smoke.');
}finally{await browser.close();}
