import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../assets/js/lesson-progress.js', import.meta.url), 'utf8');
const positionInput = { value: '0' };
const csrfInput = { name: 'study_csrf_token', value: 'old-token' };
const saveButton = { hidden: true };
const handlers = {};
const scrollCalls = [];
const timers = [];
const fetchCalls = [];
const readingMaterial = { getAttribute: (name) => name === 'data-saved-position' ? '3500' : null };
const saveForm = {
	querySelector(selector) {
		if (selector === 'input[name="position"]') return positionInput;
		if (selector === 'input[name="study_csrf_token"]') return csrfInput;
		if (selector === 'button[type="submit"]') return saveButton;
		return null;
	},
	action: '/materi/uji/posisi',
	addEventListener(name, handler) { handlers[name] = handler; }
};
const status = { textContent: '' };
const document = {
	querySelectorAll() { return [csrfInput]; },
	documentElement: { scrollHeight: 1500 },
	getElementById(id) {
		if (id === 'reading-material') return readingMaterial;
		if (id === 'save-reading-position') return saveForm;
		if (id === 'reading-position-status') return status;
		return null;
	}
};
const window = {
	innerHeight: 500,
	scrollY: 700,
	location: { search: '?lanjut=1' },
	matchMedia: () => ({ matches: true }),
	scrollTo: (options) => scrollCalls.push(options),
	URLSearchParams,
	addEventListener(name, handler) { handlers[name] = handler; },
	setTimeout(handler) { timers.push(handler); return timers.length; },
	clearTimeout() {},
	fetch(url, options) {
		fetchCalls.push({ url, options });
		return Promise.resolve({ ok: true, json: () => Promise.resolve({ ok: true, csrf_hash: 'rotated-token' }) });
	}
};

vm.runInNewContext(source, { document, window, URLSearchParams });
assert.equal(saveButton.hidden, false, 'save action should appear after the script initializes');
assert.equal(scrollCalls.length, 1, 'resume should restore the previously saved position');
assert.equal(scrollCalls[0].top, 350, '35% resume should scale to the current document height');
assert.equal(scrollCalls[0].behavior, 'auto', 'reduced-motion preference should disable smooth scrolling');
assert.equal(typeof handlers.submit, 'function', 'save handler should be registered');
handlers.scroll();
assert.equal(timers.length, 1, 'scroll should schedule a debounced autosave');
timers[0]();
await new Promise((resolve) => setImmediate(resolve));
assert.equal(fetchCalls.length, 1, 'scroll should send one autosave request');
assert.equal(fetchCalls[0].url, '/materi/uji/posisi', 'autosave should use the existing save endpoint');
assert.match(fetchCalls[0].options.body, /position=7000/, 'autosave should submit current reading progress');
assert.match(fetchCalls[0].options.body, /study_csrf_token=old-token/, 'autosave should send the current CSRF token');
assert.equal(csrfInput.value, 'rotated-token', 'autosave should store the rotated CSRF token');
assert.equal(status.textContent, 'Progres tersimpan otomatis.', 'autosave should announce success accessibly');
handlers.submit();
assert.equal(positionInput.value, '7000', 'current vertical progress should be submitted in basis points');

console.log('PASS resume scaling, reduced-motion behavior, progressive enhancement, manual position capture, and debounced CSRF-aware autosave.');
