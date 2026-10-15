import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../assets/js/matching-save.js', import.meta.url), 'utf8');
const { enhanceMatchingForm } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

function setup(fetch) {
	const handlers = {}; const attributes = {}; const fieldsets = [{ disabled: false }, { disabled: false }];
	const button = { disabled: false }; const columns = { hidden: false }; const status = { textContent: '' };
	const recovery = { hidden: true, focus() { this.focused = true; }, addEventListener(name, handler) { this[name] = handler; } }; const navigated = [];
	const form = {
		action: 'http://localhost/index.php/permainan/tersimpan/1#hasil-pilihan', valid: true,
		addEventListener(name, handler) { handlers[name] = handler; }, reportValidity() { return this.valid; },
		querySelectorAll() { return fieldsets; }, querySelector(selector) { return selector === '.matching-columns' ? columns : button; },
		setAttribute(name, value) { attributes[name] = value; }
	};
	const environment = {
		addEventListener(name, handler) { this[name] = handler; },
		location: { href: form.action, origin: 'http://localhost', reload() { navigated.push(this.href); } }, fetch,
		history: { replaceState(state, title, url) { environment.location.href = url; } },
		FormData: class { constructor() { assert.ok(fieldsets.every(field => !field.disabled), 'Capture payload before disabling radios'); this.includesCsrfAndAction = true; } },
		AbortController, setTimeout(handler) { this.timer = handler; return 1; }, clearTimeout(id) { this.cleared = id; }
	};
	assert.equal(enhanceMatchingForm(form, status, recovery, environment), true);
	return { form, handlers, attributes, fieldsets, button, columns, status, recovery, navigated, environment };
}
let finish; const requests = [];
const pending = setup((url, options) => { requests.push({ url, options }); return new Promise(resolve => { finish = resolve; }); });
const event = { preventDefault() {} };
const first = pending.handlers.submit(event);
await pending.handlers.submit(event);
assert.equal(requests.length, 1); assert.equal(pending.attributes['aria-busy'], 'true'); assert.equal(pending.button.disabled, true);
assert.match(pending.status.textContent, /Mengirim/); assert.equal(pending.navigated.length, 0);
assert.equal(requests[0].options.redirect, 'manual'); assert.equal(requests[0].options.credentials, 'same-origin');
assert.equal(requests[0].options.body.includesCsrfAndAction, true);
finish({ type: 'opaqueredirect' }); await first;
assert.deepEqual(pending.navigated, [pending.form.action]); assert.equal(pending.recovery.hidden, true);

for (const code of [400, 403, 409, 500, 503, 200]) {
	const test = setup(async () => ({ status: code, type: 'basic' })); await test.handlers.submit(event);
	assert.equal(test.navigated.length, 0); assert.equal(test.recovery.hidden, false); assert.equal(test.recovery.focused, true);
	assert.equal(test.button.disabled, true); assert.equal(test.attributes['aria-busy'], 'false');
	assert.equal(test.columns.hidden, code === 403); assert.equal(test.environment.cleared, 1);
	assert.doesNotMatch(test.status.textContent, /^Progres tersimpan|^Pilihan tersimpan|berhasil/i);
}
const unavailableRequests = [];
const unavailable = setup(async (url, options) => { unavailableRequests.push({url,options}); return {status:503,type:'basic',text(){throw new Error('503 HTML must not be treated as fresh game state');},json(){throw new Error('503 must not supply an optimistic game state');}}; });
await unavailable.handlers.submit(event);
assert.equal(unavailableRequests.length, 1);
assert.equal(unavailableRequests[0].options.method, 'POST');
assert.match(unavailable.status.textContent, /^Belum dapat memastikan pilihan tersimpan\./);
assert.doesNotMatch(unavailable.status.textContent, /pilihan belum tersimpan|\bpasti\b|\bberhasil\b|^(?:Progres|Pilihan) tersimpan|Cocok!/i);
assert.equal(unavailable.navigated.length, 0, '503 must not automatically navigate, refresh, or retry');
assert.ok(unavailable.fieldsets.every(field => field.disabled), '503 must lock stale revision choices until a fresh GET');
assert.equal(unavailable.button.disabled, true);
assert.equal(unavailable.recovery.hidden, false);
assert.equal(unavailable.recovery.focused, true);
await unavailable.handlers.submit(event);
unavailable.environment.timer();
await Promise.resolve();
assert.equal(unavailableRequests.length, 1, 'Repeated submit or late timeout after 503 must not replay POST');
assert.equal(unavailable.navigated.length, 0, 'Only an explicit recovery action may request fresh state');
unavailable.recovery.click(event);
assert.deepEqual(unavailable.navigated, [unavailable.form.action], 'Explicit recovery requests current GET state');
assert.equal(unavailableRequests.length, 1, 'Recovery click must never repeat POST');
const offline = setup(async () => { throw new TypeError('Network failed'); }); await offline.handlers.submit(event);
assert.match(offline.status.textContent, /mungkin sudah tersimpan/); assert.equal(offline.recovery.hidden, false);
offline.recovery.click(event); assert.deepEqual(offline.navigated, [offline.form.action], 'Recovery must reload even when the fragment URL is unchanged');
const timeout = setup((url, options) => new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('Timeout')))));
const waiting = timeout.handlers.submit(event); timeout.environment.timer(); await waiting;
assert.equal(timeout.recovery.hidden, false); assert.equal(timeout.button.disabled, true);
const invalid = setup(async () => { throw new Error('Must not submit invalid form'); }); invalid.form.valid = false;
await invalid.handlers.submit(event); assert.equal(invalid.button.disabled, false);
assert.equal(enhanceMatchingForm(null, null, null, {}), false);
assert.equal(enhanceMatchingForm(invalid.form, invalid.status, invalid.recovery, { ...invalid.environment, fetch: null }), false);
invalid.form.action = 'https://outside.example/';
assert.equal(enhanceMatchingForm(invalid.form, invalid.status, invalid.recovery, invalid.environment), false);
const restored = setup(async () => { throw new Error('Page restoration must never resend POST'); });
restored.environment.pageshow({ persisted: false }); assert.equal(restored.navigated.length, 0);
restored.environment.pageshow({ persisted: true }); assert.deepEqual(restored.navigated, [restored.form.action]);
restored.environment.pageshow({ persisted: false }); assert.equal(restored.navigated.length, 1, 'Fresh load must not reload in a loop');
console.log('PASS saved matching enhancement: native fallback, one in-flight POST, capture before disable, no optimistic score, explicit 503 uncertainty without automatic retry/navigation, manual redirect GET, status/timeout/network uncertainty, fresh-state recovery, BFCache GET without POST/reload loop, and same-origin guard.');
