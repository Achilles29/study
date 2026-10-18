import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {randomBytes,createHash} from 'node:crypto';
import {readFileSync,writeFileSync,unlinkSync} from 'node:fs';
const db=process.env.STUDY_MONITOR_TEST_DB,spool=process.env.STUDY_PAYMENT_TEST_DIRECTORY;assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);assert.match(spool||'',/^\/tmp\/study-payment-qa-[A-Za-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
function client(port=8097){const jar=new Map();return async(path,data)=>{const r=await fetch(`http://127.0.0.1:${port}`+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}const body=await r.text();assert.ok(!/Severity:|Fatal error|Database Error/.test(body),body.slice(0,250));return {status:r.status,body,location:r.headers.get('location')};};}
const field=(b,n)=>{const m=b.match(new RegExp(`name="${n}" value="([^"]*)"`));assert.ok(m,n);return m[1];};
async function login(id,port){const r=client(port),p=await r('/masuk');assert.equal((await r('/masuk',{identity:'monitor'+id,password:'Monitor-QA-Only-2026!',study_csrf_token:field(p.body,'study_csrf_token')})).status,303);return r;}
async function post(r,path,data,status=303,from='/akun/kredit'){const p=await r(from);assert.equal(p.status,200,from);const out=await r(path,{...data,study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(out.status,status,`${path}: ${out.status} vs ${status}`);return out;}
sql('UPDATE st_users SET email_verified_at=UTC_TIMESTAMP() WHERE id IN(1,2,21,22,23)');
const root=await login(1),adult=await login(21),parent=await login(2),other=await login(22),child=await login(3);
const key='SB-Mid-server-QAOnlyNotARealKey123456789';
const conf=sql('SELECT COALESCE(MAX(id),0) FROM st_payment_configs');await post(root,'/kelola/pengaturan/pembayaran',{previous_id:conf,action:'save',environment:'sandbox',merchant_id:'QA-MERCHANT',server_key:key,client_key:''},303,'/kelola/pengaturan/pembayaran');sql("UPDATE st_payment_configs SET probe_result='status_api_reached' ORDER BY id DESC LIMIT 1");
async function offer(kind,price,credit='0',days='0'){const r=await post(root,'/kelola/produk/0',{title:'QA '+kind,kind,package_id:'0',price,credit_amount:credit,access_days:days,voucher_days:'0',status:'active',revision:'0',confirm:'1',reason:'Skenario transaksi sandbox sintetis'},303,'/kelola/produk/0');return Number(r.location.split('/').at(-1));}
const topup=await offer('topup','20000','200'),platinum=await offer('platinum','300000','0','365'),slot=await offer('slot','10000');
const form=()=>({revision:'1',request_key:randomBytes(32).toString('hex'),confirm:'1',amount_idr:'1',credit_amount:'999999'});
async function checkout(r,id,data=form()){const out=await post(r,'/akun/kredit/checkout/'+id,data);return Number(out.location.split('/').at(-1));}
const shared=form(),order=await checkout(adult,topup,shared);assert.equal(await checkout(adult,topup,shared),order);
const code=id=>sql('SELECT order_code FROM st_payment_orders WHERE id='+id),load=id=>JSON.parse(readFileSync(spool+'/'+code(id)+'.json','utf8')),save=(id,v)=>writeFileSync(spool+'/'+code(id)+'.json',JSON.stringify(v),{mode:0o600});
function signed(v){return {...v,signature_key:createHash('sha512').update(v.order_id+v.status_code+v.gross_amount+key).digest('hex')};}
async function notify(v,expected=200,port=8097){const r=await fetch(`http://127.0.0.1:${port}/pembayaran/notifikasi`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(v)});assert.equal(r.status,expected,'notify '+r.status+' vs '+expected);return r.json();}
const balance=id=>sql(`SELECT balance_credits FROM st_wallets WHERE owner_user_id=${id} AND environment='sandbox'`);
assert.equal(sql('SELECT amount_idr FROM st_payment_orders WHERE id='+order),'20000');assert.equal(balance(21),'0');assert.equal((await other('/akun/pesanan/'+order)).status,404);assert.equal((await child('/akun/pesanan/'+order)).status,403);
const pending=load(order);await notify({...signed(pending),signature_key:'bad'},403);await notify(signed({...pending,gross_amount:'1.00'}),403);await notify(signed({...pending,payment_type:'bank_transfer'}),403);await notify(signed({...pending,merchant_id:'OTHER'}),403);
await notify(signed({...pending,transaction_status:'settlement',status_code:'200'}));assert.equal(balance(21),'0','Even signed notification cannot settle while status API remains pending');
let settled={...pending,transaction_status:'settlement',status_code:'200'};save(order,{...settled,gross_amount:'1.00'});await notify(signed(pending),409);assert.equal(balance(21),'0');save(order,settled);
sql("CREATE TRIGGER qa_payment_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA payment audit failure'");try{await notify(signed(settled),503);assert.equal(balance(21),'0');assert.equal(sql('SELECT status FROM st_payment_orders WHERE id='+order),'pending');}finally{sql('DROP TRIGGER qa_payment_audit_fail');}
await Promise.all([notify(signed(settled)),notify(signed(settled),200,8098)]);assert.equal(balance(21),'200');assert.equal(sql(`SELECT COUNT(*) FROM st_wallet_entries WHERE reference_type='payment_order' AND reference_id=${order}`),'1');
save(order,pending);await notify(signed(pending));assert.equal(sql('SELECT status FROM st_payment_orders WHERE id='+order),'paid');assert.equal(balance(21),'200');save(order,settled);
await post(adult,'/akun/kredit/checkout/'+platinum,form(),403);await post(adult,'/akun/kredit/checkout/'+slot,form(),403);
const plat=await checkout(parent,platinum),sl=await checkout(parent,slot);for(const id of [plat,sl]){const v={...load(id),transaction_status:'settlement',status_code:'200'};save(id,v);await notify(signed(v));await notify(signed(v));}
assert.equal(sql(`SELECT COUNT(*) FROM st_commerce_grants g JOIN st_commerce_purchases p ON p.id=g.purchase_id WHERE p.payment_order_id=${plat} AND g.kind='platinum'`),'1');assert.equal(sql(`SELECT COUNT(*) FROM st_family_slots s JOIN st_commerce_purchases p ON p.id=s.purchase_id WHERE p.payment_order_id=${sl}`),'1');
// A timeout leaves the same order recoverable; no automatic new charge/order.
writeFileSync(spool+'/fail','qa',{mode:0o600});let timeout;try{timeout=await checkout(other,topup);assert.equal(sql('SELECT status FROM st_payment_orders WHERE id='+timeout),'created');await post(other,'/akun/pesanan/'+timeout,{},503,'/akun/pesanan/'+timeout);}finally{unlinkSync(spool+'/fail');}
sql('UPDATE st_payment_orders SET checked_at=NULL WHERE id='+timeout);await post(other,'/akun/pesanan/'+timeout,{},303,'/akun/pesanan/'+timeout);assert.equal(sql('SELECT status FROM st_payment_orders WHERE id='+timeout),'pending');
// Expired/denied/cancelled state and eventual authoritative settlement.
for(const status of ['expire','deny','cancel']){const id=await checkout(other,topup),v={...load(id),transaction_status:status,status_code:'202'};save(id,v);await notify(signed(v));assert.ok(['expired','failed','cancelled'].includes(sql('SELECT status FROM st_payment_orders WHERE id='+id)));}
const late=await checkout(other,topup),old=load(late);save(late,{...old,transaction_status:'expire',status_code:'202'});await notify(signed(old));const latePaid={...old,transaction_status:'settlement',status_code:'200'};save(late,latePaid);await notify(signed(latePaid));assert.equal(balance(22),'200');
// Refund events create a review flag, not a negative balance or deletion.
save(order,{...settled,transaction_status:'refund'});await notify(signed({...settled,transaction_status:'refund'}));assert.equal(sql('SELECT review_required FROM st_payment_orders WHERE id='+order),'1');assert.equal(balance(21),'200');
assert.equal((await adult('/kelola/pesanan')).status,403);assert.equal((await root('/kelola/pesanan?status=review')).status,200);
console.log('PASS sandbox checkout: server prices, ownership/CSRF, pending, forged signatures/method/merchant/amount, authoritative status, atomic ledger rollback, parallel duplicate callbacks, out-of-order status, timeout recovery, expiry/failure/cancel, late settlement, Platinum/slot exactly once and refund review. Synthetic transport, not a real merchant transaction.');
