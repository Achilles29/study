import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
const password='Monitor-QA-Only-2026!';
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch('http://127.0.0.1:8097'+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return{status:r.status,body};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(id,pw=password){const r=client(),p=await r('/masuk');assert.equal((await r('/masuk',{identity:'monitor'+id,password:pw,study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return r;}
async function post(r,path,data,status=303){const p=await r(path);assert.equal(p.status,200);const out=await r(path,{...data,study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(out.status,status,path);return out;}
const root=await login(1),parent=await login(2),child=await login(3),other=await login(4),adult=await login(19),path='/keluarga/anak/3/pulihkan-sandi';
assert.equal((await adult(path)).status,403);assert.equal((await child(path)).status,403);assert.equal((await parent('/keluarga/anak/4/pulihkan-sandi')).status,404);assert.equal((await parent('/kelola/pengguna/3/akses')).status,403);
const version=sql('SELECT auth_version FROM st_users WHERE id=3'),newPassword='Child-recovered-QA-2026!',data={version,new_password:newPassword,password_confirm:newPassword,current_password:password,confirm:'1'};
assert.equal((await parent(path,data)).status,403);
await post(parent,path,{...data,current_password:'Wrong-password-only'},403);await post(parent,path,{...data,new_password:'short',password_confirm:'short'},400);await post(parent,path,{...data,password_confirm:'mismatch'},400);
const before=sql('SELECT password_hash FROM st_users WHERE id=3');
sql("CREATE TRIGGER qa_controls_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA audit failure'");try{await post(parent,path,data,503);assert.equal(sql('SELECT password_hash FROM st_users WHERE id=3'),before);}finally{sql('DROP TRIGGER qa_controls_fail');}
const verified=sql('SELECT email_verified_at FROM st_users WHERE id=2');try{sql('UPDATE st_users SET email_verified_at=NULL WHERE id=2');await post(parent,path,data,403);}finally{sql("UPDATE st_users SET email_verified_at='"+verified+"' WHERE id=2");}
await post(parent,path,data);await post(parent,path,data,409);assert.equal((await child('/belajar')).status,303,'Old child session revoked');const recovered=await login(3,newPassword);assert.equal((await recovered('/belajar')).status,200);
const statusPath='/kelola/pengguna/20/akses',victim=await login(20),ver=()=>sql('SELECT auth_version FROM st_users WHERE id=20');
const change={action:'status',version:ver(),status:'suspended',reason:'Pengujian penangguhan akun terisolasi',current_password:password,confirm:'1'};await post(root,statusPath,change);await post(root,statusPath,change,409);assert.equal(sql('SELECT status FROM st_users WHERE id=20'),'suspended');await post(root,statusPath,{...change,version:ver(),status:'active'});assert.equal((await victim('/belajar')).status,303,'Unvisited old session stays invalid after restore');await login(20);
await post(root,'/kelola/pengguna/1/akses',{...change,version:'0'},403);
const blockPath='/kelola/pengguna/3/akses',block={action:'course',course_id:'1',version:'0',blocked:'1',reason:'Pengujian pembatasan kursus anak',current_password:password,confirm:'1'};await post(root,blockPath,block);await post(root,blockPath,block,409);
const slug=sql('SELECT slug FROM st_courses WHERE id=1');assert.equal((await recovered('/kursus/'+slug)).status,200);assert.equal(sql('SELECT blocked FROM st_course_access_blocks WHERE user_id=3 AND course_id=1'),'1');
await post(root,blockPath,{...block,version:'1',blocked:'0'});
const childStatus='/kelola/pengguna/3/akses',cv=()=>sql('SELECT auth_version FROM st_users WHERE id=3');await post(root,childStatus,{...change,version:cv()});
const add=await parent('/keluarga/anak/tambah'),name='qarestore_'+Date.now();assert.equal((await parent('/keluarga/anak/tambah',{username:name,display_name:'QA Kuota Pemulihan',learning_stage:'primary',password:'Child-QA-Only-2026!',password_confirm:'Child-QA-Only-2026!',study_csrf_token:field(add.body,'study_csrf_token')})).status,303);
await post(root,childStatus,{...change,version:cv(),status:'active'},409);const added=sql("SELECT id FROM st_users WHERE username='"+name+"'");await post(root,'/kelola/pengguna/'+added+'/akses',{...change,version:'0'});await post(root,childStatus,{...change,version:cv(),status:'active'});
assert.equal(sql("SELECT COUNT(*) FROM st_audit_events WHERE event_type='family.child_password_reset' AND entity_id=3"),'1');assert.equal(sql("SELECT COUNT(*) FROM st_audit_events WHERE details_json LIKE '%Child-recovered-QA%'") ,'0');
console.log('PASS child recovery, role/family isolation, CSRF, verified guardian, password bounds, audit rollback, replay, old-session revocation after suspension+restore, protected Superadmin, course block revisions and child restoration capacity.');
