import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const base='http://127.0.0.1:8097';
const database=process.env.STUDY_MONITOR_TEST_DB;
assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');jar.set(pair.slice(0,i),pair.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body};};}
const token=b=>{const m=b.match(/name="study_csrf_token" value="([^"]+)"/);assert.ok(m);return m[1];};
const revision=b=>b.match(/name="revision" value="(\d+)"/)[1];
async function login(name){const request=client();const f=await request('/masuk');assert.equal((await request('/masuk',{identity:name,password:'Monitor-QA-Only-2026!',study_csrf_token:token(f.body)})).status,303);return request;}
const admin=await login('monitor1'), editor=await login('monitor6'), ordinary=await login('monitor5');
for(const request of [editor,ordinary]){assert.equal((await request('/kelola/pengaturan')).status,403);assert.equal((await request('/settings/index/umum')).status,403);}
let f=await admin('/kelola/pengaturan');assert.equal(f.status,200);
const original=JSON.parse(sql("SELECT values_json FROM st_site_settings WHERE section='umum';"));
const auditBefore=Number(sql("SELECT COUNT(*) FROM st_audit_events WHERE event_type='settings.updated' AND entity_id=1;"));
const fields={brand_name:'Project Pintar <uji>',tagline:'Belajar dengan keluarga.',seo_description:'Latihan soal anak dan kartu belajar.',support_email:'support@example.test',brand_icon:'bi-book'};
const submit=async(values,rev)=>{const page=await admin('/kelola/pengaturan');return admin('/kelola/pengaturan',{...values,revision:rev||revision(page.body),study_csrf_token:token(page.body)});};
assert.equal((await admin('/kelola/pengaturan',fields)).status,403,'CSRF');
assert.equal((await submit({...fields,support_email:'javascript:alert(1)'})).status,400);
assert.equal((await submit({...fields,brand_icon:'evil-class'})).status,400);
const stale=revision(f.body);assert.equal((await submit(fields)).status,303);
assert.equal((await submit({...fields,brand_name:'Conflict overwrite'},stale)).status,409);
const home=await client()('/');assert.ok(home.body.includes('Project Pintar &lt;uji&gt;'));assert.ok(home.body.includes('Latihan soal anak dan kartu belajar.'));
assert.equal(Number(sql("SELECT COUNT(*) FROM st_audit_events WHERE event_type='settings.updated' AND entity_id=1;")),auditBefore+1);
const current=sql("SELECT CONCAT(revision,':',values_json) FROM st_site_settings WHERE section='umum';");
try{
 sql("CREATE TRIGGER qa_settings_audit_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA synthetic audit failure';");
 assert.equal((await submit({...fields,brand_name:'Must roll back'})).status,503);
 assert.equal(sql("SELECT CONCAT(revision,':',values_json) FROM st_site_settings WHERE section='umum';"),current,'Failed audit rolls back settings');
}finally{sql('DROP TRIGGER IF EXISTS qa_settings_audit_failure;');}
// Restore fixture identity through the real form, not through production configuration.
assert.equal((await submit({brand_name:'Project Pintar',tagline:'Belajar sedikit demi sedikit, tumbuh setiap hari.',seo_description:'Project Pintar, aplikasi belajar anak TK, SD, dan SMP dengan latihan soal, flashcard, permainan edukasi, dan pemantauan progres oleh orang tua.',support_email:'',brand_icon:'bi-stars',...original})).status,303);
const setMode=async value=>{const page=await admin('/kelola/pengaturan/pembelajaran');return admin('/kelola/pengaturan/pembelajaran',{solo_review:value,revision:revision(page.body),study_csrf_token:token(page.body)});};
const originalMode=JSON.parse(sql("SELECT values_json FROM st_site_settings WHERE section='pembelajaran';")).solo_review||'0';
const fixtureIds=[];
for(const author of [1,6,1,1]){
 const slug='qa-solo-'+Date.now()+'-'+fixtureIds.length;
 const id=Number(sql(`INSERT INTO st_courses(title,slug,author_user_id,status,rights_status,source_note,created_at,updated_at) VALUES('Demo QA saja','${slug}',${author},'review','owned','Teks orisinal QA',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();`));fixtureIds.push(id);
 const section=Number(sql(`INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(${id},'Bagian QA',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();`));
 sql(`INSERT INTO st_lessons(section_id,title,slug,body_text,rights_status,source_note,sort_order,created_at,updated_at) VALUES(${section},'Materi QA','${slug}-materi','Materi singkat orisinal QA.','owned','Ditulis untuk QA',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());`);
}
const [own,editorOwn,invalid,failed]=fixtureIds;
const review=async(request,id,checks=true)=>{const page=await request('/kelola');return request(`/kelola/kursus/${id}/tinjau`,{decision:'approved',notes:'Isi, hak dan usia sudah diperiksa.',...(checks?{quality_content:'1',quality_rights:'1',quality_age:'1'}:{}),study_csrf_token:token(page.body)});};
const status=id=>sql(`SELECT status FROM st_courses WHERE id=${id};`);
assert.equal((await setMode('0')).status,303);await review(admin,own);assert.equal(status(own),'review','Disabled solo mode denied');
assert.equal((await setMode('1')).status,303);
let course=await admin(`/kelola/kursus/${own}`);assert.equal(course.status,200);assert.ok(course.body.includes('quality_content'));
await review(admin,own,false);assert.equal(status(own),'review','Missing checklist denied');
await review(editor,editorOwn);assert.equal(status(editorOwn),'review','Editor self-review denied');
sql(`UPDATE st_courses SET rights_status='pending_review' WHERE id=${invalid};`);await review(admin,invalid);assert.equal(status(invalid),'review','Quality guard remains');
await review(admin,own);assert.equal(status(own),'published');await review(admin,own);assert.equal(sql(`SELECT COUNT(*) FROM st_content_reviews WHERE course_id=${own};`),'1','Replay does not duplicate review');
assert.equal(sql(`SELECT JSON_EXTRACT(details_json,'$.self_review') FROM st_audit_events WHERE event_type='content.course_reviewed' AND entity_id=${own};`),'true');
try{sql("CREATE TRIGGER qa_settings_audit_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA synthetic audit failure';");await review(admin,failed);assert.equal(status(failed),'review');assert.equal(sql(`SELECT COUNT(*) FROM st_content_reviews WHERE course_id=${failed};`),'0');}finally{sql('DROP TRIGGER IF EXISTS qa_settings_audit_failure;');}
assert.equal((await setMode(originalMode)).status,303);
for(const request of [admin,editor,ordinary]){const page=await request('/beranda');assert.equal((await request('/keluar',{study_csrf_token:token(page.body)})).status,303);}
console.log('PASS settings: save/read/public identity, validation/escaping, roles, CSRF, stale-edit conflict, audit and rollback on failed audit.');
console.log('PASS solo review: off-mode, checklist and editor denial, quality guards, publish, audit/replay and rollback on failed audit.');
