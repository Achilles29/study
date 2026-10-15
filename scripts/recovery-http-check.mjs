import assert from 'node:assert/strict';

const index = process.argv.indexOf('--base-url');
const base = new URL(index >= 0 ? process.argv[index + 1] : 'http://127.0.0.1:8081/index.php/');
assert.ok(base.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(base.hostname) && !base.username && !base.password && !base.search && !base.hash && base.pathname.endsWith('/'), 'Use an HTTP loopback application base URL ending in /');
for (const [method, route, expected] of [['GET', 'qa-recovery-missing-page', 404], ['HEAD', 'qa-recovery-missing-page', 404], ['POST', 'masuk', 403], ['GET', 'qa-recovery-|', 400]]) {
	const response = await fetch(new URL(route, base), { method, redirect: 'manual', signal: AbortSignal.timeout(10000) });
	assert.equal(response.status, expected, `${method} status`);
	assert.match(response.headers.get('cache-control') || '', /no-store/);
	assert.match(response.headers.get('x-robots-tag') || '', /noindex/);
	assert.match(response.headers.get('content-type') || '', /text\/html; charset=utf-8/i);
	const html = await response.text();
	if (method === 'HEAD') assert.equal(html, '');
	else {
		assert.match(html, /<html lang="id">/);
		assert.match(html, /name="viewport"/);
		assert.match(html, /class="skip-link"/);
		assert.match(html, /Kembali ke beranda/);
		assert.doesNotMatch(html, /qa-recovery-missing-page|<script|<form|The action you have requested|404 Page Not Found/);
		if (expected === 403) assert.match(html, /Buka kembali halaman formulir/);
		if (expected === 400) assert.match(html, /Gunakan tautan di aplikasi/);
	}
}
console.log('PASS HTTP recovery pages: real GET/HEAD 404, CSRF POST 403 and invalid URI 400; status, privacy/cache headers, Indonesian standalone markup, no POST replay.');
