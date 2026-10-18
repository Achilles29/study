import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const database=process.env.STUDY_MONITOR_TEST_DB,base='http://127.0.0.1:8097';
assert.match(database||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','--batch','--raw','--skip-column-names',database],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body));return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]+)"`));assert.ok(m,n);return m[1];};
async function login(client,user){const p=await client('/masuk');assert.equal((await client('/masuk',{identity:'monitor'+user,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);}
const user=client(),other=client();await login(user,9);await login(other,10);
assert.equal(sql('SELECT COUNT(*) FROM st_arena_runs WHERE learner_user_id=9'),'0','Use fresh synthetic user');
const packs=sql("SELECT slug FROM st_arena_packs WHERE game_type='flashcard' ORDER BY id").split('\n');assert.equal(packs.length,14);
const bankBefore=sql('SELECT COUNT(*) FROM st_questions');let cards=0;
async function start(slug){const p=await user('/belajar/arena/pilih/'+slug);assert.equal(p.status,200);const data={start_token:field(p.body,'start_token'),mode:'calm',study_csrf_token:field(p.body,'study_csrf_token')};const r=await user('/belajar/arena/pilih/'+slug,data);assert.equal(r.status,303);const fresh=await user('/belajar/arena/pilih/'+slug);assert.equal((await user('/belajar/arena/pilih/'+slug,{...data,study_csrf_token:field(fresh.body,'study_csrf_token')})).location,r.location,'Start replay');return Number(r.location.match(/main\/(\d+)/)[1]);}
async function act(id,action,value,expected=303){const p=await user('/belajar/arena/main/'+id);const data={revision:field(p.body,'revision'),action,value,study_csrf_token:field(p.body,'study_csrf_token')};const r=await user('/belajar/arena/main/'+id,data);assert.equal(r.status,expected,`${action} ${r.status}`);return data;}
for(const slug of packs){
 const id=await start(slug),snapshot=JSON.parse(sql(`SELECT snapshot_json FROM st_arena_runs WHERE id=${id}`));
 assert.equal((await other('/belajar/arena/main/'+id)).status,404);
 for(let i=0;i<snapshot.items.length;i++){
  let p=await user('/belajar/arena/main/'+id);assert.equal(p.status,200);assert.ok(!p.body.includes('data-card-back'));assert.ok(!p.body.includes(snapshot.items[i].key));
  await act(id,'rate','known',400);await act(id,'reveal','');
  p=await user('/belajar/arena/main/'+id);assert.ok(p.body.includes('data-card-back'));
  if(cards===0){
   const before=sql(`SELECT state_json,revision FROM st_arena_runs WHERE id=${id}`);
   sql("CREATE TRIGGER qa_card_fail BEFORE INSERT ON st_arena_card_progress FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'");
   try{await act(id,'rate','known',503);assert.equal(sql(`SELECT state_json,revision FROM st_arena_runs WHERE id=${id}`),before,'Atomic card/run rollback');}finally{sql('DROP TRIGGER qa_card_fail');}
   const noCsrf=await user('/belajar/arena/main/'+id,{revision:field(p.body,'revision'),action:'rate',value:'known'});assert.equal(noCsrf.status,403);
  }
  const data=await act(id,'rate','known');if(i<snapshot.items.length-1){const fresh=await user('/belajar/arena/main/'+id);assert.equal((await user('/belajar/arena/main/'+id,{...data,study_csrf_token:field(fresh.body,'study_csrf_token')})).status,409,'Stale revision cannot duplicate review');}cards++;
 }
 const p=await user('/belajar/arena/main/'+id);assert.ok(p.body.includes('Tinjauan kartu'));assert.ok(!p.body.includes('jawaban benar'));assert.equal(sql(`SELECT status FROM st_arena_runs WHERE id=${id}`),'completed');
}
assert.equal(cards,151);assert.equal(sql('SELECT COUNT(*) FROM st_arena_card_progress WHERE learner_user_id=9'),'151');
const id=await start(packs[0]);await act(id,'reveal','');await act(id,'rate','repeat');await act(id,'stop','');
assert.equal(sql("SELECT COUNT(*) FROM st_arena_card_progress WHERE learner_user_id=9 AND status='repeat' AND review_count=2"),'1','Repeat updates current card, not duplicated');
assert.equal(sql('SELECT COUNT(*) FROM st_arena_card_progress WHERE learner_user_id=9'),'151');
assert.equal(sql('SELECT COUNT(*) FROM st_questions'),bankBefore);
console.log('PASS 14 flashcard decks / 151 cards: reveal/rate/end-to-end, self-assessment, durable per-card progress, repeat upsert, CSRF, ownership, stale tabs, idempotent start and atomic save rollback. Exercise bank unchanged.');
