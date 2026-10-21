import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readdirSync} from 'node:fs';
import puppeteer from '/tmp/finance2-studio-work/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
assert.equal(process.env.STUDY_MONITOR_TEST_DB,'study_qa_monitor_sso039');
const base='http://127.0.0.1:8097',route='/kelola/bank-soal/impor';
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'],{input:q,encoding:'utf8'}).trim();
const mediaCount=()=>readdirSync('/www/study-media-qa047-ZPze1qZD').length;
const counts=()=>sql('SELECT (SELECT COUNT(*) FROM st_questions),(SELECT COUNT(*) FROM st_bank_imports);');
function client(){const jar=new Map();return async(path,data)=>{
 const multipart=data instanceof FormData,r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data&&!multipart?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?(multipart?data:new URLSearchParams(data)):undefined});
 for(const c of r.headers.getSetCookie()){const s=c.split(';')[0],i=s.indexOf('=');jar.set(s.slice(0,i),s.slice(i+1));}
 const bytes=Buffer.from(await r.arrayBuffer()),body=bytes.toString();
 assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body),path);
 return{status:r.status,body,bytes,location:r.headers.get('location')};
};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(name){const c=client(),p=await c('/masuk');assert.equal((await c('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return c;}
const root=await login('monitor1'),parent=await login('monitor2'),outsider=await login('monitor11');
const nonce=Date.now();
function png(color=1){return execFileSync('/www/server/php/84/bin/php',['-r',`$im=imagecreatetruecolor(32,32);imagefill($im,0,0,imagecolorallocate($im,${color*10},${color*8},${color*5}));imagepng($im);`]);}
const files=Array.from({length:12},(_,i)=>({name:`soal-${i+1}.png`,bytes:png(i+1)}));
const block=(n,names)=>`${n}. QA076-${nonce} soal ${n}: perhatikan gambar.\na. Satu\nb. Dua\nc. Tiga\nd. Empat\nGAMBAR: ${names}\n`;
const one=block(1,'soal-1.png | soal-2.png')+'KUNCI JAWABAN\n1.a\n';
let text='11 Soal Pilihan Ganda\n'+block(1,'soal-1.png | soal-2.png');
for(let i=2;i<=11;i++)text+=block(i,`soal-${i+1}.png`);
text+='KUNCI JAWABAN\n'+Array.from({length:11},(_,i)=>`${i+1}.a`).join(' ');
async function upload(text,attachments,extra={},target=route){
 const p=await root(target),f=new FormData();
 for(const [k,v]of Object.entries({study_csrf_token:field(p.body,'study_csrf_token'),mapel:'matematika',kelas:'sd_1',tingkat:'basic',rights_status:'owned',source_note:'QA076 original synthetic images',images_expected:String(attachments.length),...extra}))f.set(k,v);
 f.set('document',new Blob([text]),'soal.txt');
 for(const file of attachments)f.append('images[]',new Blob([file.bytes],{type:'image/png'}),file.name);
 return root(target,f);
}
const before=counts(),mediaBefore=mediaCount();
let r=await upload(one,[files[0]]);
assert.equal(r.status,422);assert.ok(r.body.includes('soal-2.png'));assert.equal(counts(),before);assert.equal(mediaCount(),mediaBefore,'Invalid mapping cleans only new uploads');
r=await upload(one,[...files.slice(0,2),files[2]]);assert.equal(r.status,422);assert.ok(r.body.includes('belum dihubungkan'));
assert.equal(counts(),before);assert.equal(mediaCount(),mediaBefore);
r=await upload(one,[files[0],files[0]]);assert.equal(r.status,400);assert.ok(r.body.includes('unik'));
r=await upload(one,files.slice(0,2),{images_expected:'3'});assert.equal(r.status,400);assert.ok(r.body.includes('berbeda'));
r=await upload(one,[{name:'soal-1.png',bytes:Buffer.from('not an image')},files[1]]);assert.equal(r.status,400);
assert.equal(mediaCount(),mediaBefore);
// Attachment order is deliberately reversed; the TXT order controls each gallery.
r=await upload(text,[...files].reverse());assert.equal(r.status,303);
const preview=new URL(r.location).pathname;
let view=await root(preview);
assert.equal((view.body.match(/<figure /g)||[]).length,12);
assert.ok(view.body.indexOf('soal-1.png')<view.body.indexOf('soal-2.png'));
const firstImage=await root(preview+'/gambar/0'),secondImage=await root(preview+'/gambar/0/1');
assert.equal(firstImage.status,200);assert.equal(secondImage.status,200);assert.notDeepEqual(firstImage.bytes,secondImage.bytes);
assert.equal((await root(preview+'/gambar/0/2')).status,404);
assert.equal((await parent(preview+'/gambar/0/1')).status,403);
assert.equal((await root(preview,{study_csrf_token:field(view.body,'study_csrf_token'),confirm:'1',publish:'1'})).status,303);
const token=preview.split('/').at(-1),ids=JSON.parse(sql(`SELECT JSON_EXTRACT(result_json,'$.question_ids') FROM st_bank_imports WHERE token='${token}';`)),id=ids[0];
assert.equal(ids.length,11);
assert.deepEqual((await root(`/kelola/bank-soal/${id}/gambar/1`)).bytes,secondImage.bytes);
const editValues={mapel:'matematika',kelas:'sd_1',tingkat:'basic',soal:`QA076-${nonce} soal 1: perhatikan gambar.`,a:'Satu',b:'Dua',c:'Tiga',d:'Empat',kunci:'A',bahasan:'',rights_status:'owned',source_note:'QA076 original synthetic images',status:'published',confirm:'1'};
view=await root('/kelola/bank-soal/'+id);
assert.equal((await root('/kelola/bank-soal/'+id,{...editValues,revision:field(view.body,'revision'),study_csrf_token:field(view.body,'study_csrf_token')})).status,303);
assert.deepEqual((await root(`/kelola/bank-soal/${id}/gambar/1`)).bytes,secondImage.bytes,'Ordinary edit retains both images');
// Same content imported into an exam reuses the shared bank question.
const examValues={title:'QA076 multi image '+nonce,grade_id:'3',duration_minutes:'15',max_daily:'3',credit_price:'0',status:'draft',question_shuffle:'1',option_shuffle:'1',revision:'0',action:'save'};
view=await root('/kelola/simulasi-ujian/0');
r=await root('/kelola/simulasi-ujian/0',{...examValues,study_csrf_token:field(view.body,'study_csrf_token')});assert.equal(r.status,303);
const examPath=new URL(r.location).pathname,eid=examPath.split('/').at(-1);
r=await upload(one,files.slice(0,2),{},route+'?ujian='+eid);assert.equal(r.status,303);
const examPreview=new URL(r.location).pathname;view=await root(examPreview);
assert.equal((await root(examPreview,{study_csrf_token:field(view.body,'study_csrf_token'),confirm:'1',publish:'1'})).status,303);
assert.equal(sql(`SELECT question_id FROM st_exam_questions WHERE exam_id=${Number(eid)};`),String(id));
view=await root(examPath);
assert.equal((await root(examPath,{...examValues,status:'published',revision:field(view.body,'revision'),study_csrf_token:field(view.body,'study_csrf_token')})).status,303);
view=await parent('/latihan/ujian/'+eid);
r=await parent('/latihan/ujian/'+eid,{start_token:field(view.body,'start_token'),study_csrf_token:field(view.body,'study_csrf_token')});assert.equal(r.status,303);
const attempt=new URL(r.location).pathname;
view=await parent(attempt);
assert.equal((view.body.match(/bank-question-image/g)||[]).length,2);
assert.deepEqual((await parent(attempt+`/gambar/${id}/1`)).bytes,secondImage.bytes);
assert.equal((await outsider(attempt+`/gambar/${id}/1`)).status,404);
assert.equal((await parent(attempt+`/gambar/${id}/2`)).status,404);
// Snapshot must retain both images even if the bank later removes the gallery.
view=await root('/kelola/bank-soal/'+id);
assert.equal((await root('/kelola/bank-soal/'+id,{...editValues,remove_image:'1',revision:field(view.body,'revision'),study_csrf_token:field(view.body,'study_csrf_token')})).status,303);
assert.equal((await root(`/kelola/bank-soal/${id}/gambar/1`)).status,404);
assert.deepEqual((await parent(attempt+`/gambar/${id}/1`)).bytes,secondImage.bytes);
view=await parent(attempt);
const oid=sql(`SELECT id FROM st_question_options WHERE question_id=${Number(id)} AND is_correct=1;`);
assert.equal((await parent(attempt,{action:'finish',revision:field(view.body,'revision'),[`answers[${id}]`]:oid,study_csrf_token:field(view.body,'study_csrf_token')})).status,303);
assert.ok((await parent(attempt)).body.includes('Jawaban benar'));
assert.equal(sql(`SELECT score FROM st_practice_attempts WHERE id=${Number(attempt.split('/').at(-1))};`),'100.00');
// 500 small files exercise the actual PHP multipart ceiling, not a mocked count.
let bulk='500 Soal Pilihan Ganda\n',bulkKeys='',bulkFiles=[];
for(let i=1;i<=500;i++){bulk+=block(i,`bulk${i}.png`);bulkKeys+=`${i}.a `;bulkFiles.push({name:`bulk${i}.png`,bytes:files[0].bytes});}
r=await upload(bulk+'KUNCI JAWABAN\n'+bulkKeys,bulkFiles);assert.equal(r.status,303,'500 images accepted');
r=await upload(one,files.slice(0,2),{images_expected:'501'});assert.equal(r.status,400,'Count above supported cap blocked clearly');
// Valid PNGs with noise: 8 files exceed old 4 MiB and old server 8 MiB limits.
const large=execFileSync('/www/server/php/84/bin/php',['-r','$im=imagecreatetruecolor(1024,512);mt_srand(76);for($y=0;$y<512;$y++){for($x=0;$x<1024;$x++){imagesetpixel($im,$x,$y,mt_rand(0,0xffffff));}}imagepng($im);'],{maxBuffer:4*1024*1024});
assert.ok(large.length>1024*1024&&large.length<3*1024*1024);
const largeFiles=Array.from({length:8},(_,i)=>({name:`large${i}.png`,bytes:large}));
r=await upload(block(1,largeFiles.map(f=>f.name).join(' | '))+'KUNCI JAWABAN\n1.a',largeFiles);assert.equal(r.status,303,'Over 8 MiB multipart accepted');
const oversized=Array.from({length:Math.floor(32*1024*1024/large.length)+1},(_,i)=>({name:`oversize${i}.png`,bytes:large}));
const preReject=counts(),preMedia=mediaCount();
r=await upload(one,oversized);assert.equal(r.status,400);assert.ok(r.body.includes('32 MiB'));assert.equal(counts(),preReject);assert.equal(mediaCount(),preMedia);
const browser=await puppeteer.launch({executablePath:'/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 async function bl(name){await page.deleteCookie(...await page.cookies());await page.goto(base+'/masuk');await page.type('[name=identity]',name);await page.type('[name=password]','Monitor-QA-Only-2026!');await Promise.all([page.waitForNavigation(),page.click('form[action$="/masuk"] button[type=submit]')]);}
 await bl('monitor1');
 for(const width of [1280,360]){
  await page.setViewport({width,height:900});await page.goto(base+route);
  const detail=await page.$('fieldset details summary');await detail.focus();await page.keyboard.press('Enter');
  assert.ok(await detail.evaluate(el=>el.parentElement.open),'Image guide keyboard');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Guide viewport');
  await page.evaluate(()=>document.querySelector('fieldset details').scrollIntoView({behavior:'instant'}));
  await page.screenshot({path:`/tmp/images076-guide-${width}.png`});
  await page.goto(base+preview);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Preview viewport');
  assert.equal(await page.$$eval('figure img',imgs=>imgs.length),12);
  await page.screenshot({path:`/tmp/images076-gallery-${width}.png`});
 }
 await page.goto(base+route);
 await page.evaluate(()=>{const input=document.querySelector('#images'),dt=new DataTransfer();for(let i=0;i<12;i++)dt.items.add(new File(['x'],`image${i}.png`,{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change'));});
 assert.ok((await page.$eval('#images-status',el=>el.textContent)).includes('12 gambar'));
 assert.equal(await page.$eval('[name=images_expected]',el=>el.value),'12');
 await page.evaluate(()=>{const input=document.querySelector('#images'),dt=new DataTransfer();dt.items.add(new File([new Uint8Array(3145729)],'too-big.png',{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change'));});
 assert.equal(await page.$eval('#images',el=>el.validity.valid),false);
 await bl('monitor2');
 for(const width of [1280,360]){await page.setViewport({width,height:900});await page.goto(base+attempt);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));assert.equal(await page.$$eval('.bank-question-image',imgs=>imgs.length),2);}
 assert.deepEqual(errors,[]);
}finally{await browser.close();}
console.log('PASS076: 12/500 real images, >8MiB body, 32MiB rejection, names/order/multi-image mapping, invalid cleanup, private access, same-ID exam, historical gallery after removal, grading, desktop/mobile/keyboard and upload feedback.');
