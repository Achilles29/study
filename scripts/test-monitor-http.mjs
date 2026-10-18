import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const base = process.env.STUDY_MONITOR_TEST_URL || 'http://127.0.0.1:8097';
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)) throw new Error('Only isolated loopback QA is accepted.');
function client() {
 const cookies = new Map();
 return async (path, data) => {
  const response = await fetch(base + path, {redirect: 'manual', method: data ? 'POST' : 'GET', headers: {Cookie: [...cookies].map(([k,v]) => `${k}=${v}`).join('; '), ...(data ? {'Content-Type': 'application/x-www-form-urlencoded'} : {})}, body: data ? new URLSearchParams(data) : undefined});
  for (const c of response.headers.getSetCookie()) { const pair = c.split(';')[0]; const at = pair.indexOf('='); cookies.set(pair.slice(0,at),pair.slice(at+1)); }
  const body = await response.text();
  assert.ok(!/Severity:|Fatal error|Database Error|Warning:/.test(body), `Application error at ${path}`);
  return {status: response.status, body, headers: response.headers};
 };
}
const token = body => { const match = body.match(/name="study_csrf_token" value="([^"]+)"/); assert.ok(match, 'CSRF token'); return match[1]; };
async function login(user) { const request = client(); const form = await request('/masuk'); const r = await request('/masuk', {identity: user, password: 'Monitor-QA-Only-2026!', study_csrf_token: token(form.body)}); assert.equal(r.status, 303); return request; }
const guest = client(); assert.equal((await guest('/kelola/pembelajaran')).status, 307);
const admin = await login('monitor1');
for (const path of ['/kelola', '/kelola/pembelajaran', '/kelola/pembelajaran?halaman=2', '/kelola/pembelajaran/modul', '/kelola/pembelajaran/modul/latihan', '/kelola/pembelajaran/pengguna/3', '/kelola/pembelajaran/pengguna/3?tab=latihan', '/kelola/pembelajaran/pengguna/3?tab=akses', '/kelola/pembelajaran/aktivitas/latihan/2', '/kelola/rapor', '/kelola/rapor/3']) {
 const r = await admin(path); assert.equal(r.status, 200, path); assert.ok(r.body.includes('admin-sidebar'), 'Dedicated shell'); assert.match(r.headers.get('x-robots-tag'), /noindex/); assert.ok(!r.body.includes('PRIVATE FAMILY TITLE') && !r.body.includes('PRIVATE ANSWER'), 'Private content absent');
}
for (const path of ['/kelola/pembelajaran?mulai=2026-02-30', '/kelola/pembelajaran?status[]=running', '/kelola/pembelajaran?mulai=2020-01-01', '/kelola/pembelajaran?modul=unknown', '/kelola/pembelajaran?pencarian=bad', '/kelola/pembelajaran/pengguna/3?tab=invalid']) assert.equal((await admin(path)).status, 400, path);
assert.equal((await admin('/kelola/pembelajaran/pengguna/999999')).status, 404);
assert.equal((await admin('/kelola/pembelajaran/aktivitas/latihan/999999')).status, 404);
let scoped = await admin('/kelola/pembelajaran/modul/materi?modul=materi&konten=1'); assert.equal(scoped.status,200); assert.match(scoped.body,/dari 2 pengguna/,'Specific lesson participants');
assert.match(scoped.body, /type="hidden" name="modul" value="materi"/, 'Content scope locks module');
scoped = await admin('/kelola/pembelajaran/modul?modul=latihan'); assert.equal(scoped.status,200); assert.ok(!scoped.body.includes('Lihat peserta Flashcard'), 'Module overview respects filter');
scoped = await admin('/kelola/pembelajaran/pengguna/3?tab=latihan'); assert.equal(scoped.status,200); assert.match(scoped.body,/type="hidden" name="modul" value="latihan"/, 'Tab and filter agree');
scoped = await admin('/kelola/pembelajaran/modul/latihan?modul=latihan&konten=2'); assert.equal(scoped.status,200); assert.match(scoped.body,/dari 1 pengguna/,'Specific private-session aggregate'); assert.ok(!scoped.body.includes('PRIVATE FAMILY TITLE'));
assert.equal((await admin('/kelola/pembelajaran', {q:'100%'})).status, 403, 'CSRF required');
let r = await admin('/kelola/pembelajaran');
r = await admin('/kelola/pembelajaran', {q:'100%', study_csrf_token: token(r.body), mulai:'2026-10-16', sampai:'2026-10-16'});
assert.equal(r.status, 303); const location = r.headers.get('location'); assert.ok(!location.includes('100'), 'Identity query not in URL');
const searchPath = new URL(location).pathname + new URL(location).search;
r = await admin(searchPath); assert.match(r.body, /dari 1 pengguna/); assert.ok(r.body.includes('Anak &lt;uji&gt; 100%'), 'Output escaped');
const ordinary = await login('monitor5'); const editor = await login('monitor6');
for (const request of [ordinary, editor]) for (const path of ['/kelola/pembelajaran', '/kelola/pembelajaran/pengguna/3', '/kelola/pembelajaran/aktivitas/latihan/1', '/kelola/rapor/3', '/learning_monitor/report/3']) assert.equal((await request(path)).status, 403, 'Role boundary '+path);
r = await editor('/kelola'); assert.equal(r.status,200); assert.ok(!r.body.includes('Progres pengguna'), 'Menu permissions match routes');
const parent = await login('monitor2');
r = await parent('/keluarga/anak/3/rapor'); assert.equal(r.status, 200); assert.ok(!r.body.includes('DRAF RAHASIA QA') && !r.body.includes('admin-sidebar'), 'Family report isolation');
assert.equal((await parent('/keluarga/anak/4/rapor')).status, 404, 'Unrelated child denied');
assert.equal((await ordinary('/keluarga/anak/3/rapor')).status, 403);
const database = process.env.STUDY_MONITOR_TEST_DB;
if (database) {
 assert.match(database, /^study_qa_monitor_[a-z0-9]+$/, 'Revocation test requires a disposable database');
 const sql = statement => execFileSync('/www/server/mysql/bin/mysql', ['--defaults-file=/etc/projectpintar/mariadb.cnf', '-uroot', '--batch', '--skip-column-names', database], {input:statement, encoding:'utf8'}).trim();
 assert.equal(sql("SELECT COUNT(*) FROM st_family_links WHERE parent_user_id=2 AND child_user_id=3 AND status='active';"), '1');
 try {
  sql("UPDATE st_family_links SET status='revoked' WHERE parent_user_id=2 AND child_user_id=3;");
  assert.equal((await parent('/keluarga/anak/3/rapor')).status, 404, 'Revoked family link denied in existing session');
 } finally {
  sql("UPDATE st_family_links SET status='active' WHERE parent_user_id=2 AND child_user_id=3;");
 }
 console.log('PASS isolated family-link revocation in existing parent session.');
}
assert.equal((await admin(searchPath)).status, 200);
const secondAdmin = await login('monitor1'); assert.equal((await secondAdmin(searchPath)).status, 400, 'Search context bound to session');
for (const request of [admin,ordinary,editor,parent,secondAdmin]) { const page = await request('/beranda'); assert.equal((await request('/keluar',{study_csrf_token:token(page.body)})).status,303); }
r = await guest('/'); assert.match(r.body, /Project Pintar/); assert.ok(r.body.includes('rel="canonical"')); assert.ok(!r.body.includes('index.php/'));
r = await guest('/sitemap.xml'); assert.equal(r.status,200); assert.ok(r.body.includes('/kursus/kursus-qa') && !r.body.includes('draft-secret'));
assert.equal((await guest('/robots.txt')).status,200);
console.log('PASS isolated HTTP: admin pages, filters/errors, CSRF/search contexts, escaping, ordinary/editor denial, family isolation, logout, clean URLs, public SEO/sitemap.');
