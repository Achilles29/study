import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
const source = await readFile(new URL('../assets/js/lesson-progress.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function fixture(fetchImpl) {
	const csrf = { name: 'study_csrf_token', value: 'old-token' }, otherCsrf = { value: 'old-token' };
	const position = { value: '0' }, button = { hidden: true, disabled: false }, completeButton = { disabled: false };
	const recovery = { hidden: true }, status = { textContent: '' }, handlers = {}, timers = new Map(), requests = [];
	let nextTimer = 0, aborts = 0, reloads = 0;
	const form = { action: '/materi/uji/posisi', querySelector: s => s.includes('study_csrf') ? csrf : s.includes('button') ? button : position, addEventListener: (n, h) => { handlers[n] = h; } };
	const complete = { querySelector: () => completeButton, addEventListener: (n, h) => { handlers.complete = h; } };
	const nodes = { 'reading-material': { getAttribute: () => '3500' }, 'save-reading-position': form, 'complete-reading-lesson': complete, 'reading-position-status': status, 'reading-position-recovery': recovery };
	const document = { documentElement: { scrollHeight: 1500 }, getElementById: id => nodes[id] || null, querySelectorAll: () => [csrf, otherCsrf] };
	const window = {
		innerHeight: 500, scrollY: 700, location: { search: '', reload() { reloads++; } }, URLSearchParams,
		addEventListener: (n, h) => { handlers[n] = h; },
		setTimeout: (callback, delay) => { timers.set(++nextTimer, { callback, delay }); return nextTimer; }, clearTimeout: id => timers.delete(id),
		AbortController: class { signal = {}; abort() { aborts++; } },
		fetch: (url, options) => { requests.push({ url, options }); return fetchImpl ? fetchImpl() : Promise.resolve(success()); }
	};
	vm.runInNewContext(source, { document, window, URLSearchParams });
	function fire(delay) { const e = [...timers].find(([, t]) => t.delay === delay); assert.ok(e, `missing timer ${delay}`); timers.delete(e[0]); e[1].callback(); }
	function scroll(value) { window.scrollY = value; handlers.scroll(); if ([...timers.values()].some(t => t.delay === 1200)) fire(1200); }
	function submit(kind = 'submit') { let prevented = false; handlers[kind]({ preventDefault: () => { prevented = true; } }); return prevented; }
	return { csrf, otherCsrf, button, completeButton, recovery, status, timers, requests, fire, scroll, submit, handlers, reloads: () => reloads, aborts: () => aborts };
}
function success() { return { ok: true, json: () => Promise.resolve({ ok: true, csrf_hash: 'rotated-token' }) }; }
let f = fixture();
f.scroll(700); await flush();
assert.equal(f.otherCsrf.value, 'rotated-token', 'all page form tokens rotate after success');
assert.equal(f.timers.size, 0);
assert.equal(f.submit(), false);
f.scroll(900); assert.equal(f.requests.length, 1, 'native submit prevents new autosaves');
let resolve;
f = fixture(() => new Promise(done => { resolve = done; }));
f.scroll(700); f.scroll(850);
assert.equal(f.requests.length, 1);
assert.equal(f.submit(), true); assert.equal(f.submit('complete'), true);
resolve(success()); await flush();
assert.equal(f.requests.length, 2);
assert.match(f.requests[1].options.body, /position=8500/);
assert.match(f.requests[1].options.body, /study_csrf_token=rotated-token/);
resolve(success()); await flush();
for (const failure of ['503', '403', 'network', 'json', 'false-ok', 'missing-token', 'null']) {
	let reject;
	f = fixture(() => new Promise((done, fail) => { resolve = done; reject = fail; }));
	f.scroll(700); f.scroll(900);
	if (failure === 'network') reject(new Error('offline'));
	else if (failure === '503' || failure === '403') resolve({ ok: false, json() { throw new Error('Error response must not be read'); } });
	else if (failure === 'json') resolve({ ok: true, json: () => Promise.reject(new Error('invalid JSON')) });
	else resolve({ ok: true, json: () => Promise.resolve(failure === 'null' ? null : { ok: failure !== 'false-ok', csrf_hash: failure === 'missing-token' ? '' : 'untrusted' }) });
	await flush();
	assert.equal(f.requests.length, 1, `${failure}: no queued retry`);
	assert.equal(f.csrf.value, 'old-token');
	assert.equal(f.button.disabled, true); assert.equal(f.completeButton.disabled, true);
	assert.equal(f.recovery.hidden, false); assert.match(f.status.textContent, /belum dapat dipastikan/);
	assert.equal(f.submit(), true); assert.equal(f.submit('complete'), true);
	f.scroll(950); await flush(); assert.equal(f.requests.length, 1); assert.equal(f.timers.size, 0);
}
f = fixture(() => new Promise(done => { resolve = done; }));
f.scroll(700); f.scroll(900); f.fire(20000);
assert.equal(f.aborts(), 1); assert.equal(f.recovery.hidden, false); assert.equal(f.submit(), true);
resolve(success()); await flush();
assert.equal(f.requests.length, 1, 'late success cannot restart queue');
assert.equal(f.csrf.value, 'old-token'); assert.match(f.status.textContent, /belum dapat dipastikan/);
f.handlers.pageshow({ persisted:false }); assert.equal(f.reloads(), 0);
f.handlers.pageshow({ persisted:true }); assert.equal(f.reloads(), 1);
console.log('PASS sequential autosave, all-form CSRF, seven failure classes, queue/manual/completion lock, timeout and late success. VM only, not network/browser integration.');
