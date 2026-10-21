import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFileSync,readdirSync} from 'node:fs';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',route='/kelola/bank-soal/impor',nonce=Date.now();
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{
 const multi=data instanceof FormData,r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{'User-Agent':'ProjectPintar-QA/auto079',Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data&&!multi?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?(multi?data:new URLSearchParams(data)):undefined});
 for(const c of r.headers.getSetCookie()){const s=c.split(';')[0],i=s.indexOf('=');jar.set(s.slice(0,i),s.slice(i+1));}
 const bytes=Buffer.from(await r.arrayBuffer()),body=bytes.toString();assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body),path);
 return{status:r.status,body,bytes,location:r.headers.get('location')};
};}
const field=(body,name)=>{const m=body.match(new RegExp(`name="${name}" value="([^"]*)"`));assert.ok(m,name);return m[1];};
async function login(name){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
const root=await login('monitor1'),child=await login('monitor4'),other=await login('monitor11');
async function upload(text,files,target=route){
 const p=await root(target),form=new FormData();
 for(const [k,v]of Object.entries({mapel:'matematika',kelas:'sd_1',tingkat:'basic',rights_status:'owned',source_note:'QA079 diagnostic fixture; no public publication',study_csrf_token:field(p.body,'study_csrf_token'),images_expected:String(files.length)}))form.set(k,v);
 form.set('document',new Blob([text]),'soal.txt');
 for(const f of files)form.append('images[]',new Blob([f.bytes]),f.name);
 return root(target,form);
}
// Read owner-supplied files; correct JPEG filename aliases only in the request.
// Neither originals nor question wording/keys are overwritten.
let sample=readFileSync('docs/testgambar/KMSI 2025 D - MTK LEVEL 1.txt','utf8');
const sampleFiles=readdirSync('docs/testgambar').filter(n=>/\.(png|jpe?g)$/i.test(n)).map(original=>{
 const bytes=readFileSync('docs/testgambar/'+original),jpeg=bytes[0]===255&&bytes[1]===216;
 const name=jpeg?original.replace(/\.[^.]+$/,'.jpg'):original;
 sample=sample.replaceAll(`[GAMBAR: ${original}]`,`[GAMBAR: ${name}]`);
 return{name,bytes};
});
let r=await upload(sample,[...sampleFiles].reverse());assert.equal(r.status,303,r.body);
const samplePreview=new URL(r.location).pathname;let page=await root(samplePreview);
assert.ok(page.body.includes('Pratinjau 30 soal'));assert.equal((page.body.match(/Gambar otomatis di atas soal:/g)||[]).length,4);
for(const name of ['soal7.jpg','soal9.png','soal17.jpg','soal18.jpg'])assert.ok(page.body.includes(name));
assert.equal((page.body.match(/class="question-inline-image"/g)||[]).length,6);
// No commit of owner sample: synthetic content only for persistence/attempt checks.
const png=i=>execFileSync('/www/server/php/84/bin/php',['-r',`$im=imagecreatetruecolor(180,80);imagefill($im,0,0,imagecolorallocate($im,240,240,255));imagestring($im,5,20,30,"Auto079 ${i}",imagecolorallocate($im,20,20,100));imagepng($im);`]);
const files=[{name:'soal7-2.png',bytes:png(2)},{name:'inline.png',bytes:png(3)},{name:'soal7-1.png',bytes:png(1)},{name:'jawaban.png',bytes:png(4)}];
const text=`7. QA079-${nonce} Nilai [GAMBAR: inline.png] adalah?\na. [GAMBAR: jawaban.png]\nb. Dua\nc. Tiga\nd. Empat\nKUNCI JAWABAN\n7.a`;
const before=sql('SELECT COUNT(*) FROM st_bank_imports;');
r=await upload(text,[...files,{name:'soal99.png',bytes:png(5)}]);assert.equal(r.status,422);assert.ok(r.body.includes('soal99.png'));assert.equal(sql('SELECT COUNT(*) FROM st_bank_imports;'),before);
const ev={title:'QA079 automatic '+nonce,grade_id:'3',duration_minutes:'20',max_daily:'3',credit_price:'0',status:'draft',question_shuffle:'1',option_shuffle:'1',revision:'0',action:'save'};
page=await root('/kelola/simulasi-ujian/0');r=await root('/kelola/simulasi-ujian/0',{...ev,study_csrf_token:field(page.body,'study_csrf_token')});assert.equal(r.status,303);
const examPath=new URL(r.location).pathname,eid=Number(examPath.split('/').at(-1));
r=await upload(text,files,route+'?ujian='+eid);assert.equal(r.status,303);const preview=new URL(r.location).pathname;
page=await root(preview);assert.equal((await root(preview,{confirm:'1',publish:'1',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
const qid=Number(sql(`SELECT question_id FROM st_exam_questions WHERE exam_id=${eid};`));
const saved=await root('/kelola/bank-soal/'+qid);assert.ok(saved.body.includes('[GAMBAR: soal7-1.png]'));assert.ok(saved.body.includes('[GAMBAR: soal7-2.png]'));
page=await root(examPath);assert.equal((await root(examPath,{...ev,status:'published',revision:field(page.body,'revision'),study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
page=await child('/latihan/ujian/'+eid);r=await child('/latihan/ujian/'+eid,{start_token:field(page.body,'start_token'),study_csrf_token:field(page.body,'study_csrf_token')});assert.equal(r.status,303);const attempt=new URL(r.location).pathname;
assert.equal((await child(attempt+`/gambar/${qid}/0`)).status,200);assert.equal((await other(attempt+`/gambar/${qid}/0`)).status,404);
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const p=await browser.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 async function bl(name){await p.deleteCookie(...await p.cookies());await p.goto(base+'/masuk');await p.type('[name=identity]',name);await p.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([p.waitForNavigation(),p.click('form[action$="/masuk"] button[type=submit]')]);}
 for(const [name,path]of [['monitor1',preview],['monitor4',attempt]]){
  await bl(name);
  for(const width of [1280,360]){
   await p.setViewport({width,height:900});await p.goto(base+path,{waitUntil:'networkidle0'});
   await p.$$eval('.question-inline-image',els=>els.forEach(e=>e.loading='eager'));
   await p.waitForFunction(()=>[...document.querySelectorAll('.question-inline-image')].every(e=>e.complete&&e.naturalWidth>0));
   assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   assert.ok(await p.$eval('p.question-rich-text',e=>{const images=e.querySelectorAll('img');const text=[...e.childNodes].find(n=>n.nodeType===3&&n.textContent.includes('QA079'));return images.length===3&&(images[1].compareDocumentPosition(text)&Node.DOCUMENT_POSITION_FOLLOWING)!==0;}));
   assert.equal(await p.$$eval('.question-inline-image',els=>els.length),4);
   await p.$eval('p.question-rich-text',e=>e.scrollIntoView({behavior:'instant',block:'center'}));await p.screenshot({path:`/tmp/auto079-${name}-${width}.png`});
  }
 }
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
page=await child(attempt);const correct=sql(`SELECT id FROM st_question_options WHERE question_id=${qid} AND is_correct=1;`);
assert.equal((await child(attempt,{action:'finish',revision:field(page.body,'revision'),study_csrf_token:field(page.body,'study_csrf_token'),[`answers[${qid}]`]:correct})).status,303);
assert.equal(sql(`SELECT score FROM st_practice_attempts WHERE id=${Number(attempt.split('/').at(-1))};`),'100.00');
console.log('PASS079: owner sample30 preview/6 images/4 automatic (no commit), unmatched file422/no preview write, synthetic commit+exam+child grading100, private image guard, automatic order ahead of text, inline/answer unchanged, real images at1280/360.');
