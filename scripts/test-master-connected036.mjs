import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const base='http://127.0.0.1:8097',errors=[],path='/kelola/kategori?tab=permainan',payment='/kelola/pengaturan/pembayaran';
async function login(id){const c=await browser.createBrowserContext(),p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor'+id);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);return p;}
async function visit(p,url){assert.equal((await p.goto(base+url)).status(),200,url);assert.ok(!/Severity:|Fatal error|Database Error/.test(await p.content()));assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+url);}
const count=(p,attr)=>p.$$eval('['+attr+']',e=>e.length);
try{
 const root=await login(1);
 for(const width of [1280,360]){
  await root.setViewport({width,height:900});
  await visit(root,path);assert.equal(await count(root,'data-master-game'),8);assert.equal(await count(root,'data-master-category'),0);assert.equal(await count(root,'data-master-battle'),1);
  await root.screenshot({path:`/tmp/study-master036-games-${width}.png`,fullPage:true});
  for(const [url,attr,n] of [[path+'&jenis=english','data-master-stage',1],[path+'&jenis=english&jenjang=primary','data-master-grade',1],[path+'&jenis=english&jenjang=primary&kelas=lintas-kelas','data-master-category',13],[path+'&jenis=word&jenjang=upper_secondary&kelas=sma_10','data-master-category',1]]){await visit(root,url);assert.equal(await count(root,attr),n);if(attr!=='data-master-category')assert.equal(await count(root,'data-master-category'),0);}
  await visit(root,payment);assert.equal(await root.$eval('[name=server_key]',e=>e.value),'');assert.equal(await root.$eval('[name=client_key]',e=>e.value),'');
  await root.screenshot({path:`/tmp/study-payment036-${width}.png`,fullPage:true});
 }
 const categories=sql("SELECT c.id,c.game_type,COALESCE(g.code,'lintas-kelas'),c.learning_stage,COUNT(m.pack_id) FROM st_arena_categories c LEFT JOIN st_learning_grades g ON g.id=c.grade_id LEFT JOIN st_arena_pack_categories m ON m.arena_category_id=c.id GROUP BY c.id,c.game_type,g.code,c.learning_stage ORDER BY c.id").split('\n').map(l=>l.split('\t'));
 const branches=new Set();let packs=0;
 for(const [id,game,grade,stage,n] of categories){const url=path+'&'+new URLSearchParams({jenis:game,jenjang:stage,kelas:grade});if(!branches.has(url)){await visit(root,url);const expected=categories.filter(c=>c[1]===game&&c[2]===grade&&c[3]===stage).length;assert.equal(await count(root,'data-master-category'),expected);branches.add(url);}if(Number(n)>0){await visit(root,`/kelola/arena?jenis=${game}&kategori=${id}`);assert.equal(await count(root,'data-arena-pack'),Number(n));packs+=Number(n);}else{await visit(root,url);assert.equal(await root.$$eval(`[data-master-category="${id}"] a`,e=>e.length),0);}}
 assert.equal(packs,190);assert.equal(categories.length,97);
 await visit(root,path);for(const selector of ['[data-master-game="english"] a','[data-master-stage="primary"] a','[data-master-grade="lintas-kelas"] a']){await root.focus(selector);await Promise.all([root.waitForNavigation(),root.keyboard.press('Enter')]);}assert.equal(await count(root,'data-master-category'),13);await root.goBack();assert.equal(await count(root,'data-master-grade'),1);
 await visit(root,payment);await root.$eval('[name=merchant_id]',e=>e.value='QA-NEW-MERCHANT');await root.focus('form:has([name=action][value=save]) button[type=submit]');await Promise.all([root.waitForNavigation(),root.keyboard.press('Enter')]);assert.ok(await root.$('#server_key-error'));assert.equal(await root.$eval('[name=server_key]',e=>e.getAttribute('aria-invalid')),'true');assert.equal(await root.$eval('[name=merchant_id]',e=>e.value),'QA-NEW-MERCHANT');
 for(const query of ['&jenis[]=math','&jenis=english&jenjang[]=primary','&jenis=english&jenjang=primary&kelas[]=lintas-kelas'])assert.equal((await root.goto(base+path+query)).status(),400);
 assert.equal((await root.goto(base+path+'&jenis=english&jenjang=primary&kelas=sma_10')).status(),404);
 const editor=await login(6);await visit(editor,path+'&jenis=english&jenjang=primary&kelas=lintas-kelas');assert.equal(await editor.$$eval('main a[href*="kelola/arena"]',e=>e.length),0);assert.equal((await editor.goto(base+payment)).status(),403);
 const parent=await login(2);assert.equal((await parent.goto(base+path)).status(),403);
 for(const url of ['/kelola/arena','/kelola/trafik','/belajar/arena'])await visit(root,url);
 assert.deepEqual(errors,[]);console.log('PASS 8 master types + Battle, all 97 category branches and 190 activity links, hierarchy/keyboard/Back, desktop/mobile, payment inline validation/no secret echo, roles and bad filters.');
}finally{await browser.close();}
