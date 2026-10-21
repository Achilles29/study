import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097', endpoint='/kelola/trafik/live';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text(),headers:r.headers};};}
const field=(body,name)=>body.match(new RegExp(`name="${name}" value="([^"]*)"`))?.[1];
async function login(name){const c=client(),r=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(r.body,'study_csrf_token')})).status,303);return c;}
const root=await login('monitor1'),parent=await login('monitor2'),child=await login('monitor4');
assert.ok([303,307].includes((await client()(endpoint)).status));for(const user of[parent,child])assert.equal((await user(endpoint)).status,403);
const dash=await root('/kelola');assert.equal(dash.status,200);assert.ok(dash.body.includes('traffic-live'));
assert.equal((await root(endpoint,{study_csrf_token:field(dash.body,'study_csrf_token')})).status,405);
// Current, bot, expired, future and WIB midnight boundary fixtures. Only QA DB.
const insert=(created,expires,bot)=>sql(`INSERT INTO st_operational_events(created_at,expires_at,page_key,user_id,account_kind,ip_address,country,device,browser,os,is_bot) VALUES(${created},${expires},'home/index',NULL,'guest','192.0.2.86','ID','Desktop','QA086','QA086',${bot})`);
insert('UTC_TIMESTAMP()-INTERVAL 1 SECOND','UTC_TIMESTAMP()+INTERVAL 7 DAY',0);
insert('UTC_TIMESTAMP()-INTERVAL 1 SECOND','UTC_TIMESTAMP()+INTERVAL 7 DAY',1);
insert('UTC_TIMESTAMP()-INTERVAL 1 SECOND','UTC_TIMESTAMP()-INTERVAL 1 SECOND',0);
insert('UTC_TIMESTAMP()+INTERVAL 1 DAY','UTC_TIMESTAMP()+INTERVAL 7 DAY',0);
insert("DATE(UTC_TIMESTAMP()+INTERVAL 7 HOUR)-INTERVAL 7 HOUR-INTERVAL 1 SECOND",'UTC_TIMESTAMP()+INTERVAL 6 DAY',0);
insert("DATE(UTC_TIMESTAMP()+INTERVAL 7 HOUR)-INTERVAL 7 HOUR",'UTC_TIMESTAMP()+INTERVAL 6 DAY',1);
const count=sql('SELECT COUNT(*) FROM st_operational_events');
const response=await root(endpoint);assert.equal(response.status,200);assert.match(response.headers.get('cache-control'),/no-store/);assert.match(response.headers.get('content-type'),/application\/json/);assert.match(response.headers.get('x-robots-tag'),/noindex/);
const data=JSON.parse(response.body);assert.equal(data.daily.length,7);assert.equal(data.hourly.length,24);assert.equal(data.refresh_seconds,30);assert.equal(data.timezone,'Asia/Jakarta');assert.equal(data.summary.views,data.summary.non_bot+data.summary.bots);
assert.equal(data.hourly.reduce((s,x)=>s+x.views,0),data.summary.views);
for(const forbidden of['ip_address','user_id','display_name','country','browser','destination_path'])assert.ok(!response.body.includes('"'+forbidden+'"'),'No identities '+forbidden);
const cutoff=new Date(data.generated_at).toISOString().slice(0,19).replace('T',' '),start=data.daily[0].key;
const expected=sql(`SELECT DATE(created_at+INTERVAL 7 HOUR),COUNT(*),SUM(is_bot=1) FROM st_operational_events WHERE created_at>=DATE('${start}')-INTERVAL 7 HOUR AND created_at<='${cutoff}' AND expires_at>'${cutoff}' GROUP BY 1`).split('\n').filter(Boolean).map(r=>r.split('\t'));
for(const[day,views,bots]of expected){const got=data.daily.find(d=>d.key===day);assert.ok(got);assert.equal(got.views,Number(views));assert.equal(got.bots,Number(bots));}
await root(endpoint);await root(endpoint);assert.equal(sql('SELECT COUNT(*) FROM st_operational_events'),count,'Polling cannot increase views');
console.log('PASS role/auth/method/no-store, no identities, WIB/day/hour totals, expiry/future exclusion, polling not counted');
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});const page=await browser.newPage(),errors=[];let mode='normal',requests=0,latest=data;page.on('pageerror',e=>errors.push(e.message));
await page.setRequestInterception(true);page.on('request',r=>{if(new URL(r.url()).pathname===endpoint){requests++;if(mode==='fail')return r.respond({status:503,contentType:'application/json',body:'{"ok":false}'});if(mode==='zero'||mode==='disabled'){const z=structuredClone(latest);z.enabled=mode!=='disabled';z.updated_label='21/10/2026 23:59:59 WIB';if(mode==='zero'){for(const p of[...z.daily,...z.hourly])p.views=p.bots=p.non_bot=0;Object.keys(z.summary).forEach(k=>z.summary[k]=0);}return r.respond({status:200,contentType:'application/json',body:JSON.stringify(z)});}}r.continue();});
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
try{
 await page.goto(base+'/masuk');await page.type('[name=identity]','monitor1');await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);
 await page.goto(base+'/kelola');await page.waitForSelector('.traffic-live-column');
 for(const width of[1280,360]){await page.setViewport({width,height:850});assert.equal(await page.$$eval('.traffic-live-column',es=>es.length),7);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.$eval('#traffic-live',e=>e.scrollIntoView());await page.screenshot({path:`/tmp/traffic086-daily-${width}.png`,fullPage:true});await page.focus('[data-live-range="hourly"]');await page.keyboard.press('Enter');assert.equal(await page.$$eval('.traffic-live-column',es=>es.length),24);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.ok(await page.$eval('.traffic-live-scroll',e=>e.scrollWidth>e.clientWidth||innerWidth>1000));await page.click('[data-live-range="daily"]');}
 // A real 30-second poll, not a timer substitution.
 const beforePoll=requests;await page.waitForFunction(()=>document.querySelector('[data-live-status]').textContent.startsWith('Diperbarui'));await sleep(31000);assert.ok(requests>beforePoll,'Automatic 30-second poll');
 const before=await page.$$eval('[data-live-metric]',es=>es.map(e=>e.textContent));mode='fail';await page.click('[data-live-refresh]');await page.waitForFunction(()=>document.querySelector('[data-live-status]').textContent.includes('Angka terakhir'));assert.deepEqual(await page.$$eval('[data-live-metric]',es=>es.map(e=>e.textContent)),before,'Failure preserves last numbers');
 mode='normal';await page.click('[data-live-refresh]');await page.waitForFunction(()=>document.querySelector('[data-live-status]').textContent.startsWith('Diperbarui'));
 mode='zero';await page.click('[data-live-refresh]');await page.waitForFunction(()=>document.querySelector('[data-live-metric="views"]').textContent==='0');assert.ok(await page.$eval('[data-live-chart]',e=>!e.innerHTML.includes('NaN')),'Zero safe');
 mode='disabled';await page.click('[data-live-refresh]');await page.waitForFunction(()=>document.querySelector('[data-live-badge]').textContent==='Pencatatan nonaktif');
 // Simulate hidden-tab transition and ensure refresh does not issue requests.
 await page.evaluate(()=>{Object.defineProperty(document,'hidden',{value:true,configurable:true});document.dispatchEvent(new Event('visibilitychange'));});const hiddenCount=requests;await page.click('[data-live-refresh]');await sleep(500);assert.equal(requests,hiddenCount);
 mode='normal';await page.evaluate(()=>{Object.defineProperty(document,'hidden',{value:false,configurable:true});document.dispatchEvent(new Event('visibilitychange'));});await page.waitForFunction(()=>document.querySelector('[data-live-badge]').textContent!=='Pencatatan nonaktif');assert.ok(requests>hiddenCount);
 assert.deepEqual(errors,[]);console.log('PASS desktop/mobile/keyboard, daily/hourly, real 30s poll, offline retains values/recovery, zero/disabled states, hidden pause/resume, no JS errors');
}finally{await browser.close();}
