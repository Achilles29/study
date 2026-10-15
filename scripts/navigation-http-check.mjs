import assert from 'node:assert/strict';

const index = process.argv.indexOf('--base-url');
const base = new URL(index < 0 ? 'http://localhost/study/index.php/' : process.argv[index + 1]);
assert(base.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(base.hostname) && !base.username && !base.password && !base.search && !base.hash && base.pathname.endsWith('/'), 'Use an HTTP loopback base URL ending in / without credentials, query or fragment.');
const cases = [
	['', ''], ['home/index', ''], ['kursus', 'kursus'], ['catalog/index', 'kursus'],
	['kursus?q=kelola&halaman=1', 'kursus'], ['masuk', 'masuk'], ['auth/sign_in', 'masuk'],
	['daftar', 'daftar'], ['auth/sign_up', 'daftar'], ['masuk?next=kelola', 'masuk']
];
for (const [route, target] of cases) {
	const response = await fetch(new URL(route, base), { redirect: 'manual', signal: AbortSignal.timeout(10000) });
	assert.equal(response.status, 200, `Public page status: ${route}`);
	const html = await response.text();
	const header = html.match(/<header class="site-header">([\s\S]*?)<\/header>/)?.[1];
	assert(header, `Shared header missing: ${route}`);
	assert(header.includes('class="navbar-collapse" id="navigasi-utama"'), `Server menu is not an accessible fallback: ${route}`);
	assert(/<button class="navbar-toggler"[^>]* hidden>/.test(header), `Inactive toggle must be hidden: ${route}`);
	assert(/assets\/js\/navigation\.js\?v=\d+/.test(html), `Versioned navigation asset missing: ${route}`);
	const current = [...header.matchAll(/<a\b[^>]*aria-current="([^"]*)"[^>]*>/g)];
	assert.equal(current.length, 1, `Expected one current menu: ${route}`);
	assert.equal(current[0][1], 'page', `Current page semantics: ${route}`);
	assert(current[0][0].includes(`href="${new URL(target, base).href}"`), `Current link destination: ${route}`);
	assert(!header.includes('>Panel pengelola</a>') && !header.includes('>Akun anak</a>'), 'Private navigation exposed to anonymous visitor');
}
console.log('PASS 10 public navigation HTTP cases: aliases/direct controllers, exact current link, query-independent state, private menu exclusion.');
