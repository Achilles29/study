import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const argument = (name, fallback = '') => { const index = process.argv.indexOf(name); return index < 0 ? fallback : process.argv[index + 1]; };
const nginx = argument('--nginx');
const phpCgi = argument('--php-cgi');
const project = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const template = await readFile(path.join(project, 'docs/nginx-study.example.conf'), 'utf8');
const imageStore = await readFile(path.join(project, 'application/libraries/Lesson_image_store.php'), 'utf8');
const maxImageBytes = Number(imageStore.match(/const MAX_UPLOAD_BYTES = ([0-9]+);/)?.[1]);
assert.ok(Number.isSafeInteger(maxImageBytes) && maxImageBytes > 0, 'Image upload limit must be explicit');
const assertUploadHeadroom = config => {
	const limits = [...config.matchAll(/^\s*client_max_body_size\s+([0-9]+)([kKmM]?)\s*;/gm)];
	assert.equal(limits.length, 1, 'Template must have one explicit request-body limit');
	const units = { '': 1, k: 1024, m: 1048576 };
	const bodyBytes = Number(limits[0][1]) * units[limits[0][2].toLowerCase()];
	assert.ok(bodyBytes >= maxImageBytes + 1048576, 'Nginx body limit needs at least 1 MiB multipart headroom above the app image cap');
};
assertUploadHeadroom(template);
assert.throws(() => assertUploadHeadroom(template.replace(/client_max_body_size\s+[^;]+;/, 'client_max_body_size 2m;')), /multipart headroom/, 'Legacy 2m limit must be rejected');
if (process.argv.includes('--check-rules')) {
	const pattern = template.match(/location ~ (\S+) \{/);
	assert.ok(pattern, 'Hidden-file rule missing');
	const hidden = new RegExp(pattern[1]);
	for (const allowed of ['/', '/masuk', '/index.php/masuk', '/assets/css/app.css', '/.well-known/acme-challenge/token']) assert.equal(hidden.test(allowed), false, `Hidden-file rule overmatches ${allowed}`);
	for (const denied of ['/.env', '/.git/config', '/assets/.hidden.css', '/folder/.well-known-other/private']) assert.equal(hidden.test(denied), true, `Hidden-file rule misses ${denied}`);
	assert.ok(template.includes('location = /index.php {'), 'Only the exact front controller should reach FastCGI');
	console.log('PASS static Nginx rule regression: hidden-file regex, exact front controller, image multipart headroom and legacy 2m rejection. Not a substitute for nginx -t/HTTP or PHP SAPI limits.');
	process.exit(0);
}
if (!nginx || !phpCgi || process.platform !== 'win32') throw new Error('This isolated check requires Windows --nginx and --php-cgi paths. It is not Linux/FPM validation.');
const temporary = await mkdtemp(path.join(os.tmpdir(), 'study-nginx-qa-'));
const slash = value => value.replaceAll('\\', '/');
const children = new Set();
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const freePort = async () => {
	const server = net.createServer();
	await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
	const port = server.address().port;
	await new Promise(resolve => server.close(resolve));
	return port;
};
const run = (binary, args, options = {}) => new Promise((resolve, reject) => {
	const { echo = false, ...spawnOptions } = options;
	const process = spawn(binary, args, { windowsHide: true, cwd: project, ...spawnOptions });
	let output = '';
	process.stdout?.on('data', chunk => { output += chunk; if (echo) globalThis.process.stdout.write(chunk); });
	process.stderr?.on('data', chunk => { output += chunk; if (echo) globalThis.process.stderr.write(chunk); });
	process.once('error', reject);
	process.once('exit', code => { if (code === 0) resolve(output); else reject(new Error(`Tool exited ${code}: ${output.slice(-2500)}`)); });
});
const launch = (binary, args, options = {}) => {
	const child = spawn(binary, args, { windowsHide: true, cwd: project, stdio: 'ignore', ...options });
	children.add(child);
	child.once('error', error => { child.launchError = error; });
	return child;
};
const stop = async child => {
	if (child.exitCode === null && child.pid) {
		// Kill only this test's process and descendants, never all nginx/PHP processes.
		await run('taskkill.exe', ['/PID', String(child.pid), '/T', '/F']).catch(() => {});
		for (let i = 0; i < 50 && child.exitCode === null; i++) await delay(100);
		if (child.exitCode === null) throw new Error('A test-owned process did not stop. Temporary files retained.');
	}
	children.delete(child);
};
const waitPort = async (port, child) => {
	for (let attempt = 0; attempt < 100; attempt++) {
		if (child.launchError || child.exitCode !== null) throw new Error('Test server failed to start.');
		const ready = await new Promise(resolve => {
			const socket = net.createConnection({ host: '127.0.0.1', port });
			socket.setTimeout(200);
			socket.once('connect', () => { socket.destroy(); resolve(true); });
			socket.once('error', () => resolve(false));
			socket.once('timeout', () => { socket.destroy(); resolve(false); });
		});
		if (ready) return;
		await delay(100);
	}
	throw new Error('Timed out waiting for isolated server.');
};
const cgiPort = await freePort();
const httpPort = await freePort();
assert.notEqual(cgiPort, httpPort);
const base = `http://127.0.0.1:${httpPort}/`;
const key = randomBytes(32).toString('hex');
const environment = { ...process.env, CI_ENV: 'development', APP_BASE_URL: base, APP_ENCRYPTION_KEY: key, APP_COOKIE_SECURE: '0', DB_HOST: '127.0.0.1', DB_USERNAME: 'study_app', DB_PASSWORD: '', DB_DATABASE: 'db_study', PHP_FCGI_MAX_REQUESTS: '0' };
try {
	const sessionDirectory = path.join(temporary, 'sessions');
	await mkdir(sessionDirectory);
	const cgi = launch(phpCgi, ['-d', 'cgi.force_redirect=0', '-d', `sys_temp_dir=${sessionDirectory}`, '-b', `127.0.0.1:${cgiPort}`], { env: environment });
	await waitPort(cgiPort, cgi);
	for (const legacy of [true, false]) {
		const prefix = path.join(temporary, legacy ? 'legacy' : 'current');
		await mkdir(path.join(prefix, 'logs'), { recursive: true });
		await mkdir(path.join(prefix, 'temp'), { recursive: true });
		let server = template.replace('listen 443 ssl;', `listen 127.0.0.1:${httpPort};`)
			.replace('server_name study.example.com;', 'server_name localhost;')
			.replace('root /var/www/study;', `root "${slash(project)}";`)
			.replace(/^\s*ssl_certificate(?:_key)?\s+[^;]+;$/gm, '')
			.replace('include fastcgi_params;', `include "${slash(path.join(path.dirname(nginx), 'conf/fastcgi_params'))}";`)
			.replace('fastcgi_param HTTPS on;', 'fastcgi_param HTTPS off;')
			.replace('fastcgi_param CI_ENV production;', 'fastcgi_param CI_ENV development;')
			.replace('fastcgi_pass unix:/run/php/php8.1-fpm.sock;', `fastcgi_pass 127.0.0.1:${cgiPort};`);
		if (legacy) {
			assert.ok(server.includes('location ~ /\\.(?!well-known'));
			server = server.replace('location ~ /\\.(?!well-known', 'location ~ /.(?!well-known');
		}
		const config = `worker_processes 1;\ndaemon off;\nerror_log logs/error.log warn;\npid logs/nginx.pid;\nevents { worker_connections 128; }\nhttp { include "${slash(path.join(path.dirname(nginx), 'conf/mime.types'))}"; access_log off; ${server}\n}\n`;
		await writeFile(path.join(prefix, 'qa.conf'), config, { flag: 'wx' });
		const command = ['-p', slash(prefix) + '/', '-c', 'qa.conf'];
		await run(nginx, [...command, '-t']);
		const web = launch(nginx, command, { env: environment });
		await waitPort(httpPort, web);
		try {
			if (legacy) {
				const response = await fetch(base + 'masuk');
				assert.equal(response.status, 404, 'Legacy broad dot rule must reproduce blocking a public route');
				console.log('PASS reproduced legacy dot-rule bug: /masuk returns 404.');
				continue;
			}
			for (const route of ['', 'index.php', 'masuk', 'index.php/masuk', 'kursus', 'index.php/kursus?q=nginx-fixture']) {
				const response = await fetch(base + route);
				assert.equal(response.status, 200, `Public route ${route}`);
				const html = await response.text();
				assert.ok(html.includes('<!doctype html>') && !html.includes('Severity:') && !html.includes('Database Error'), `Application rendering ${route}`);
				if (route.includes('masuk')) assert.ok(html.includes('name="identity"'), 'PATH_INFO route must render sign-in, not home');
			}
			for (const asset of ['assets/css/app.css', 'assets/js/matching-save.js', 'assets/vendor/bootstrap/css/bootstrap.min.css']) {
				const response = await fetch(base + asset);
				assert.equal(response.status, 200, asset);
				assert.equal(response.headers.get('x-content-type-options'), 'nosniff');
				assert.equal(response.headers.get('x-frame-options'), 'DENY');
				assert.equal(response.headers.get('referrer-policy'), 'strict-origin-when-cross-origin');
				assert.match(response.headers.get('content-type'), asset.endsWith('.js') ? /javascript/ : /text\/css/);
			}
			for (const denied of ['.env', '.git/config', 'assets/.hidden.css', 'assets/%2eenv', 'application/config/config.php', 'APPLICATION/config/database.php', 'system/core/CodeIgniter.php', 'docs/nginx-study.example.conf', 'scripts/nginx-config-check.mjs', 'node_modules/bootstrap/package.json', 'vendor/autoload.php', 'composer.json', 'package-lock.json', 'phpcs.xml.dist', 'license.txt', 'not-index.php', 'not-index.php/masuk', 'assets/missing.css']) {
				assert.equal((await fetch(base + denied)).status, 404, `Private or missing file ${denied}`);
			}
			console.log('PASS current Nginx config: syntax, public routes with/without index.php, real PHP 8.1 FastCGI, static MIME/headers, private paths and non-front-controller PHP denied.');
			await run(process.execPath, [path.join(project, 'scripts/recovery-http-check.mjs'), '--base-url', base + 'index.php/'], { echo: true });
			if (process.argv.includes('--run-qa')) {
				const args = ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', path.join(project, 'scripts/qa-local.ps1'), '-Php80Path', argument('--php80', 'C:\\xampp\\php\\php.exe'), '-Php81Path', argument('--php81', 'C:\\tools\\php81-study\\php.exe'), '-Php85Path', argument('--php85', 'C:\\tools\\php85-study\\php.exe'), '-MysqlPath', argument('--mysql', 'C:\\xampp\\mysql\\bin\\mysql.exe'), '-BaseUrl', base + 'index.php/', '-AppEncryptionKey', key];
				await run('powershell.exe', args, { env: environment, echo: true });
			}
		} finally { await stop(web); }
	}
} finally {
	for (const child of children) await stop(child);
	const relative = path.relative(os.tmpdir(), temporary);
	if (!relative || relative.includes(path.sep) || !relative.startsWith('study-nginx-qa-')) throw new Error('Unsafe temporary cleanup path');
	await rm(temporary, { recursive: true, force: true });
	console.log('PASS stopped only isolated Nginx/PHP-CGI processes and removed their temporary config/log/session directory.');
}
