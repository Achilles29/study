import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097',browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox','--disable-dev-shm-usage']});const errors=[];
async function actor(id){const context=await browser.createBrowserContext(),page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await page.goto(base+'/masuk');await page.type('[name=identity]','monitor'+id);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('button[type=submit]')]);return page;}
async function visit(page,path){const r=await page.goto(base+path);assert.equal(r.status(),200,path);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow '+path);assert.ok(!(await page.content()).includes('Severity:'));}
try{
 const root=await actor(1),parent=await actor(2),offer=sql('SELECT MIN(id) FROM st_commerce_offers'),order=sql('SELECT MIN(id) FROM st_payment_orders');
 for(const width of [1280,360]){
  for(const page of [root,parent])await page.setViewport({width,height:900});
  for(const path of ['/kelola/paket','/kelola/paket/0','/kelola/produk/'+offer,'/kelola/pesanan','/kelola/kredit','/kelola/kredit/19','/akun/pesanan/'+order])await visit(root,path);
  await visit(root,'/kelola/produk/'+offer);await root.focus('#title');await root.keyboard.press('Tab');assert.equal(await root.evaluate(()=>document.activeElement.id),'kind');await root.screenshot({path:`/tmp/study-commerce-settings-${width}.png`,fullPage:false});
  await visit(parent,'/akun/kredit');await parent.screenshot({path:`/tmp/study-commerce-wallet-${width}.png`,fullPage:false});
 }
 // Actual form edit: draft product only, no external payment.
 await visit(root,'/kelola/produk/0');await root.type('#title','Produk Draft Browser QA');await root.select('#kind','topup');await root.$eval('#price',e=>e.value='20000');await root.$eval('#credit_amount',e=>e.value='200');await root.type('#reason','Uji formulir draft desktop dan mobile');await Promise.all([root.waitForNavigation(),root.click('form[action*="kelola/produk/"] button')]);assert.ok(root.url().match(/kelola\/produk\/\d+$/));assert.equal(await root.$eval('#status',e=>e.value),'draft');
 assert.deepEqual(errors,[]);console.log('PASS commerce browser 1280/360: catalog, package, offer edit, orders, ledger, order detail, family wallet; no overflow/JS errors; keyboard and actual draft save.');
}finally{await browser.close();}
