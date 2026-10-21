import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097', route='/kelola/bank-soal/impor';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const counts=()=>sql('SELECT (SELECT COUNT(*) FROM st_questions),(SELECT COUNT(*) FROM st_bank_imports),(SELECT COUNT(*) FROM st_bank_questions);');
function client(){
 const jar=new Map();
 return async(path,data)=>{
  const multipart=data instanceof FormData;
  const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data&&!multipart?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?(multipart?data:new URLSearchParams(data)):undefined});
  for(const c of r.headers.getSetCookie()){const s=c.split(';')[0],i=s.indexOf('=');jar.set(s.slice(0,i),s.slice(i+1));}
  const bytes=Buffer.from(await r.arrayBuffer()),body=bytes.toString();
  assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body),path);
  return {status:r.status,body,bytes,location:r.headers.get('location')};
 };
}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(name){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
async function upload(c,text,extra={},image,target=route){
 const p=await c(target),f=new FormData();
 f.set('study_csrf_token',field(p.body,'study_csrf_token'));
 f.set('document',new Blob([text]),'naskah.txt');
 for(const [k,v] of Object.entries({mapel:'matematika',kelas:'sd_1',tingkat:'basic',rights_status:'owned',source_note:'QA075 original test; not publishing competition questions',...extra}))f.set(k,v);
 if(image)f.set('images[]',new Blob([image],{type:'image/png'}),'kotak.png');
 return c(target,f);
}
const root=await login('monitor1'),parent=await login('monitor2'),anon=client();
assert.equal((await parent(route)).status,403);
assert.equal((await parent('/kelola/bank-soal/template/naskah')).status,403);
assert.ok([303,307].includes((await anon(route)).status));
const template=await root('/kelola/bank-soal/template/naskah');
assert.equal(template.status,200);assert.ok(template.body.includes('KUNCI JAWABAN'));
const sample=readFileSync(new URL('./fixtures/import075-user.txt',import.meta.url),'utf8');
const before=counts();
let r=await upload(root,sample,{rights_status:'licensed',source_note:'Contoh pemilik untuk uji parser saja'});
assert.equal(r.status,422);
assert.ok(r.body.includes('40 soal</strong>')&&r.body.includes('36 lolos pemeriksaan baris, 4 perlu diperbaiki'));
assert.equal((r.body.match(/class="text-danger">Perlu diperbaiki/g)||[]).length,4);
assert.ok(r.body.includes('Nama gambar')===false);
assert.ok(!r.body.includes('name="confirm"'));
assert.ok(r.body.includes('Contoh pemilik untuk uji parser saja</textarea>'),'Source field retained');
assert.ok(r.body.includes('value="sd_1" selected'),'Grade retained');
assert.equal(counts(),before,'Invalid file creates no bank rows or preview token');
r=await upload(root,template.body,{mapel:'',kelas:''});
assert.equal(r.status,422);assert.ok(r.body.includes('Pilih kelas yang tersedia'));
assert.equal(counts(),before);
r=await upload(root,template.body,{'mapel[]':'matematika'});
assert.equal(r.status,400);assert.equal(counts(),before);
const nonce=Date.now();
const question=`QA075-${nonce} 3 < 5 & kata bukan, kecuali, bagian, kunci jawaban tetap utuh.`;
const block=n=>`${n}. ${question}\na. Satu\nb. Dua\nc. Tiga\nd. Empat\n`;
const valid=`<script>window.bad075=1</script>\n2 Soal Pilihan Ganda\n${block(7)}${block(9)}KUNCI JAWABAN\n7.b 9.b\n`;
const bankBefore=sql('SELECT COUNT(*) FROM st_bank_questions;');
r=await upload(root,valid);assert.equal(r.status,303);
const preview=new URL(r.location).pathname;
assert.equal(sql('SELECT COUNT(*) FROM st_bank_questions;'),bankBefore,'Upload is not commit');
let view=await root(preview);
assert.ok(view.body.includes('Soal 7')&&view.body.includes('Soal 9'));
assert.ok(view.body.includes('identik dengan nomor 7'));
assert.ok(view.body.includes('&lt;script&gt;window.bad075=1&lt;/script&gt;'));
assert.ok(!view.body.includes('<script>window.bad075'));
assert.equal((await parent(preview)).status,403);
assert.equal((await root(preview,{confirm:'1',publish:'1'})).status,403,'CSRF on commit');
view=await root(preview);
assert.equal((await root(preview,{study_csrf_token:field(view.body,'study_csrf_token')})).status,400,'Explicit confirmation required');
view=await root(preview);
assert.equal((await root(preview,{study_csrf_token:field(view.body,'study_csrf_token'),confirm:'1',publish:'1'})).status,303);
view=await root(preview);
assert.ok(view.body.includes('1 soal baru; 1 baris memakai ID'));
assert.equal(Number(sql('SELECT COUNT(*) FROM st_bank_questions;')),Number(bankBefore)+1);
assert.equal((await root(preview,{study_csrf_token:field(view.body,'study_csrf_token'),confirm:'1',publish:'1'})).status,303);
assert.equal(Number(sql('SELECT COUNT(*) FROM st_bank_questions;')),Number(bankBefore)+1,'Replay does not duplicate');
const token=preview.split('/').at(-1);
const id=sql(`SELECT JSON_UNQUOTE(JSON_EXTRACT(result_json,'$.question_ids[0]')) FROM st_bank_imports WHERE token='${token}';`);
assert.equal(sql(`SELECT explanation FROM st_questions WHERE id=${Number(id)};`),'','No fabricated explanation');
assert.equal(sql(`SELECT o.option_text FROM st_question_options o WHERE question_id=${Number(id)} AND is_correct=1;`),'Dua');
assert.ok(!(await root('/kelola/bank-soal/'+id)).body.match(/id="bahasan"[^>]*required/),'Edit allows missing explanation');
const imageText=`1. Lihat gambar PDF asli QA075-${nonce}.\na. Gambar A\nb. Gambar B\nc. Gambar C\nd. Gambar D\nGAMBAR: kotak.png\nPEMBAHASAN: Uraian contoh gambar pengujian.\nKUNCI JAWABAN\n1.c\n`;
assert.equal((await upload(root,imageText)).status,422);
const png=execFileSync('/www/server/php/84/bin/php',['-r',"$im=imagecreatetruecolor(20,20);imagefill($im,0,0,imagecolorallocate($im,40,150,180));imagepng($im);imagedestroy($im);"]);
r=await upload(root,imageText,{},png);assert.equal(r.status,303);
const imagePreview=new URL(r.location).pathname;
assert.equal((await root(imagePreview+'/gambar/0')).status,200);
assert.equal((await parent(imagePreview+'/gambar/0')).status,403);
view=await root(imagePreview);
assert.equal((await root(imagePreview,{study_csrf_token:field(view.body,'study_csrf_token'),confirm:'1'})).status,303);
// One end-to-end learner attempt using the newly imported, explanation-free question.
const examValues={title:'QA075 naskah '+nonce,grade_id:'3',duration_minutes:'15',max_daily:'3',credit_price:'0',status:'draft',question_shuffle:'1',option_shuffle:'1',revision:'0',action:'save'};
view=await root('/kelola/simulasi-ujian/0');
r=await root('/kelola/simulasi-ujian/0',{...examValues,study_csrf_token:field(view.body,'study_csrf_token')});
assert.equal(r.status,303);
const examPath=new URL(r.location).pathname,eid=examPath.split('/').at(-1);
r=await upload(root,valid,{},undefined,route+'?ujian='+eid);assert.equal(r.status,303);
const examPreview=new URL(r.location).pathname;
view=await root(examPreview);
assert.equal((await root(examPreview,{study_csrf_token:field(view.body,'study_csrf_token'),confirm:'1',publish:'1'})).status,303);
assert.equal(sql(`SELECT GROUP_CONCAT(question_id) FROM st_exam_questions WHERE exam_id=${Number(eid)};`),id,'Exam references same bank ID');
view=await root(examPath);
assert.equal((await root(examPath,{...examValues,status:'published',revision:field(view.body,'revision'),study_csrf_token:field(view.body,'study_csrf_token')})).status,303);
view=await parent('/latihan/ujian/'+eid);
r=await parent('/latihan/ujian/'+eid,{start_token:field(view.body,'start_token'),study_csrf_token:field(view.body,'study_csrf_token')});
assert.equal(r.status,303);
const attemptPath=new URL(r.location).pathname,aid=attemptPath.split('/').at(-1);
view=await parent(attemptPath);
assert.ok(!view.body.includes('Jawaban benar'),'Active attempt does not expose keys');
const oid=sql(`SELECT id FROM st_question_options WHERE question_id=${Number(id)} AND is_correct=1;`);
assert.equal((await parent(attemptPath,{action:'finish',revision:field(view.body,'revision'),[`answers[${id}]`]:oid,study_csrf_token:field(view.body,'study_csrf_token')})).status,303);
view=await parent(attemptPath);
assert.ok(view.body.includes('Pembahasan belum tersedia.') && view.body.includes('Jawaban benar'),'Honest graded explanation fallback');
assert.equal(sql(`SELECT score FROM st_practice_attempts WHERE id=${Number(aid)};`),'100.00');
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const page=await browser.newPage(),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/masuk');await page.type('[name=identity]','monitor1');await page.type('[name=password]','Monitor-QA-Only-2026!');
 await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});
  for(const path of [route,preview,imagePreview]){
   assert.equal((await page.goto(base+path)).status(),200);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path+' '+width);
   assert.equal(await page.evaluate(()=>window.bad075),undefined);
  }
  await page.goto(base+route);
  await (await page.$('#document')).uploadFile(new URL('./fixtures/import075-user.txt',import.meta.url).pathname);
  await page.select('#mapel','matematika');await page.select('#kelas','sd_1');await page.select('#rights_status','licensed');
  await page.type('#source_note','QA075 test sample from owner, no publication');
  const [response]=await Promise.all([page.waitForNavigation(),page.click('form[action$="/kelola/bank-soal/impor"] button[type=submit]')]);assert.equal(response.status(),422);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Invalid report '+width);
  const summary=await page.$('section[aria-labelledby="import-result"] details.border summary');
  await summary.focus();await page.keyboard.press('Enter');
  assert.ok(await summary.evaluate(el=>el.parentElement.open),'Keyboard opens full question');
  await page.evaluate(()=>document.getElementById('import-result').scrollIntoView({behavior:'instant',block:'start'}));
  await page.screenshot({path:`/tmp/import075-review-${width}.png`});
 }
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
console.log('PASS 075 HTTP/browser: exact40 -> 36 complete/4 blocked and zero writes; form retention; roles/CSRF/confirm; valid preview before commit; original numbers/XSS; duplicate reuse/replay; named image; exam shared ID + learner 100% + honest missing explanation; desktop/mobile/keyboard.');
