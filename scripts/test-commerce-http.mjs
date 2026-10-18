import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';
const db=process.env.STUDY_MONITOR_TEST_DB;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
function client(port=8097){const jar=new Map();return async(path,data)=>{const r=await fetch(`http://127.0.0.1:${port}`+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),body.slice(0,200));return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(id,port){const r=client(port),p=await r('/masuk');assert.equal((await r('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return r;}
async function post(r,path,data,status=303,from='/akun/kredit'){const p=await r(from);assert.equal(p.status,200,from);const out=await r(path,{...data,study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(out.status,status,`${path}: ${out.status} vs ${status}`);return out;}
sql("UPDATE st_users SET email_verified_at=UTC_TIMESTAMP() WHERE id IN(1,2,19,20); INSERT INTO st_wallets(owner_user_id,environment,balance_credits,created_at,updated_at) SELECT id,'sandbox',0,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users u WHERE account_kind IN('adult','parent') AND NOT EXISTS(SELECT 1 FROM st_wallets w WHERE w.owner_user_id=u.id AND w.environment='sandbox')");
const admin=await login(1),parent=await login(2),adult=await login(19),other=await login(20),child=await login(3),anon=client();
assert.equal((await anon('/akun/kredit')).status,307);assert.equal((await child('/akun/kredit')).status,403);assert.equal((await adult('/kelola/paket')).status,403);assert.equal((await adult('/commerce/package/0')).status,403);
const pkg={title:'Paket QA Voucher',summary:'Paket sintetis untuk bukti transaksi',status:'published',is_official:'1',revision:'0','courses[]':'1',confirm:'1'};
const out=await post(admin,'/kelola/paket/0',pkg,303,'/kelola/paket/0'),pid=Number(out.location.split('/').at(-1));assert.ok(pid);
const offer={title:'Voucher QA Keluarga',kind:'voucher',package_id:String(pid),price:'40',credit_amount:'0',access_days:'30',voucher_days:'7',status:'active',revision:'0',confirm:'1',reason:'Ketentuan sintetis untuk QA saja'};
await post(admin,'/kelola/produk/0',{...offer,confirm:'0'},400,'/kelola/produk/0');
const made=await post(admin,'/kelola/produk/0',offer,303,'/kelola/produk/0'),oid=Number(made.location.split('/').at(-1));
await post(admin,'/kelola/produk/'+oid,{...offer,revision:'0'},409,'/kelola/produk/'+oid);
const buy=(key=randomBytes(32).toString('hex'))=>({revision:'1',package_revision:sql('SELECT revision FROM st_packages WHERE id='+pid),learner_id:'0',request_key:key,confirm:'1'});
assert.equal((await parent('/akun/kredit/beli/'+oid,buy())).status,403,'CSRF');await post(parent,'/akun/kredit/beli/'+oid,buy(),409);
const credit=id=>{sql(`UPDATE st_wallets SET balance_credits=balance_credits+120 WHERE owner_user_id=${id}; INSERT INTO st_wallet_entries(wallet_id,actor_user_id,entry_type,delta_credits,idempotency_key,note,created_at) SELECT id,${id},'adjustment',120,'qa:${randomBytes(12).toString('hex')}','QA opening credit',UTC_TIMESTAMP() FROM st_wallets WHERE owner_user_id=${id}`);};credit(2);credit(19);
const key=buy();await post(parent,'/akun/kredit/beli/'+oid,key);await post(parent,'/akun/kredit/beli/'+oid,key);assert.equal(sql('SELECT balance_credits FROM st_wallets WHERE owner_user_id=2'),'80');
const purchase=Number(sql(`SELECT id FROM st_commerce_purchases WHERE owner_user_id=2 AND offer_id=${oid} ORDER BY id DESC LIMIT 1`));
await post(parent,'/akun/kredit/beli/'+oid,{...key,learner_id:'3'},409);
await post(other,`/akun/voucher/${purchase}/gunakan`,{learner_id:'20',confirm:'1'},404);await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:'4',confirm:'1'},403);
// The old voucher keeps its original course set and duration after the product is changed.
await post(admin,'/kelola/produk/'+oid,{...offer,price:'99',access_days:'90',revision:'1'},303,'/kelola/produk/'+oid);
sql("CREATE TRIGGER qa_commerce_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA commerce failure'");try{await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:'3',confirm:'1'},503);assert.equal(sql(`SELECT status FROM st_commerce_purchases WHERE id=${purchase}`),'available');assert.equal(sql(`SELECT COUNT(*) FROM st_commerce_grants WHERE purchase_id=${purchase}`),'0');}finally{sql('DROP TRIGGER qa_commerce_audit_fail');}
await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:'3',confirm:'1'});await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:'3',confirm:'1'});await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:'2',confirm:'1'},409);
assert.equal(sql(`SELECT TIMESTAMPDIFF(DAY,starts_at,ends_at) FROM st_commerce_grants WHERE purchase_id=${purchase}`),'30');assert.equal(sql(`SELECT COUNT(*) FROM st_wallet_entries WHERE reference_type='commerce_purchase' AND reference_id=${purchase}`),'1');
await post(adult,'/akun/kredit/beli/'+oid,buy(),409,'/akun/kredit');
await post(admin,'/kelola/produk/'+oid,{...offer,revision:'2'},303,'/kelola/produk/'+oid);
// Two independent PHP workers/sessions spend the last balance concurrently.
const second=await login(19,8098),a=await adult('/akun/kredit'),b=await second('/akun/kredit');
const raceKey=buy();raceKey.revision='3';const results=await Promise.all([adult('/akun/kredit/beli/'+oid,{...raceKey,study_csrf_token:field(a.body,'study_csrf_token')}),second('/akun/kredit/beli/'+oid,{...raceKey,study_csrf_token:field(b.body,'study_csrf_token')})]);assert.deepEqual(results.map(r=>r.status),[303,303]);assert.equal(sql('SELECT balance_credits FROM st_wallets WHERE owner_user_id=19'),'80');
const p2=Number(sql(`SELECT id FROM st_commerce_purchases WHERE owner_user_id=19 AND offer_id=${oid} ORDER BY id DESC LIMIT 1`));sql(`UPDATE st_commerce_purchases SET redeem_by=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=${p2}`);await post(adult,`/akun/voucher/${p2}/gunakan`,{learner_id:'19',confirm:'1'},409);
// Multiple independent spends cannot overdraw the wallet.
const a2=await adult('/akun/kredit'),b2=await second('/akun/kredit');const d1={...buy(),revision:'3'},d2={...buy(),revision:'3'};await Promise.all([adult('/akun/kredit/beli/'+oid,{...d1,study_csrf_token:field(a2.body,'study_csrf_token')}),second('/akun/kredit/beli/'+oid,{...d2,study_csrf_token:field(b2.body,'study_csrf_token')})]).then(rs=>assert.deepEqual(rs.map(r=>r.status),[303,303]));await post(adult,'/akun/kredit/beli/'+oid,{...buy(),revision:'3'},409);
assert.equal(sql('SELECT balance_credits FROM st_wallets WHERE owner_user_id=19'),'0');assert.equal(sql('SELECT COUNT(*) FROM st_wallets w WHERE w.balance_credits<>(SELECT COALESCE(SUM(e.delta_credits),0) FROM st_wallet_entries e WHERE e.wallet_id=w.id)'),'0');
sql('UPDATE st_users SET email_verified_at=NULL WHERE id=20');await post(other,'/akun/kredit/beli/'+oid,{...buy(),revision:'3'},403);sql('UPDATE st_users SET email_verified_at=UTC_TIMESTAMP() WHERE id=20');
assert.equal((await parent('/akun/kredit?halaman[]=1')).status,400);
console.log('PASS commerce HTTP: role, CSRF, verified contact, product validation, stale revision, wallet/ledger atomicity, ownership, cross-family denial, immutable voucher snapshot, redeem replay/expiry, audit-failure rollback and two-worker duplicate/spend races.');
