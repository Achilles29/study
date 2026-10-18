import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {generateKeyPairSync,randomBytes,sign,createHmac} from 'node:crypto';
import {writeFileSync} from 'node:fs';
const db=process.env.STUDY_MONITOR_TEST_DB,dir=process.env.STUDY_GOOGLE_TEST_DIRECTORY;
assert.match(db||'',/^study_qa_monitor_[a-z0-9]+$/);assert.match(dir||'',/^\/tmp\/study-google-qa-[A-Za-z0-9]+$/);
const sql=q=>execFileSync('/www/server/mysql/bin/mysql',['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N',db],{input:q,encoding:'utf8'}).trim();
function client(){const jar=new Map();return async(path,data)=>{const r=await fetch('http://127.0.0.1:8097'+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});for(const c of r.headers.getSetCookie()){const p=c.split(';')[0],i=p.indexOf('=');jar.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text(),location:r.headers.get('location')};};}
const field=(b,n)=>b.match(new RegExp(`name="${n}" value="([^"]*)"`))[1];
const old=sql('SELECT CONCAT(sub_hash,"|",created_at) FROM st_google_identities WHERE user_id=1');
const sub='041'+Date.now(),hash=createHmac('sha256','change-this-local-development-key').update('google-sub|'+sub).digest('hex');
const {privateKey,publicKey}=generateKeyPairSync('rsa',{modulusLength:2048});writeFileSync(dir+'/jwks.json',JSON.stringify({keys:[{...publicKey.export({format:'jwk'}),kid:'qa-key',alg:'RS256',use:'sig'}]}),{mode:0o600});
try{
 sql("DELETE FROM st_google_identities WHERE user_id=1; INSERT INTO st_google_identities(user_id,sub_hash,created_at) VALUES(1,'"+hash+"',UTC_TIMESTAMP())");
 const c=client(),p=await c('/masuk'),start=await c('/auth/google/mulai',{purpose:'login',study_csrf_token:field(p.body,'study_csrf_token')});assert.equal(start.status,303);const flow=new URL(start.location).searchParams,now=Math.floor(Date.now()/1000),claims={iss:'https://accounts.google.com',aud:flow.get('client_id'),sub,nonce:flow.get('nonce'),iat:now,exp:now+300,email_verified:true};const data=Buffer.from(JSON.stringify({alg:'RS256',kid:'qa-key',typ:'JWT'})).toString('base64url')+'.'+Buffer.from(JSON.stringify(claims)).toString('base64url'),token=data+'.'+sign('RSA-SHA256',Buffer.from(data),privateKey).toString('base64url'),code=randomBytes(32).toString('hex');writeFileSync(dir+'/'+code+'.json',JSON.stringify({id_token:token,challenge:flow.get('code_challenge')}),{mode:0o600});
 assert.equal((await c('/auth/google/callback?'+new URLSearchParams({state:flow.get('state'),code}))).location,'http://127.0.0.1:8097/kelola');assert.equal((await c('/kelola')).status,200);assert.equal((await c('/beranda')).status,200,'Admin can intentionally view learner area');
 for(const route of ['/masuk','/daftar','/auth/google/daftar'])assert.equal((await c(route)).location,'http://127.0.0.1:8097/kelola');
 const q=await c('/akun/pengaturan');assert.equal(q.status,200);assert.equal((await c('/akun/pengaturan',{study_csrf_token:field(q.body,'study_csrf_token')})).status,405);assert.equal((await c('/account_settings/render_page')).status,404);
 const guest=client();assert.ok([302,303,307].includes((await guest('/akun/pengaturan')).status));
 console.log('PASS Google Superadmin -> panel, signed-in login/signup redirect, intentional learner view allowed, account settings method and direct-call guards. Synthetic provider only.');
}finally{sql('DELETE FROM st_google_identities WHERE user_id=1');if(old){const [saved,created]=old.split('|');assert.match(saved,/^[a-f0-9]{64}$/);assert.match(created,/^[0-9 :\-]+$/);sql("INSERT INTO st_google_identities(user_id,sub_hash,created_at) VALUES(1,'"+saved+"','"+created+"')");}}
