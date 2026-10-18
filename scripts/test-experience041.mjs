import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {writeFileSync} from 'node:fs';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const base='http://127.0.0.1:8097',password='Monitor-QA-Only-2026!';
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']}),errors=[],contrast=[];
async function pageFor(user){const context=await browser.createBrowserContext(),p=await context.newPage();p.on('pageerror',e=>errors.push(e.message));if(user){await p.goto(base+'/masuk');await p.type('#identity','monitor'+user);await p.type('#password',password);await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type="submit"]')]);assert.equal(new URL(p.url()).pathname,user===1?'/kelola':'/beranda');}return p;}
async function check(p,path,width,screenshot){await p.setViewport({width,height:900});const r=await p.goto(base+path,{waitUntil:'networkidle0'});assert.equal(r.status(),200,path);assert.ok(!/Severity:|Fatal error|Database Error/.test(await p.content()),path);assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path+' '+width);await p.evaluate(async()=>{for(const i of document.images){if(i.getAttribute('loading')==='lazy')i.loading='eager';}await Promise.all(Array.from(document.images).map(i=>i.decode().catch(()=>{})));});assert.deepEqual(await p.$$eval('img',images=>images.filter(i=>!i.complete||!i.naturalWidth).map(i=>i.getAttribute('src'))),[],path+' images');
const issues=await p.evaluate(()=>{
 const rgb=s=>{const m=s.match(/rgba?\(([^)]+)\)/);return m?m[1].split(/[,\s/]+/).filter(Boolean).map(Number):null;};
 const lum=a=>a.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);
 const ratio=(a,b)=>(Math.max(lum(a),lum(b))+.05)/(Math.min(lum(a),lum(b))+.05);
 const failures=[];
 for(const el of document.querySelectorAll('h1,h2,h3,p,span,a,button,label,summary,small,strong,li')){
  const st=getComputedStyle(el),rect=el.getBoundingClientRect();if(!rect.width||!rect.height||el.closest('[hidden]')||el.matches(':disabled')||st.visibility==='hidden'||Number(st.opacity)<1||!Array.from(el.childNodes).some(n=>n.nodeType===3&&n.textContent.trim()))continue;
  const fg=rgb(st.color);if(!fg)continue;let backgrounds=[];
  for(let node=el;node;node=node.parentElement){const style=getComputedStyle(node);if(style.backgroundImage.includes('gradient')){backgrounds=[...style.backgroundImage.matchAll(/rgba?\([^)]+\)/g)].map(m=>rgb(m[0])).filter(c=>c.length===3||c[3]===1);if(backgrounds.length)break;}const c=rgb(style.backgroundColor);if(c&&(c.length===3||c[3]>=.99)){backgrounds=[c];break;}}
  if(!backgrounds.length)backgrounds=[[255,255,255]];const actual=Math.min(...backgrounds.map(bg=>ratio(fg,bg)));const required=parseFloat(st.fontSize)>=24||(parseFloat(st.fontSize)>=18.66&&parseInt(st.fontWeight)>=700)?3:4.5;
  if(actual+0.02<required)failures.push({tag:el.tagName,class:el.className,text:el.textContent.trim().slice(0,65),ratio:+actual.toFixed(2),required});
 }return failures;
});if(issues.length)contrast.push({path,width,issues});if(screenshot)await p.screenshot({path:'/tmp/study-ui041-'+screenshot+'-'+width+'.png',fullPage:true});}
try{
 const guest=await pageFor();for(const width of [1280,360])for(const path of ['/','/masuk','/daftar'])await check(guest,path,width,path==='/'?'public':path.slice(1));
 await guest.goto(base+'/masuk');await guest.type('#password','Visibility-Test-Only');const eye=await guest.$('.password-toggle');assert.ok(eye);await eye.focus();await guest.keyboard.press('Enter');assert.equal(await guest.$eval('#password',e=>e.type),'text');assert.equal(await guest.$eval('.password-toggle',e=>e.getAttribute('aria-pressed')),'true');await guest.keyboard.press('Enter');assert.equal(await guest.$eval('#password',e=>e.type),'password');assert.equal(await guest.$eval('#password',e=>e.value),'Visibility-Test-Only');assert.match(guest.url(),/masuk$/);
 const admin=await pageFor(1),parent=await pageFor(2),adult=await pageFor(19),child=await pageFor(4);
 for(const width of [1280,360]){for(const path of ['/kelola','/kelola/pengaturan/google'])await check(admin,path,width,'admin');for(const path of ['/beranda','/akun/pengaturan','/belajar/arena','/belajar/arena/game/pasangan','/belajar/arena/game/math-expedition','/belajar/arena/game/kelas-inggris','/belajar/arena/game/english-quest','/belajar/battle'])await check(parent,path,width,path.replaceAll('/','-'));}
 await parent.goto(base+'/beranda');assert.equal(await parent.$$('main a[href$="/akun/email"]').then(x=>x.length),0);assert.equal(await parent.$$('main a[href$="/akun/google"]').then(x=>x.length),0);assert.ok(await parent.$('a[href$="/akun/pengaturan"]'));
 await parent.goto(base+'/akun/pengaturan');assert.ok(await parent.$('main a[href$="/akun/email"]'));assert.ok(await parent.$('main a[href$="/akun/google"]'));assert.ok(await parent.$('main a[href$="/akun/keamanan"]'));
 for(const p of [adult,child]){await check(p,'/beranda',360);await check(p,'/akun/pengaturan',360);assert.equal((await p.goto(base+'/kelola')).status(),403);}
 await child.goto(base+'/akun/pengaturan');assert.equal(await child.$$('main a[href$="/akun/email"]').then(x=>x.length),0);assert.equal(await child.$$('main a[href$="/akun/google"]').then(x=>x.length),0);
 await parent.goto(base+'/belajar/arena');assert.equal(await parent.$$eval('[data-arena-game] img',els=>els.length),9);
 const slugs=sql("SELECT slug FROM st_arena_packs WHERE enabled=1 GROUP BY game_type ORDER BY game_type").split('\n');for(const slug of slugs)await check(parent,'/belajar/arena/pilih/'+slug,360,'detail-'+slug);
 assert.deepEqual(errors,[]);writeFileSync('/tmp/study-ui041-contrast.json',JSON.stringify(contrast,null,2));assert.deepEqual(contrast,[],'contrast issues saved to /tmp/study-ui041-contrast.json');
 console.log('PASS experience: responsive 1280/360, all category/topic images loaded, navigation and account hub scopes, admin landing, password eye keyboard/no submit/no value loss, shared text contrast samples, no overflow or JS errors.');
}finally{await browser.close();}
