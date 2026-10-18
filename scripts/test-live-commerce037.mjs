import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {randomBytes,createHash} from 'node:crypto';
import {readFileSync,writeFileSync,unlinkSync} from 'node:fs';
const db=process.env.STUDY_MONITOR_TEST_DB,dir=process.env.STUDY_PAYMENT_TEST_DIRECTORY;
assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);assert.match(dir||'',/^\/tmp\/study-payment-qa-[A-Za-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
function client(port=8097){const jar=new Map();return async(path,data)=>{const r=await fetch(`http://127.0.0.1:${port}${path}`,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text(),location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(id,port){const r=client(port),p=await r('/masuk');assert.equal((await r('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return r;}
async function post(r,path,data,status=303,from='/akun/kredit?environment=production'){const p=await r(from);assert.equal(p.status,200,from);const out=await r(path,{environment:'production',...data,study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(out.status,status,path+' expected '+status+' got '+out.status);assert.ok(!/Severity:|Fatal error|Database Error/.test(out.body));return out;}
const start=Number(sql('SELECT MAX(id) FROM st_users'))+100,ids={parent:start+1,adult:start+2,other:start+3,child:start+4};
for(const [kind,id] of Object.entries(ids)){const account=kind==='child'?'child':kind==='adult'?'adult':'parent';sql(`INSERT INTO st_users(id,username,email,password_hash,display_name,account_kind,learning_stage,status,email_verified_at,created_at,updated_at) SELECT ${id},'monitor${id}',NULL,password_hash,'QA Live ${kind}','${account}','${account==='child'?'primary':'adult'}','active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users WHERE id=1;`);}
sql(`INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) VALUES(${ids.parent},${ids.child},'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());`);
const root=await login(1),parent=await login(ids.parent),adult=await login(ids.adult),other=await login(ids.other),child=await login(ids.child),editor=await login(6);
const settings='/kelola/pengaturan/pembayaran',liveSettings=settings+'?environment=production';
const latest=env=>sql(`SELECT COALESCE(MAX(id),0) FROM st_payment_configs WHERE environment='${env}'`);
const key='Mid-server-QAOnlyLiveNotReal123456',sbkey='SB-Mid-server-QAOnlySandboxNotReal123456';
const config={action:'save',merchant_id:'QA-MERCHANT',server_key:key,client_key:'',password:'Monitor-QA-Only-2026!',checkout_enabled:'0'};
assert.equal((await editor(liveSettings)).status,403);
assert.equal((await root(settings,{...config,environment:'production',previous_id:latest('production')})).status,403);
await post(root,settings,{...config,password:'bad',previous_id:latest('production')},403,liveSettings);
await post(root,settings,{...config,server_key:sbkey,previous_id:latest('production')},400,liveSettings);
await post(root,settings,{...config,checkout_enabled:'1',confirm_live:'1',previous_id:latest('production')},400,liveSettings);
const stale=latest('production');await post(root,settings,{...config,previous_id:stale},303,liveSettings);const saved=latest('production');
await post(root,settings,{...config,previous_id:stale},409,liveSettings);
await post(root,settings,{...config,server_key:'',checkout_enabled:'1',confirm_live:'1',previous_id:saved},400,liveSettings);
writeFileSync(dir+'/probe-production','ok',{mode:0o600});await post(root,settings,{action:'probe',previous_id:saved,password:config.password},303,liveSettings);
await post(root,settings,{...config,server_key:'',checkout_enabled:'1',confirm_live:'1',previous_id:saved},303,liveSettings);const active=latest('production');
assert.equal(sql(`SELECT checkout_enabled FROM st_payment_configs WHERE id=${active}`),'1');
await post(root,settings,{...config,server_key:sbkey,environment:'sandbox',previous_id:latest('sandbox')},303,settings+'?environment=sandbox');
assert.equal(latest('production'),active,'Sandbox save must not replace production profile');
assert.ok(!(await root(liveSettings)).body.includes(key));assert.equal(sql("SELECT COUNT(*) FROM st_audit_events WHERE details_json LIKE '%QAOnlyLiveNotReal%'"),'0');
// A single synthetic question in QA, not curriculum or production data.
sql("INSERT INTO st_questions(course_id,created_by_user_id,visibility,question_type,prompt,explanation,status,created_at,updated_at) VALUES(1,1,'shared','single_choice','QA: pilih A','QA only','published',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @qa_question=LAST_INSERT_ID(); INSERT INTO st_question_options(question_id,option_text,is_correct,sort_order) VALUES(@qa_question,'A',1,1),(@qa_question,'B',0,2);");
const packPage=await post(root,'/kelola/paket/0',{title:'QA Live Package',summary:'Synthetic QA only',status:'published',is_official:'1',revision:'0',confirm:'1',family_practice_enabled:'1',point_price:'0',point_days:'0',requires_questions:'1','courses[]':'1'},303,'/kelola/paket/0');const pack=Number(packPage.location.split('/').at(-1));
async function offer(kind,price,{package_id=0,credits=0,days=0,voucher=0,approved='1'}={}){const v={title:'QA Live '+kind,kind,package_id:String(package_id),price:String(price),credit_amount:String(credits),access_days:String(days),voucher_days:String(voucher),status:'active',revision:'0',confirm:'1',reason:'Synthetic QA live isolation test',live_approved:approved};const r=await post(root,'/kelola/produk/0',v,303,'/kelola/produk/0');return Number(r.location.split('/').at(-1));}
const draftPack=sql("SELECT id FROM st_packages WHERE slug='draft-latihan-topik-sd'");
await post(root,'/kelola/produk/0',{title:'Must reject empty draft',kind:'package',package_id:draftPack,price:'60',credit_amount:'0',access_days:'365',voucher_days:'0',status:'active',revision:'0',confirm:'1',reason:'Synthetic blank content approval',live_approved:'1'},400,'/kelola/produk/0');
const topup=await offer('topup',20000,{credits:200}),platinum=await offer('platinum',300000,{days:365}),slot=await offer('slot',10000),packageOffer=await offer('package',40,{package_id:pack,days:365}),voucher=await offer('voucher',40,{package_id:pack,days:365,voucher:30}),unapproved=await offer('topup',50000,{credits:500,approved:'0'});
const form=()=>({revision:'1',request_key:randomBytes(32).toString('hex'),confirm:'1',amount_idr:'1',credit_amount:'999999'});
await post(parent,'/akun/kredit/checkout/'+unapproved,form(),409);
async function checkout(r,product,data=form()){const out=await post(r,'/akun/kredit/checkout/'+product,data);return Number(out.location.split('/').at(-1));}
const shared=form(),order=await checkout(parent,topup,shared);assert.equal(await checkout(parent,topup,shared),order);
assert.equal(sql('SELECT amount_idr FROM st_payment_orders WHERE id='+order),'20000');
const code=id=>sql('SELECT order_code FROM st_payment_orders WHERE id='+id),load=id=>JSON.parse(readFileSync(dir+'/'+code(id)+'.json','utf8')),save=(id,v)=>writeFileSync(dir+'/'+code(id)+'.json',JSON.stringify(v),{mode:0o600});
const sign=(v,k=key)=>({...v,signature_key:createHash('sha512').update(v.order_id+v.status_code+v.gross_amount+k).digest('hex')});
async function notify(v,status=200,port=8097){const r=await fetch(`http://127.0.0.1:${port}/pembayaran/notifikasi`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(v)});assert.equal(r.status,status,'Callback status');}
const balance=(id,env='production')=>Number(sql(`SELECT COALESCE(MAX(balance_credits),0) FROM st_wallets WHERE owner_user_id=${id} AND environment='${env}'`));
assert.match(code(order),/^PP-LIVE-/);assert.equal(balance(ids.parent),0);const pending=load(order);
assert.equal((await other('/akun/pesanan/'+order)).status,404);assert.equal((await child('/akun/pesanan/'+order)).status,403);
for(const v of [{...pending,merchant_id:'OTHER'},{...pending,gross_amount:'1.00'},{...pending,payment_type:'bank_transfer'},{...pending,currency:'USD'}])await notify(sign(v),403);
await notify(sign(pending,sbkey),403);await notify({...sign(pending),signature_key:'fake'},403);
await notify(sign({...pending,transaction_status:'settlement',status_code:'200'}));assert.equal(balance(ids.parent),0,'Signed callback cannot replace authoritative status');
const settled={...pending,transaction_status:'settlement',status_code:'200'};save(order,{...settled,gross_amount:'1.00'});await notify(sign(pending),409);assert.equal(balance(ids.parent),0);save(order,settled);
sql("CREATE TRIGGER qa_live_audit_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA rollback'");try{await notify(sign(settled),503);assert.equal(balance(ids.parent),0);}finally{sql('DROP TRIGGER qa_live_audit_failure');}
await Promise.all([notify(sign(settled)),notify(sign(settled),200,8098)]);assert.equal(balance(ids.parent),200);assert.equal(balance(ids.parent,'sandbox'),0);
save(order,pending);await notify(sign(pending));assert.equal(balance(ids.parent),200);save(order,settled);
// Retain old config on order after key rotation and checkout pause.
const waiting=await checkout(parent,topup);await post(root,settings,{...config,server_key:'Mid-server-QAOnlyRotatedNotReal123456',previous_id:latest('production')},303,liveSettings);
const oldSettled={...load(waiting),transaction_status:'settlement',status_code:'200'};save(waiting,oldSettled);await notify(sign(oldSettled));assert.equal(balance(ids.parent),400);
await post(parent,'/akun/kredit/checkout/'+topup,form(),409);
await post(root,settings,{...config,previous_id:latest('production')},303,liveSettings);await post(root,settings,{action:'probe',previous_id:latest('production'),password:config.password},303,liveSettings);await post(root,settings,{...config,server_key:'',checkout_enabled:'1',confirm_live:'1',previous_id:latest('production')},303,liveSettings);
await post(adult,'/akun/kredit/checkout/'+slot,form(),403);
for(const product of [platinum,slot]){const id=await checkout(parent,product),v={...load(id),transaction_status:'settlement',status_code:'200'};save(id,v);await notify(sign(v));await notify(sign(v));}
assert.equal(sql(`SELECT COUNT(*) FROM st_family_slots WHERE owner_user_id=${ids.parent} AND environment='production'`),'1');assert.equal(sql(`SELECT COUNT(*) FROM st_commerce_grants WHERE owner_user_id=${ids.parent} AND kind='platinum' AND environment='production'`),'1');
const buyForm=()=>({revision:'1',package_revision:'1',learner_id:String(ids.child),request_key:randomBytes(32).toString('hex'),confirm:'1'});
await post(other,'/akun/kredit/beli/'+packageOffer,buyForm(),403);
const buy=buyForm();await post(parent,'/akun/kredit/beli/'+packageOffer,buy);await post(parent,'/akun/kredit/beli/'+packageOffer,buy);assert.equal(balance(ids.parent),360);
await post(parent,'/akun/kredit/beli/'+packageOffer,{...buy,environment:'sandbox'},409,'/akun/kredit?environment=sandbox');
await post(adult,'/akun/kredit/beli/'+packageOffer,{...buyForm(),learner_id:String(ids.adult)},409);
const vf={...buyForm(),learner_id:'0'};await post(parent,'/akun/kredit/beli/'+voucher,vf);const purchase=Number(sql(`SELECT id FROM st_commerce_purchases WHERE request_key='${vf.request_key}'`));assert.equal(balance(ids.parent),320);
await post(other,`/akun/voucher/${purchase}/gunakan`,{learner_id:String(ids.other),confirm:'1'},404);
await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:String(ids.child),confirm:'1'});await post(parent,`/akun/voucher/${purchase}/gunakan`,{learner_id:String(ids.child),confirm:'1'});assert.equal(sql(`SELECT COUNT(*) FROM st_commerce_grants WHERE purchase_id=${purchase}`),'1');
const expired={...buyForm(),learner_id:'0'};await post(parent,'/akun/kredit/beli/'+voucher,expired);const expiredId=Number(sql(`SELECT id FROM st_commerce_purchases WHERE request_key='${expired.request_key}'`));sql(`UPDATE st_commerce_purchases SET redeem_by=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=${expiredId}`);await post(parent,`/akun/voucher/${expiredId}/gunakan`,{learner_id:String(ids.child),confirm:'1'},409);
const compensate={confirm:'1',password:config.password,reason:'QA unused voucher compensation'};await post(root,`/kelola/voucher/${expiredId}/kompensasi`,compensate,303,`/kelola/kredit/${ids.parent}?environment=production`);await post(root,`/kelola/voucher/${expiredId}/kompensasi`,compensate,303,`/kelola/kredit/${ids.parent}?environment=production`);assert.equal(balance(ids.parent),320);assert.equal(balance(ids.parent,'sandbox'),0);
// Failed purchase audit cannot debit a real-mode wallet.
sql("CREATE TRIGGER qa_live_buy_failure BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA rollback'");try{await post(parent,'/akun/kredit/beli/'+packageOffer,buyForm(),503);assert.equal(balance(ids.parent),320);}finally{sql('DROP TRIGGER qa_live_buy_failure');}
writeFileSync(dir+'/fail','qa',{mode:0o600});let timeout;try{timeout=await checkout(other,topup);assert.equal(sql('SELECT status FROM st_payment_orders WHERE id='+timeout),'created');}finally{unlinkSync(dir+'/fail');}await post(other,'/akun/pesanan/'+timeout,{},303,'/akun/pesanan/'+timeout);assert.equal(sql('SELECT status FROM st_payment_orders WHERE id='+timeout),'pending');
const late={...load(timeout),transaction_status:'expire',status_code:'202'};save(timeout,late);await notify(sign(late));late.transaction_status='settlement';late.status_code='200';save(timeout,late);await notify(sign(late));assert.equal(balance(ids.other),200);
save(order,{...settled,transaction_status:'refund'});await notify(sign({...settled,transaction_status:'refund'}));assert.equal(balance(ids.parent),320);await post(parent,'/akun/kredit/beli/'+packageOffer,buyForm(),409);await post(parent,'/akun/kredit/checkout/'+topup,form(),409);
assert.equal(sql(`SELECT COUNT(*) FROM st_wallets WHERE environment='production' AND balance_credits<0`),'0');
assert.equal(sql("SELECT COUNT(*) FROM st_commerce_offers WHERE draft_code IS NOT NULL AND (status<>'draft' OR live_approved<>0)"),'0','Seed drafts never activated');
writeFileSync(dir+'/live-state.json',JSON.stringify({ids,pack,order,topup,platinum,slot,packageOffer,voucher}),{mode:0o600});
console.log('PASS live settings, reauth/CSRF/environment/activation guards, draft/content protection, QRIS status/signature/amount/merchant, immutable key rotation and pause, duplicate parallel callbacks, ledger/grants/slots exactly once, family isolation, voucher expiry/replay/compensation, audit rollback, timeout/late settlement/refund freeze. Synthetic transport only; no real charge.');
