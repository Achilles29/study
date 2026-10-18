import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});
const base='http://127.0.0.1:8097',errors=[];const today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
async function login(id){const context=await browser.createBrowserContext(),p=await context.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(base+'/masuk');await p.type('[name=identity]','monitor'+id);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);return p;}
async function visit(p,path){const r=await p.goto(base+path);assert.equal(r.status(),200,path);assert.ok(!/Severity:|Fatal error|Database Error/.test(await p.content()),path);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);}
try{
 const root=await login(1);
 for(const width of [1280,768,360]){
  await root.setViewport({width,height:900});
  for(const [name,path] of [['dashboard','/kelola'],['arena','/kelola/arena'],['memory','/kelola/arena?jenis=memory'],['master','/kelola/kategori'],['grades','/kelola/kategori?tab=jenjang'],['game-master','/kelola/kategori?tab=permainan']]){
   await visit(root,path);
   if(name==='arena'){assert.equal(await root.$$eval('[name=pack_id]',e=>e.length),0);assert.equal(await root.$$eval('.admin-card-grid article',e=>e.length),9);}
   if(name==='memory'){assert.equal(await root.$$eval('[name=pack_id]',e=>e.length),15);assert.ok((await root.content()).includes('Hewan dan Suaranya'));}
   if(name==='grades')assert.equal(await root.$$eval('main .admin-card-grid li',e=>e.length),11);
   if(width!==768)await root.screenshot({path:`/tmp/study-admin034-${name}-${width}.png`,fullPage:true});
  }
  for(const tab of ['ringkasan','halaman','pengguna']){
   await visit(root,`/kelola/trafik/${tab}?from=${today}&to=${today}`);
   assert.equal(await root.$$eval('main form',e=>e.length),1,'One coherent filter form');
   if(tab==='ringkasan'){assert.ok((await root.content()).includes('Belum ada event tersimpan'));await root.screenshot({path:`/tmp/study-admin034-traffic-empty-${width}.png`,fullPage:true});}
  }
 }
 // Populated analytics use existing QA fixture dates, without enabling production tracking.
 const date=sql("SELECT DATE(CONVERT_TZ(created_at,'+00:00','+07:00')) FROM st_traffic_events ORDER BY created_at DESC LIMIT 1");
 if(date){await visit(root,`/kelola/trafik/ringkasan?from=${date}&to=${date}`);assert.ok(await root.$$eval('progress',e=>e.length)>0);await root.screenshot({path:'/tmp/study-admin034-traffic-data-360.png',fullPage:true});}
 // Keyboard navigation to category and real toggle action (restore availability afterwards).
 await visit(root,'/kelola');await root.focus('main details summary');await root.keyboard.press('Enter');assert.ok(await root.$eval('main details',e=>e.open),'Dashboard menus expand with keyboard');
 for(const width of [1280,360]){await root.setViewport({width,height:900});for(const path of ['/kelola/pengaturan','/kelola/paket','/kelola/pengaturan/email','/kelola/operasional','/kelola/pembelajaran'])await visit(root,path);}
 await visit(root,'/kelola/arena');await root.focus('a[href$="?jenis=memory"]');await Promise.all([root.waitForNavigation(),root.keyboard.press('Enter')]);assert.ok(root.url().includes('jenis=memory'));
 await root.select('#arena-stage','primary');await Promise.all([root.waitForNavigation(),root.click('main form[method=get] button')]);assert.ok(root.url().includes('jenjang=primary'));
 const pack=await root.$eval('[data-arena-pack]',e=>e.dataset.arenaPack);const before=sql('SELECT enabled FROM st_arena_packs WHERE id='+Number(pack));
 const button=`[data-arena-pack="${pack}"] button`;await Promise.all([root.waitForNavigation(),root.click(button)]);assert.notEqual(sql('SELECT enabled FROM st_arena_packs WHERE id='+Number(pack)),before);assert.ok(root.url().includes('jenjang=primary'));
 await Promise.all([root.waitForNavigation(),root.click(button)]);assert.equal(sql('SELECT enabled FROM st_arena_packs WHERE id='+Number(pack)),before);
 const editor=await login(6);await visit(editor,'/kelola');assert.equal(await editor.$$eval('main a[href$="kelola/trafik"]',e=>e.length),0);assert.equal((await editor.goto(base+'/kelola/trafik')).status(),403);assert.equal((await editor.goto(base+'/kelola/arena')).status(),403);await visit(editor,'/kelola/kategori?tab=permainan');assert.equal(await editor.$$eval('main a[href*="kelola/arena"]',e=>e.length),0);
 const learner=await login(2);assert.equal((await learner.goto(base+'/kelola/kategori')).status(),403);assert.equal((await root.goto(base+'/kelola/arena?jenis[]=memory')).status(),400);assert.equal((await root.goto(base+'/kelola/arena?jenis=memory&kategori=999999')).status(),400);assert.equal((await root.goto(base+'/kelola/kategori?tab[]=mapel')).status(),400);
 assert.deepEqual(errors,[]);console.log('PASS admin 1280/768/360: dashboard role cards, Arena category-first/drilldown/stage filter/keyboard/toggle restored, 3 master tabs, empty and populated traffic with one filter form, roles/invalid filters, no overflow/JS errors.');
}finally{await browser.close();}
