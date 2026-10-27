import assert from 'node:assert/strict';
import {spawn, execFileSync} from 'node:child_process';

assert.equal(process.env.STUDY_MONITOR_TEST_DB, 'study_qa_monitor_sso039');
const base = 'http://127.0.0.1:8097';
const sql = query => execFileSync('/www/server/mysql/bin/mysql', ['--defaults-file=/etc/projectpintar/mariadb.cnf','-uroot','-N','study_qa_monitor_sso039'], {input:query,encoding:'utf8'}).trim();
const before = sql('SELECT CONCAT(revision,":",chat_id) FROM st_notification_config WHERE id=1');
const beforeAi = sql('SELECT CONCAT(revision,":",enabled) FROM st_bot_ai_config WHERE id=1');
const server = spawn('/www/server/php/84/bin/php', ['-d','mysqli.default_socket=/tmp/mysql-study.sock','-S','127.0.0.1:8097','scripts/php-dev-router.php'], {
  cwd:'/www/wwwroot/study', stdio:'ignore', env:{...process.env,CI_ENV:'development',APP_BASE_URL:base+'/',APP_COOKIE_SECURE:'0',DB_HOST:'localhost',DB_PORT:'3308',DB_USERNAME:'root',DB_PASSWORD:'',DB_DATABASE:'study_qa_monitor_sso039',STUDY_TELEGRAM_PAIR_TEST_GETME:JSON.stringify({ok:true,result:{is_bot:true,username:'ProjectPintarTestBot'}}),STUDY_TELEGRAM_PAIR_TEST_GETUPDATES:JSON.stringify({ok:true,result:[]})}
});
const cookies = new Map();
const request = async (path, data) => {
  const response = await fetch(base+path, {redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...cookies].map(([key,value])=>`${key}=${value}`).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams(data):undefined});
  for (const cookie of response.headers.getSetCookie()) { const item=cookie.split(';')[0], i=item.indexOf('='); cookies.set(item.slice(0,i),item.slice(i+1)); }
  const body = await response.text();
  assert.ok(!/Severity:|Fatal error|Database Error|A PHP Error/.test(body), body.slice(0,400));
  return {status:response.status,body};
};
const field = (html,name) => { const match=html.match(new RegExp(`name="${name}" value="([^"]*)"`)); assert.ok(match,name); return match[1]; };
try {
  let ready=false;
  for(let i=0;i<40;i++) { try { await request('/masuk'); ready=true; break; } catch { await new Promise(resolve=>setTimeout(resolve,100)); } }
  assert.ok(ready,'QA server did not start');
  const login=await request('/masuk');
  assert.equal((await request('/masuk',{identity:'monitor1',password:'Monitor-QA-Only-2026!',study_csrf_token:field(login.body,'study_csrf_token')})).status,303);
  let page=await request('/kelola/pengaturan/telegram');
  assert.equal(page.status,200);
  assert.ok(page.body.includes('Hubungkan chat pribadi'));
  assert.ok(page.body.includes('Koneksi bot'));
  assert.ok(page.body.includes('Percakapan AI'));
  assert.ok(page.body.includes('name="action" value="save_connection"'));
  assert.ok(!page.body.includes('name="action" value="ai_save"'));
  assert.equal((await request('/kelola/pengaturan/telegram',{action:'pair_start',study_csrf_token:field(page.body,'study_csrf_token')})).status,303);
  page=await request('/kelola/pengaturan/telegram');
  assert.match(page.body,/https:\/\/t\.me\/ProjectPintarTestBot\?start=pp_[a-f0-9]{32}/);
  assert.ok(!page.body.includes('123456789:'+('a'.repeat(35))),'Bot token exposed in pairing page');
  const checked=await request('/kelola/pengaturan/telegram',{action:'pair_check',confirm:'1',study_csrf_token:field(page.body,'study_csrf_token')});
  assert.equal(checked.status,200);
  assert.ok(checked.body.includes('Chat pribadi belum terdeteksi'));
  assert.equal(sql('SELECT CONCAT(revision,":",chat_id) FROM st_notification_config WHERE id=1'),before,'Missing chat must not change settings');
  const notifications=await request('/kelola/pengaturan/telegram?tab=notifications');
  assert.equal(notifications.status,200);
  assert.ok(notifications.body.includes('name="action" value="save_preferences"'));
  assert.ok(!notifications.body.includes('name="token"'));
  const preferenceKeys=['enabled','members','children','payments','donations','help','visitors'];
  const savedBefore=sql('SELECT CONCAT_WS(":",chat_id,COALESCE(SHA2(token_cipher,256),""),enabled,members,children,payments,donations,help,visitors) FROM st_notification_config WHERE id=1');
  const preferenceRevision=notifications.body.match(/name="action" value="save_preferences"[\s\S]*?name="revision" value="(\d+)"/);
  assert.ok(preferenceRevision,'Notification settings form missing');
  const preferenceValues=sql('SELECT enabled,members,children,payments,donations,help,visitors FROM st_notification_config WHERE id=1').split('\t');
  const savedPreferences=await request('/kelola/pengaturan/telegram',{action:'save_preferences',revision:preferenceRevision[1],confirm:'1',study_csrf_token:field(notifications.body,'study_csrf_token'),...Object.fromEntries(preferenceKeys.map((key,i)=>[key,preferenceValues[i]]))});
  assert.equal(savedPreferences.status,303);
  assert.ok(savedPreferences.body==='' || !savedPreferences.body.includes('Fatal error'));
  assert.equal(sql('SELECT CONCAT_WS(":",chat_id,COALESCE(SHA2(token_cipher,256),""),enabled,members,children,payments,donations,help,visitors) FROM st_notification_config WHERE id=1'),savedBefore,'Saving notification choices must preserve connection and existing choices');
  const aiPage=await request('/kelola/pengaturan/telegram?tab=ai');
  assert.equal(aiPage.status,200);
  assert.ok(aiPage.body.includes('Kunci OpenAI API'));
  assert.ok(aiPage.body.includes('Hubungkan akun ChatGPT ke Codex bot'));
  assert.ok(aiPage.body.includes('https://auth.openai.com/codex/device'));
  assert.ok(aiPage.body.includes('data-copy-target="codex-login-command"'));
  assert.ok(aiPage.body.includes('name="provider" value="codex"'));
  const aiRevision = aiPage.body.match(/name="action" value="ai_save"[\s\S]*?name="revision" value="(\d+)"/);
  assert.ok(aiRevision,'AI settings form missing');
  const invalidAi = await request('/kelola/pengaturan/telegram',{action:'ai_save',revision:aiRevision[1],key:'',enabled:'0',daily_limit:'0',confirm:'1',study_csrf_token:field(aiPage.body,'study_csrf_token')});
  assert.equal(invalidAi.status,400);
  assert.ok(invalidAi.body.includes('Periksa batas harian'));
  assert.equal(sql('SELECT CONCAT(revision,":",enabled) FROM st_bot_ai_config WHERE id=1'),beforeAi,'Invalid AI settings must not be saved');
  const history=await request('/kelola/pengaturan/telegram?tab=history');
  assert.equal(history.status,200);
  assert.ok(history.body.includes('20 pengiriman terakhir'));
  console.log('PASS: Telegram tabs, private pairing, notification save preservation, and AI form validation.');
} finally {
  server.kill('SIGTERM');
}
