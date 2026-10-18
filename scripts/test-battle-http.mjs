import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const database=process.env.STUDY_MONITOR_TEST_DB;assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(port=8097){const jar=new Map();return async(path,data)=>{const r=await fetch('http://127.0.0.1:'+port+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),path);return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(id,port){const req=client(port),p=await req('/masuk');assert.equal((await req('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return req;}
const route=id=>'/belajar/battle/ruang/'+id;
async function post(req,path,data,status=303,from=path){const p=await req(from);assert.equal(p.status,200,from);const r=await req(path,{...data,study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(r.status,status,`${path} ${data.action||''} expected ${status} got ${r.status}`);return r;}
const wib=offset=>new Date(Date.now()+7*3600000+offset).toISOString().slice(0,16);
async function create(req,title='QA Battle Privat'){const p=await req('/belajar/battle'),data={title,starts_at:wib(-60000),ends_at:wib(3600000),create_token:field(p.body,'create_token')};const r=await post(req,'/belajar/battle/buat',data,303,'/belajar/battle');assert.equal((await post(req,'/belajar/battle/buat',data,303,'/belajar/battle')).location,r.location,'Create replay');return Number(r.location.match(/ruang\/(\d+)/)[1]);}
async function edit(req,id,action,data={},status=303){return post(req,route(id),{action,revision:sql(`SELECT revision FROM st_battle_rooms WHERE id=${id}`),...data},status);}
async function join(req,id,extra={},status=303){return post(req,'/belajar/battle/gabung',{code:sql(`SELECT invite_code FROM st_battle_rooms WHERE id=${id}`),...extra},status,'/belajar/battle');}
const question={prompt:'QA <script>alert(1)</script> dua ditambah dua?',option_A:'Empat',option_B:'Tiga',option_C:'Dua',option_D:'Satu',answer:'A',explanation:'PRIVATE_CORRECT_EXPLANATION_82',source:'Karya sintetis QA',rights:'Materi uji milik proyek'};
const host=await login(16),user=await login(17),other=await login(18),parent=await login(2),child=await login(3),admin=await login(1);
assert.equal(sql('SELECT COUNT(*) FROM st_battle_rooms'),'0','Fresh QA only');const bank=sql('SELECT COUNT(*) FROM st_questions');
const id=await create(host);const draft2=await create(host),draft3=await create(host);
await edit(host,id,'details',{title:'QA Battle Diperbaiki',starts_at:wib(-60000),ends_at:wib(3600000)});assert.equal(sql(`SELECT title FROM st_battle_rooms WHERE id=${id}`),'QA Battle Diperbaiki');
await edit(host,id,'details',{title:'Invalid date',starts_at:'2026-02-30T12:00',ends_at:wib(3600000)},400);
let p=await host('/belajar/battle');await post(host,'/belajar/battle/buat',{title:'Quota rejected',starts_at:wib(-60000),ends_at:wib(3600000),create_token:field(p.body,'create_token')},403,'/belajar/battle');
await edit(host,draft2,'cancel',{reason:'Synthetic cancellation'});await edit(host,draft3,'cancel',{reason:'Synthetic cancellation'});
await edit(host,id,'question',{...question,option_B:'Empat'},400);await edit(host,id,'question',question);await edit(host,id,'question',{...question,prompt:'Soal kedua'});
await edit(host,id,'remove',{index:'1'});await edit(host,id,'question',{...question,prompt:'Soal kedua'});
await edit(host,id,'publish',{confirm:'1'},403);
sql('UPDATE st_users SET email_verified_at=UTC_TIMESTAMP() WHERE id IN(16,2)');await edit(host,id,'publish',{},403);await edit(host,id,'publish',{confirm:'1'});
const snapshot=sql(`SELECT SHA2(snapshot_json,256) FROM st_battle_rooms WHERE id=${id}`);
await edit(host,id,'question',question,403);await join(host,id,{},403);await join(child,id,{},403);
await edit(host,id,'details',{title:'Frozen title',starts_at:wib(-60000),ends_at:wib(3600000)},403);
await join(user,id);await join(user,id);assert.equal(sql(`SELECT COUNT(*) FROM st_battle_entries WHERE room_id=${id}`),'1');
assert.equal((await other(route(id))).status,404);assert.equal((await admin(route(id))).status,404);assert.equal((await other('/kelola/battle')).status,403);
await join(parent,id,{child_id:'3'},403);await join(parent,id,{child_id:'4',consent:'1'},403);await join(parent,id,{child_id:'3',consent:'1'});
p=await parent(route(id));assert.ok(!p.body.includes(question.explanation)&&!p.body.includes('Soal kedua'));await post(parent,route(id),{action:'answer',entry_revision:'1',value:'A'},404);
p=await user(route(id));assert.ok(p.body.includes('&lt;script&gt;')&&!p.body.includes('<script>alert(1)</script>')&&!p.body.includes(question.explanation));
assert.equal((await user(route(id),{action:'answer',entry_revision:'1',value:'A'})).status,403);
await post(user,route(id),{action:'finish',entry_revision:'1'},400);
sql("CREATE TRIGGER qa_battle_entry_fail BEFORE UPDATE ON st_battle_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
try{await post(user,route(id),{action:'answer',entry_revision:'1',value:'A'},503);assert.equal(sql(`SELECT CONCAT(revision,':',correct_count) FROM st_battle_entries WHERE room_id=${id} AND learner_user_id=17`),'1:0');}finally{sql('DROP TRIGGER qa_battle_entry_fail');}
await post(user,route(id),{action:'answer',entry_revision:'1',value:'A',score:'100'});await post(user,route(id),{action:'answer',entry_revision:'1',value:'A'},409);
await post(user,route(id),{action:'answer',entry_revision:'2',value:'B',score:'100'});assert.equal(sql(`SELECT CONCAT(status,':',correct_count,':',question_count) FROM st_battle_entries WHERE room_id=${id} AND learner_user_id=17`),'completed:1:2');p=await user(route(id));assert.ok(!p.body.includes(question.explanation));
const entryId=sql(`SELECT id FROM st_battle_entries WHERE room_id=${id} AND learner_user_id=17`);p=await admin('/kelola/pembelajaran/aktivitas/battle/'+entryId);assert.equal(p.status,200);assert.ok(!p.body.includes('50%')&&!p.body.includes(question.explanation),'No early scores or questions in monitoring');
sql("UPDATE st_family_links SET status='revoked' WHERE parent_user_id=2 AND child_user_id=3");assert.equal((await child(route(id))).status,404);sql("UPDATE st_family_links SET status='active' WHERE parent_user_id=2 AND child_user_id=3");
sql('UPDATE st_users SET email_verified_at=NULL WHERE id=2');assert.equal((await child(route(id))).status,404);sql('UPDATE st_users SET email_verified_at=UTC_TIMESTAMP() WHERE id=2');
const childEntry=sql(`SELECT id FROM st_battle_entries WHERE room_id=${id} AND learner_user_id=3`);await post(parent,route(id),{action:'revoke',entry_id:childEntry});await post(child,route(id),{action:'answer',entry_revision:'2',value:'A'},403);await join(parent,id,{child_id:'3',consent:'1'},403);
assert.equal(sql(`SELECT SHA2(snapshot_json,256) FROM st_battle_rooms WHERE id=${id}`),snapshot);
// Two independent PHP processes race for the last slot. Actor locks are different.
const race=await create(host,'QA Capacity Race');await edit(host,race,'question',question);await edit(host,race,'publish',{confirm:'1'});sql(`UPDATE st_battle_rooms SET capacity=1 WHERE id=${race}`);
const racer1=await login(21,8097),racer2=await login(22,8098),code=sql(`SELECT invite_code FROM st_battle_rooms WHERE id=${race}`);
const pages=await Promise.all([racer1('/belajar/battle'),racer2('/belajar/battle')]);const raced=await Promise.all([racer1,racer2].map((r,i)=>r('/belajar/battle/gabung',{code,study_csrf_token:field(pages[i].body,'study_csrf_token')})));assert.deepEqual(raced.map(r=>r.status).sort(),[303,403]);assert.equal(sql(`SELECT COUNT(*) FROM st_battle_entries WHERE room_id=${race}`),'1');
const winner=raced[0].status===303?racer1:racer2;sql(`UPDATE st_battle_rooms SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=${race}`);await post(winner,route(race),{action:'answer',entry_revision:'1',value:'A'});assert.equal(sql(`SELECT CONCAT(status,':',correct_count) FROM st_battle_entries WHERE room_id=${race}`),'completed:0');assert.ok((await winner(route(race))).body.includes(question.explanation));
sql(`UPDATE st_battle_rooms SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=${id}`);p=await user(route(id));assert.ok(p.body.includes('1/2 benar')&&p.body.includes(question.explanation));
p=await admin('/kelola/pembelajaran/aktivitas/battle/'+entryId);assert.equal(p.status,200);assert.ok(p.body.includes('50')&&!p.body.includes(question.explanation));assert.equal((await parent('/keluarga/anak/3/rapor?modul=battle')).status,200);assert.equal((await parent('/keluarga/anak/4/rapor?modul=battle')).status,404);
await post(user,route(id),{action:'report',reason:'Synthetic moderation report'});await post(user,route(id),{action:'report',reason:'Duplicate report'});assert.equal(sql(`SELECT COUNT(*) FROM st_battle_reports WHERE room_id=${id}`),'1');const report=sql(`SELECT id FROM st_battle_reports WHERE room_id=${id}`);
sql("CREATE TRIGGER qa_battle_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");try{await post(admin,'/kelola/battle',{report_id:report,cancel:'1',reason:'Synthetic moderator decision'},503);assert.equal(sql(`SELECT status FROM st_battle_rooms WHERE id=${id}`),'published');assert.equal(sql(`SELECT status FROM st_battle_reports WHERE id=${report}`),'open');}finally{sql('DROP TRIGGER qa_battle_audit_fail');}
await post(admin,'/kelola/battle',{report_id:report,cancel:'1',reason:'Synthetic moderator decision'});assert.ok(!(await user(route(id))).body.includes(question.explanation));await post(user,route(id),{action:'answer',entry_revision:'3',value:'A'},403);
const childDraft=await create(child,'QA Draf anak');await edit(child,childDraft,'question',question);await edit(child,childDraft,'publish',{confirm:'1'},403);assert.equal((await other(route(childDraft))).status,404);await edit(parent,childDraft,'publish',{confirm:'1'});assert.equal(sql(`SELECT publisher_user_id FROM st_battle_rooms WHERE id=${childDraft}`),'2');
assert.equal(sql('SELECT COUNT(*) FROM st_questions'),bank);
console.log('PASS Battle: drafts/questions/preview/publish, email gate, child parent approval/consent/revocation, CSRF/IDOR/stale/replay, server grade/deadline/hidden keys, atomic answer and audit rollback, two-process last-slot race, reports/moderation and immutable snapshot.');
