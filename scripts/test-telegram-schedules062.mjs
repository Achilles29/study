import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.TELEGRAM_SCHEDULE_QA,'study_qa_monitor_sso039');
const sql=q=>execFileSync('mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','--socket=/tmp/mysql-study.sock','-N','-B','--raw','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const snapshot=JSON.parse(sql("SELECT JSON_ARRAYAGG(JSON_OBJECT('category',category,'mode',mode,'interval_minutes',interval_minutes,'daily_time',daily_time,'template',template,'send_empty',send_empty,'revision',revision,'last_window_at',last_window_at,'next_due_at',next_due_at,'updated_at',updated_at)) FROM st_telegram_message_policies"));
const esc=v=>v===null?'NULL':"CONVERT(0x"+Buffer.from(String(v)).toString('hex')+" USING utf8mb4)";
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});const errors=[];let checks=0;
try{
 const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
 const base='http://127.0.0.1:8098';
 async function login(p,user){await p.goto(base+'/masuk');await p.type('[name=identity]',user);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);}
 await login(page,'monitor1');
 for(const width of [1440,390,320]){await page.setViewport({width,height:1000});for(const category of ['visitors','members','children','payments','donations','help']){
  const res=await page.goto(base+'/kelola/pengaturan/telegram?tab=messages&category='+category);assert.equal(res.status(),200);assert.ok(!/A PHP Error|Fatal error/.test(await page.content()));assert.equal(await page.$$eval('#telegram-message-form',e=>e.length),1);assert.ok(await page.$eval('#message-preview',e=>e.textContent.trim().length)>0);
  const dimensions=await page.evaluate(()=>({w:document.documentElement.clientWidth,s:document.documentElement.scrollWidth}));assert.ok(dimensions.s<=dimensions.w+1,JSON.stringify({category,width,dimensions}));checks++;
  if(category==='visitors'){assert.equal(await page.$('#message-mode option[value=realtime]'),null);await page.screenshot({path:'docs/telegram-schedules/settings-'+width+'.png',fullPage:true});}
 }}
 await page.setViewport({width:1440,height:1000});await page.goto(base+'/kelola/pengaturan/telegram?tab=messages&category=visitors');
 await page.click('[data-interval="720"]');assert.equal(await page.$eval('#message-interval',e=>e.value),'720');
 await Promise.all([page.waitForNavigation(),page.click('#telegram-message-form button[type=submit]')]);assert.equal(await page.$eval('#message-interval',e=>e.value),'720');assert.equal(sql("SELECT interval_minutes FROM st_telegram_message_policies WHERE category='visitors'"),'720');
 await page.select('#message-mode','daily');await page.$eval('#message-time',e=>e.value='08:45');
 const custom='Pagi! {periode}\nAda {kunjungan} akses.\n<em>Ini teks biasa</em>\n{tautan}';
 await page.$eval('#message-template',(e,v)=>{e.value=v;e.dispatchEvent(new Event('input'));},custom);
 assert.ok((await page.$eval('#message-preview',e=>e.textContent)).includes('240 akses'));assert.equal(await page.$('#message-preview em'),null);
 await Promise.all([page.waitForNavigation(),page.click('#telegram-message-form button[type=submit]')]);assert.equal(await page.$eval('#message-mode',e=>e.value),'daily');assert.equal(await page.$eval('#message-time',e=>e.value),'08:45');assert.equal(await page.$eval('#message-template',e=>e.value),custom);
 // Unknown variables must be rejected, preserving the entered text and existing settings.
 const bad=custom+' {secret}';await page.$eval('#message-template',(e,v)=>e.value=v,bad);const invalid=await Promise.all([page.waitForNavigation(),page.click('#telegram-message-form button[type=submit]')]);assert.equal(invalid[0].status(),400);assert.equal(await page.$eval('#message-template',e=>e.value),bad);assert.equal(sql("SELECT template FROM st_telegram_message_policies WHERE category='visitors'"),custom);
 // A stale form stays stale; reload is required before replacing another edit.
 await page.goto(base+'/kelola/pengaturan/telegram?tab=messages&category=members');const rev=await page.$eval('#telegram-message-form [name=revision]',e=>e.value);sql("UPDATE st_telegram_message_policies SET revision=revision+1 WHERE category='members'");
 const conflict=await Promise.all([page.waitForNavigation(),page.click('#telegram-message-form button[type=submit]')]);assert.equal(conflict[0].status(),409);assert.equal(await page.$eval('#telegram-message-form [name=revision]',e=>e.value),rev);
 for(const tab of ['connection','notifications','ai','history']){assert.equal((await page.goto(base+'/kelola/pengaturan/telegram?tab='+tab)).status(),200);checks++;}
 const ctx=await browser.createBrowserContext();const parent=await ctx.newPage();await login(parent,'monitor2');assert.equal((await parent.goto(base+'/kelola/pengaturan/telegram?tab=messages')).status(),403);
 assert.deepEqual(errors,[]);const report={responsiveViews:checks,widths:[1440,390,320],intervalSave:'720 minutes persisted',dailySave:'08:45 WIB persisted',templatePreview:'plain text; no HTML execution',invalidTemplate:'400 with input preserved',staleRevision:'409; reload required',nonAdmin:'403',browserErrors:errors,externalMessages:0};await fs.writeFile('docs/telegram-schedules/validation.json',JSON.stringify(report,null,2));console.log(report);
}finally{
 await browser.close();for(const row of snapshot)sql('UPDATE st_telegram_message_policies SET '+Object.entries(row).filter(([k])=>k!=='category').map(([k,v])=>'`'+k+'`='+esc(v)).join(',')+' WHERE category='+esc(row.category));
}
