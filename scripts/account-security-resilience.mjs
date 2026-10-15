import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, rm } from 'node:fs/promises';
import { createServer } from 'node:net';
import { randomBytes, createHmac } from 'node:crypto';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const arg = name => process.argv[process.argv.indexOf(name) + 1];
const php = arg('--php'), mysql = arg('--mysql'), username = arg('--username'), userId = Number(arg('--user-id'));
const password = process.env.STUDY_QA_SECURITY_PASSWORD;
assert.match(username, /^qa_security_[a-f0-9]{12}_race$/);
assert.ok(Number.isSafeInteger(userId) && userId > 0 && password);
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runFile = promisify(execFile);
const sql = async query => (await runFile(mysql, ['-uroot', '-D', 'db_study', '-N', '-B', '-e', query], { windowsHide: true, timeout: 15000 })).stdout.trim();
assert.equal(await sql(`SELECT COUNT(*) FROM st_users WHERE id=${userId} AND username='${username}' AND account_kind='adult';`), '1');
assert.equal(await sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='information_schema' AND table_name IN ('INNODB_LOCK_WAITS','INNODB_TRX');"), '2');
const originalHash = await sql(`SELECT password_hash FROM st_users WHERE id=${userId};`);
assert.match(originalHash, /^\$2y\$[0-9]{2}\$[./A-Za-z0-9]{53}$/);
const temp = await mkdtemp(path.join(os.tmpdir(), 'study-security-race-'));
const processes = new Set();
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const environment = { ...process.env, CI_ENV: 'development', APP_COOKIE_SECURE: '0', APP_ENCRYPTION_KEY: randomBytes(32).toString('hex'), DB_HOST: '127.0.0.1', DB_USERNAME: 'study_app', DB_PASSWORD: '', DB_DATABASE: 'db_study' };
delete environment.STUDY_QA_SECURITY_PASSWORD;
delete environment.STUDY_QA_SECURITY_NEXT;
const digest = (kind, value) => createHmac('sha256', environment.APP_ENCRYPTION_KEY).update(`${kind}|${value}`).digest('hex');
const failureHash = digest('password-change', userId), loginHash = digest('identity', username), addressHash = digest('password-change-address', '127.0.0.1');
function child(args, env = environment) {
	const process = spawn(php, ['-d', `sys_temp_dir=${temp}`, ...args], { cwd: root, env, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
	process.on('error', () => {}); process.stderr.on('data', () => {}); processes.add(process); return process;
}
const port = () => new Promise((resolve, reject) => { const socket = createServer(); socket.on('error', reject); socket.listen(0, '127.0.0.1', () => { const value = socket.address().port; socket.close(() => resolve(value)); }); });
async function worker() {
	const value = await port(), origin = `http://127.0.0.1:${value}/`;
	const process = child(['-S', `127.0.0.1:${value}`, '-t', root, path.join(root, 'scripts/php-dev-router.php')], { ...environment, APP_BASE_URL: origin });
	process.stdout.on('data', () => {});
	for (let i = 0; i < 100; i++) {
		assert.equal(process.exitCode, null);
		try { if ((await fetch(origin + 'masuk', { signal: AbortSignal.timeout(1000) })).status === 200) return origin; } catch {}
		await delay(50);
	}
	throw new Error('Security worker did not start.');
}
class Client {
	constructor(origin) { this.origin = origin; this.cookies = new Map(); }
	async request(route, body) {
		const response = await fetch(new URL(route, this.origin), { method: body ? 'POST' : 'GET', headers: { Cookie: [...this.cookies].map(([key, value]) => `${key}=${value}`).join('; ') }, body: body ? new URLSearchParams(body) : undefined, redirect: 'manual', signal: AbortSignal.timeout(25000) });
		for (const cookie of response.headers.getSetCookie()) { const pair = cookie.split(';')[0], at = pair.indexOf('='); this.cookies.set(pair.slice(0, at), pair.slice(at + 1)); }
		return { status: response.status, body: await response.text() };
	}
	async login(secret = password) {
		const page = await this.request('masuk');
		assert.ok([302, 303, 307].includes((await this.request('masuk', { identity: username, password: secret, study_csrf_token: token(page) })).status));
		assert.equal((await this.request('akun/keamanan')).status, 200);
	}
}
function token(page) { const match = page.body.match(/name="study_csrf_token"\s+value="([^"]+)"/); assert.ok(match, 'Missing CSRF in security fixture form.'); return match[1]; }
const body = (page, next, current = password) => ({ current_password: current, new_password: next, password_confirm: next, study_csrf_token: token(page) });
const audits = async () => Number(await sql(`SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=${userId} AND event_type='auth.password_changed';`));
async function race(label, actions) {
	const barrier = child(['index.php', 'cli/check_account_security/hold', String(userId)]);
	let output = ''; barrier.stdout.on('data', chunk => { output += chunk; });
	let connection;
	for (let i = 0; i < 100; i++) { const match = output.match(/READY ([0-9]+)/); if (match) { connection = Number(match[1]); break; } assert.equal(barrier.exitCode, null); await delay(50); }
	assert.ok(connection, 'Security barrier not ready.');
	const pending = actions.map(action => action().catch(error => ({ error })));
	let overlapping = false;
	try {
		for (let i = 0; i < 80; i++) {
			const count = Number(await sql(`SELECT COUNT(DISTINCT w.requesting_trx_id) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id<>${connection} AND t.trx_query LIKE '%SELECT id, password_hash FROM st_users%' AND t.trx_query REGEXP 'WHERE id = [^0-9]*${userId}([^0-9]|$)';`));
			if (count >= 2) { overlapping = true; break; } await delay(50);
		}
	} finally { barrier.stdin.end('release\n'); }
	const responses = await Promise.all(pending);
	assert.ok(overlapping, 'Two actual InnoDB waiters were not observed; cannot claim a race test.');
	for (const response of responses) if (response.error) throw response.error;
	console.log(`PASS security race ${label}: two PHP workers waited in InnoDB before release.`);
	return responses;
}
try {
	const [originA, originB] = await Promise.all([worker(), worker()]);
	for (const duplicate of [false, true]) {
		await sql(`UPDATE st_users SET password_hash='${originalHash}' WHERE id=${userId} AND username='${username}';`);
		const a = new Client(originA), b = new Client(originB); await a.login(); await b.login();
		const before = await audits(), first = 'QA-race-new-password-A!', second = duplicate ? first : 'QA-race-new-password-B!';
		const [pageA, pageB] = await Promise.all([a.request('akun/keamanan'), b.request('akun/keamanan')]);
		const result = await race(duplicate ? 'identical replacements' : 'different replacements', [() => a.request('akun/keamanan', body(pageA, first)), () => b.request('akun/keamanan', body(pageB, second))]);
		assert.deepEqual(result.map(item => item.status).sort(), [303, 409]); assert.equal(await audits(), before + 1);
		await new Client(originA).login(result[0].status === 303 ? first : second);
		assert.ok([303, 307].includes((await b.request('beranda')).status));
	}
	await sql(`UPDATE st_users SET password_hash='${originalHash}' WHERE id=${userId} AND username='${username}'; DELETE FROM st_login_failures WHERE identity_hash='${failureHash}';`);
	for (let i = 0; i < 9; i++) await sql(`INSERT INTO st_login_failures (identity_hash,address_hash,attempted_at) VALUES ('${failureHash}','${addressHash}',UTC_TIMESTAMP());`);
	const a = new Client(originA), b = new Client(originB); await a.login(); await b.login();
	const before = await audits(), [pageA, pageB] = await Promise.all([a.request('akun/keamanan'), b.request('akun/keamanan')]);
	const result = await race('last allowed verification', [() => a.request('akun/keamanan', body(pageA, 'QA-valid-next-password!', 'Wrong-secret!')), () => b.request('akun/keamanan', body(pageB, 'QA-valid-next-password!', 'Wrong-secret!'))]);
	assert.deepEqual(result.map(item => item.status).sort(), [400, 429]);
	assert.equal(await sql(`SELECT COUNT(*) FROM st_login_failures WHERE identity_hash='${failureHash}';`), '10');
	assert.equal(await audits(), before); assert.equal(await sql(`SELECT password_hash FROM st_users WHERE id=${userId};`), originalHash);
	await sql(`DELETE FROM st_login_failures WHERE identity_hash='${failureHash}';`);
	const checked = await runFile(php, ['-d', `sys_temp_dir=${temp}`, 'index.php', 'cli/check_account_security/index', String(userId)], { cwd: root, windowsHide: true, timeout: 15000, env: { ...environment, STUDY_QA_SECURITY_PASSWORD: password, STUDY_QA_SECURITY_NEXT: 'QA-audit-rollback-new!' } });
	assert.match(checked.stdout, /PASS real database query failure/); console.log(checked.stdout.trim());
	assert.equal((await a.request('akun/keamanan')).status, 200, 'Rollback invalidated an existing session.');
	console.log('PASS password race outcomes: one change/audit, winning credential valid, no throttle overrun, failed audit preserves old credentials/session.');
} finally {
	await Promise.all([...processes].map(async process => {
		const closed = new Promise(resolve => process.once('close', resolve));
		if (process.exitCode === null && process.signalCode === null) { process.kill('SIGKILL'); await Promise.race([closed, delay(3000)]); }
		assert.ok(process.exitCode !== null || process.signalCode !== null, 'Owned worker did not stop.');
	}));
	await sql(`DELETE FROM st_login_failures WHERE identity_hash IN ('${failureHash}','${loginHash}');`);
	const relative = path.relative(os.tmpdir(), temp);
	assert.ok(relative.startsWith('study-security-race-') && !relative.includes(path.sep) && !path.isAbsolute(relative));
	await rm(temp, { recursive: true, force: false });
	console.log('PASS security workers/barriers and their private session directory stopped/cleaned; fixture accounts/audits remain owned by the parent QA script.');
}
