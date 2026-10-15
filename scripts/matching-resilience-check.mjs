import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtemp, rm } from 'node:fs/promises';
import { createServer, request as httpRequest } from 'node:http';
import { createServer as tcpServer } from 'node:net';
import { randomBytes } from 'node:crypto';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const argument = (name) => process.argv[process.argv.indexOf(name) + 1];
const php = argument('--php');
const mysql = argument('--mysql');
const learner = argument('--learner');
const userId = Number(argument('--user-id'));
const lessonId = Number(argument('--lesson-id'));
const slug = argument('--slug');
const password = process.env.STUDY_QA_RACE_PASSWORD;
assert.match(learner, /^qa_matchingrun_[a-f0-9]{12}$/);
assert.match(slug, /^qa-matching-run-[a-f0-9]{12}$/);
assert.ok(Number.isSafeInteger(userId) && userId > 0 && Number.isSafeInteger(lessonId) && lessonId > 0 && password);
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runFile = promisify(execFile);
const sql = async (query) => (await runFile(mysql, ['-uroot', '-D', 'db_study', '-N', '-B', '-e', query], { windowsHide: true, timeout: 15000 })).stdout.trim();
assert.equal(await sql(`SELECT COUNT(*) FROM st_users u JOIN st_courses c ON c.author_user_id=u.id JOIN st_course_sections s ON s.course_id=c.id JOIN st_lessons l ON l.section_id=s.id WHERE u.id=${userId} AND u.username='${learner}' AND l.id=${lessonId} AND l.slug='${slug}';`), '1');
assert.equal(await sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='information_schema' AND table_name IN ('INNODB_LOCK_WAITS','INNODB_TRX');"), '2', 'This local race verifier requires MariaDB/InnoDB lock-wait instrumentation.');
const temporary = await mkdtemp(path.join(os.tmpdir(), 'study-matching-race-'));
const processes = new Set();
const servers = new Set();
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const environment = { ...process.env, CI_ENV: 'development', APP_COOKIE_SECURE: '0', APP_ENCRYPTION_KEY: randomBytes(32).toString('hex'), DB_HOST: '127.0.0.1', DB_USERNAME: 'study_app', DB_PASSWORD: '', DB_DATABASE: 'db_study' };
delete environment.STUDY_QA_RACE_PASSWORD;
const startProcess = (args, env = environment) => {
	const child = spawn(php, ['-d', `sys_temp_dir=${temporary}`, ...args], { cwd: root, env, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
	child.stderr.on('data', () => {});
	child.on('error', () => {});
	processes.add(child);
	return child;
};
const freePort = () => new Promise((resolve, reject) => {
	const probe = tcpServer(); probe.on('error', reject);
	probe.listen(0, '127.0.0.1', () => { const port = probe.address().port; probe.close(() => resolve(port)); });
});
async function startWorker() {
	const port = await freePort();
	const origin = `http://127.0.0.1:${port}/`;
	const child = startProcess(['-S', `127.0.0.1:${port}`, '-t', root, path.join(root, 'scripts/php-dev-router.php')], { ...environment, APP_BASE_URL: origin });
	child.stdout.on('data', () => {});
	for (let attempt = 0; attempt < 100; attempt++) {
		assert.equal(child.exitCode, null, 'Isolated HTTP worker exited early.');
		try { if ((await fetch(`${origin}index.php/masuk`, { signal: AbortSignal.timeout(1000) })).status === 200) return origin; } catch {}
		await delay(50);
	}
	throw new Error('Isolated HTTP worker did not start.');
}
class Client {
	constructor(origin) { this.origin = origin; this.cookies = new Map(); }
	async request(route, body, origin = this.origin) {
		const response = await fetch(new URL(`index.php/${route}`, origin), { method: body ? 'POST' : 'GET', headers: { Cookie: [...this.cookies].map(([key, value]) => `${key}=${value}`).join('; ') }, body: body ? new URLSearchParams(body) : undefined, redirect: 'manual', signal: AbortSignal.timeout(25000) });
		for (const cookie of response.headers.getSetCookie()) { const [entry] = cookie.split(';'); const split = entry.indexOf('='); this.cookies.set(entry.slice(0, split), entry.slice(split + 1)); }
		return { status: response.status, body: await response.text(), location: response.headers.get('location') };
	}
	async login() {
		const page = await this.request('masuk');
		assert.ok([302, 303, 307].includes((await this.request('masuk', { identity: learner, password, study_csrf_token: hidden(page, 'study_csrf_token') })).status));
		assert.equal((await this.request('permainan/riwayat')).status, 200);
	}
}
function hidden(page, name) {
	const match = page.body.match(new RegExp(`name="${name}"\\s+value="([^"]*)"`));
	assert.ok(match, `Missing ${name} in fixture form (HTTP ${page.status}).`);
	return match[1];
}
const requestBody = (page, rest) => ({ study_csrf_token: hidden(page, 'study_csrf_token'), action_token: hidden(page, 'action_token'), ...rest });
const runPath = (id) => `permainan/tersimpan/${id}`;
const startPath = `permainan/tersimpan/pelajaran/${slug}`;
const state = async (id) => JSON.parse(await sql(`SELECT state_json FROM st_matching_runs WHERE id=${id} AND learner_user_id=${userId};`));
const revision = async (id) => Number(await sql(`SELECT revision FROM st_matching_runs WHERE id=${id} AND learner_user_id=${userId};`));
const active = async () => Number(await sql(`SELECT id FROM st_matching_runs WHERE learner_user_id=${userId} AND lesson_id=${lessonId} AND status='in_progress';`));
async function stop(client, id) {
	const route = `${runPath(id)}/berhenti`;
	const page = await client.request(route);
	assert.equal((await client.request(route, requestBody(page, { revision: hidden(page, 'revision'), confirm: '1' }))).status, 303);
}
async function start(client) {
	const page = await client.request(startPath);
	assert.equal((await client.request(startPath, requestBody(page, {}))).status, 303);
	const id = await active(); assert.ok(id > 0); return id;
}
async function barrier() {
	const child = startProcess(['index.php', 'cli/check_matching/hold', String(lessonId), String(userId)]);
	let output = ''; child.stdout.on('data', chunk => { output += chunk; });
	for (let attempt = 0; attempt < 100; attempt++) {
		const match = output.match(/READY ([0-9]+)/);
		if (match) return { child, connection: Number(match[1]) };
		assert.equal(child.exitCode, null, 'Lock barrier failed before becoming ready.');
		await delay(50);
	}
	throw new Error('Lock barrier did not start.');
}
async function race(label, requests) {
	const lock = await barrier();
	const pending = requests.map(request => request().catch(error => ({ error })));
	let overlap = false;
	try {
		for (let attempt = 0; attempt < 80; attempt++) {
			// Count both waiting workers, including a worker blocked by the other worker's join locks.
			const count = Number(await sql(`SELECT COUNT(DISTINCT w.requesting_trx_id) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id<>${lock.connection} AND t.trx_query LIKE '%FROM st_lessons l JOIN st_course_sections%' AND t.trx_query REGEXP 'WHERE l.id=[^0-9]*${lessonId}([^0-9]|$)';`));
			if (count >= 2) { overlap = true; break; }
			await delay(50);
		}
	} finally { lock.child.stdin.end('release\n'); }
	const responses = await Promise.all(pending);
	assert.ok(overlap, `${label}: two overlapping database waiters were not observed; do not claim a concurrency pass.`);
	for (const response of responses) { if (response.error) throw response.error; }
	console.log(`PASS simultaneous HTTP ${label}: two independent PHP 8.1 workers observed waiting in InnoDB before release.`);
	return responses;
}
async function lostTransport(client, route, body, forward) {
	let backendCommittedResponse = false;
	const proxy = createServer((incoming, outgoing) => {
		if (!forward) { incoming.resume(); outgoing.destroy(); return; }
		const target = new URL(incoming.url, client.origin);
		const upstream = httpRequest(target, { method: incoming.method, headers: { ...incoming.headers, host: target.host } }, response => {
			response.resume();
			response.on('end', () => { backendCommittedResponse = response.statusCode === 303; outgoing.destroy(); });
		});
		upstream.on('error', () => outgoing.destroy()); incoming.pipe(upstream);
	});
	servers.add(proxy);
	await new Promise(resolve => proxy.listen(0, '127.0.0.1', resolve));
	let failed = false;
	try { await client.request(route, body, `http://127.0.0.1:${proxy.address().port}/`); } catch { failed = true; }
	proxy.closeAllConnections(); await new Promise(resolve => proxy.close(resolve)); servers.delete(proxy);
	assert.ok(failed, 'Fault proxy unexpectedly returned a successful client response.');
	assert.equal(backendCommittedResponse, forward, 'Fault did not occur at the intended side of the commit boundary.');
}

try {
	const [originA, originB] = await Promise.all([startWorker(), startWorker()]);
	const a = new Client(originA); const b = new Client(originB);
	await Promise.all([a.login(), b.login()]);
	const existing = await active(); if (existing) await stop(a, existing);
	const beforeRuns = Number(await sql(`SELECT COUNT(*) FROM st_matching_runs WHERE learner_user_id=${userId};`));
	const [startA, startB] = await Promise.all([a.request(startPath), b.request(startPath)]);
	const starts = await race('duplicate start', [() => a.request(startPath, requestBody(startA, {})), () => b.request(startPath, requestBody(startB, { action_token: hidden(startA, 'action_token') }))]);
	assert.deepEqual(starts.map(response => response.status), [303, 303]);
	const id = await active(); assert.ok(id > 0);
	assert.equal(Number(await sql(`SELECT COUNT(*) FROM st_matching_runs WHERE learner_user_id=${userId};`)), beforeRuns + 1);
	assert.ok(starts.every(response => response.location.endsWith(runPath(id))));
	assert.equal(await sql(`SELECT COUNT(*) FROM st_audit_events WHERE entity_type='matching_run' AND entity_id=${id} AND event_type='matching.started';`), '1');
	let [pageA, pageB] = await Promise.all([a.request(runPath(id)), b.request(runPath(id))]);
	let pair = pageA.body.match(/name="left" value="([0-9]+)"/)[1];
	let body = requestBody(pageA, { revision: hidden(pageA, 'revision'), left: pair, right: pair });
	const duplicates = await race('duplicate choice', [() => a.request(runPath(id), body), () => b.request(runPath(id), { ...body, study_csrf_token: hidden(pageB, 'study_csrf_token') })]);
	assert.deepEqual(duplicates.map(response => response.status), [303, 303]); assert.equal(await revision(id), 1);
	[pageA, pageB] = await Promise.all([a.request(runPath(id)), b.request(runPath(id))]);
	pair = pageA.body.match(/name="left" value="([0-9]+)"/)[1];
	const conflicts = await race('different keys on one revision', [() => a.request(runPath(id), requestBody(pageA, { revision: '1', left: pair, right: pair })), () => b.request(runPath(id), requestBody(pageB, { revision: '1', left: pair, right: pair }))]);
	assert.deepEqual(conflicts.map(response => response.status).sort(), [303, 409]); assert.equal(await revision(id), 2);
	while ((await state(id)).matched.length < 7) {
		pageA = await a.request(runPath(id)); pair = pageA.body.match(/name="left" value="([0-9]+)"/)[1];
		assert.equal((await a.request(runPath(id), requestBody(pageA, { revision: hidden(pageA, 'revision'), left: pair, right: pair }))).status, 303);
	}
	[pageA, pageB] = await Promise.all([a.request(runPath(id)), b.request(runPath(id))]);
	pair = pageA.body.match(/name="left" value="([0-9]+)"/)[1];
	body = requestBody(pageA, { revision: '7', left: pair, right: pair });
	const finals = await race('duplicate final pair', [() => a.request(runPath(id), body), () => b.request(runPath(id), { ...body, study_csrf_token: hidden(pageB, 'study_csrf_token') })]);
	assert.deepEqual(finals.map(response => response.status), [303, 303]); assert.equal(await revision(id), 8);
	assert.equal(await sql(`SELECT status FROM st_matching_runs WHERE id=${id};`), 'completed');
	assert.equal(await sql(`SELECT COUNT(*) FROM st_matching_moves WHERE run_id=${id};`), '8');
	assert.equal(await sql(`SELECT COUNT(*) FROM st_audit_events WHERE entity_type='matching_run' AND entity_id=${id} AND event_type='matching.completed';`), '1');
	const [differentStartA, differentStartB] = await Promise.all([a.request(startPath), b.request(startPath)]);
	const differentStarts = await race('different start keys', [() => a.request(startPath, requestBody(differentStartA, {})), () => b.request(startPath, requestBody(differentStartB, {}))]);
	assert.deepEqual(differentStarts.map(response => response.status).sort(), [303, 409]);
	assert.equal(Number(await sql(`SELECT COUNT(*) FROM st_matching_runs WHERE learner_user_id=${userId};`)), beforeRuns + 2);
	const competingId = await active(); assert.ok(competingId > 0);
	[pageA, pageB] = await Promise.all([a.request(runPath(competingId)), b.request(`${runPath(competingId)}/berhenti`)]);
	pair = pageA.body.match(/name="left" value="([0-9]+)"/)[1];
	const competing = await race('stop against choice', [() => a.request(runPath(competingId), requestBody(pageA, { revision: '0', left: pair, right: pair })), () => b.request(`${runPath(competingId)}/berhenti`, requestBody(pageB, { revision: '0', confirm: '1' }))]);
	assert.deepEqual(competing.map(response => response.status).sort(), [303, 409]); assert.equal(await revision(competingId), 1);
	const competingStatus = await sql(`SELECT status FROM st_matching_runs WHERE id=${competingId};`);
	assert.ok(['in_progress', 'abandoned'].includes(competingStatus));
	assert.equal(Number(await sql(`SELECT COUNT(*) FROM st_matching_moves WHERE run_id=${competingId};`)), competingStatus === 'in_progress' ? 1 : 0);
	if (competingStatus === 'in_progress') await stop(a, competingId);
	const recoveryId = await start(a);
	pageA = await a.request(runPath(recoveryId)); pair = pageA.body.match(/name="left" value="([0-9]+)"/)[1];
	body = requestBody(pageA, { revision: '0', left: pair, right: pair });
	await lostTransport(a, runPath(recoveryId), body, false); assert.equal(await revision(recoveryId), 0);
	await lostTransport(a, runPath(recoveryId), body, true); assert.equal(await revision(recoveryId), 1);
	pageB = await b.request(runPath(recoveryId)); assert.equal(hidden(pageB, 'revision'), '1');
	assert.equal((await b.request(runPath(recoveryId), { ...body, study_csrf_token: hidden(pageB, 'study_csrf_token') })).status, 303);
	assert.equal(await revision(recoveryId), 1); assert.equal(await sql(`SELECT COUNT(*) FROM st_matching_moves WHERE run_id=${recoveryId};`), '1');
	await stop(b, recoveryId);
	assert.equal(await sql(`SELECT COUNT(*) FROM st_wallet_entries WHERE actor_user_id=${userId};`), '0');
	console.log('PASS transport recovery: dropped request before forwarding leaves no write; response dropped after backend 303 preserves one choice; GET resumes and identical retry does not duplicate. No credits written.');
} finally {
	for (const server of servers) { server.closeAllConnections(); server.close(); }
	await Promise.all([...processes].map(async child => {
		if (child.exitCode !== null || child.signalCode !== null) return;
		const closed = new Promise(resolve => child.once('exit', resolve)); child.kill();
		await Promise.race([closed, delay(3000)]);
		if (child.exitCode === null && child.signalCode === null) { child.kill('SIGKILL'); await Promise.race([closed, delay(3000)]); }
		assert.ok(child.exitCode !== null || child.signalCode !== null, 'Test-owned PHP process did not terminate.');
	}));
	const relative = path.relative(os.tmpdir(), temporary);
	assert.ok(relative.startsWith('study-matching-race-') && !relative.includes(path.sep) && !path.isAbsolute(relative), 'Refuse unsafe temporary-directory removal.');
	await rm(temporary, { recursive: true, force: false });
	console.log('PASS stopped only test-owned HTTP workers/barriers/proxy and removed their isolated temporary session directory. Database fixture cleanup remains with the PowerShell owner.');
}
