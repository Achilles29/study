import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import vm from 'node:vm';
import { focusSnapshotExpression, validateFocusEvidenceOptions, writeFocusEvidence } from './browser-focus-evidence.mjs';

const tests = [];
const test = (name, run) => tests.push({ name, run });
const validOptions = () => ({ enabled: true, nativeZoom: true, captureScreenshots: true, loginIdentity: '', loginPassword: '', routes: [{ path: '' }, { path: 'kursus' }, { path: 'masuk' }, { path: 'daftar' }] });
const directory = path.resolve('synthetic-focus-evidence-no-files-created');
const stem = 'daftar-360-focus-01';
const pngData = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
const pngBytes = Buffer.from(pngData, 'base64');

function blockProperties(target, names) {
	for (const name of names) Object.defineProperty(target, name, { get() { throw new Error(`Forbidden private read: ${name}`); } });
	return target;
}

function snapshotFixture({ bodyActive = false, obscured = false, childHit = false, noHit = false, rect = { top: 10, bottom: 50, left: 20, right: 100, width: 80, height: 40 }, outlineStyle = 'solid', outlineWidth = '2px', focused = true, visual = true, type = 'password', fontsStatus = 'loaded', longStack = false } = {}) {
	const attributes = [];
	const mutations = [];
	const child = { tagName: 'SPAN', id: 'synthetic-child', getAttribute(name) { attributes.push(name); assert.equal(name, 'type'); return null; } };
	const other = { tagName: 'DIV', id: 'synthetic-overlay', getAttribute(name) { attributes.push(name); assert.equal(name, 'type'); return null; } };
	const element = blockProperties({
		tagName: bodyActive ? 'BODY' : 'INPUT', id: bodyActive ? '' : 'synthetic-control',
		getAttribute(name) { attributes.push(name); assert.equal(name, 'type'); return bodyActive ? null : type; },
		getBoundingClientRect() { return { ...rect }; },
		matches(selector) { assert.ok([':focus', ':focus-visible'].includes(selector)); return focused; },
		contains(node) { return node === child; },
		focus() { mutations.push('focus'); throw new Error('Snapshot must not repair focus.'); },
		scrollIntoView() { mutations.push('scroll'); throw new Error('Snapshot must not repair scrolling.'); }
	}, ['value', 'defaultValue', 'textContent', 'innerText', 'innerHTML', 'outerHTML', 'labels', 'href', 'dataset']);
	const hit = noHit ? null : obscured ? other : childHit ? child : element;
	let testedPoint;
	const stack = noHit ? [] : longStack ? [hit, ...Array.from({ length: 12 }, () => other)] : [hit, other];
	const document = blockProperties({
		activeElement: element, body: bodyActive ? element : {},
		hasFocus() { return focused; }, visibilityState: focused ? 'visible' : 'hidden',
		fonts: fontsStatus === null ? null : blockProperties({ status: fontsStatus }, ['ready']),
		documentElement: { clientWidth: 345, clientHeight: 620 },
		elementFromPoint(x, y) { testedPoint = { x, y }; return hit; },
		elementsFromPoint(x, y) { assert.deepEqual({ x, y }, testedPoint); return stack; },
		getElementsByTagName(tag) {
			assert.ok(['BODY', 'INPUT', 'SPAN', 'DIV'].includes(tag));
			return { BODY: [element], INPUT: [{}, element], SPAN: [child], DIV: [other] }[tag];
		}
	}, ['cookie', 'URL', 'documentURI', 'referrer', 'title']);
	const sandbox = blockProperties({
		document, innerWidth: 360, innerHeight: 640, scrollX: 3, scrollY: 125, devicePixelRatio: 2,
		visualViewport: visual ? { width: 360, height: 600, offsetTop: 12, offsetLeft: 2, scale: 1 } : null,
		getComputedStyle(node) { assert.equal(node, element); return { outlineStyle, outlineWidth, outlineOffset: '3px', outlineColor: 'rgb(4, 80, 120)' }; },
		focus() { mutations.push('window.focus'); }, scrollTo() { mutations.push('window.scrollTo'); },
		scrollBy() { mutations.push('window.scrollBy'); }
	}, ['localStorage', 'sessionStorage', 'location', 'navigator']);
	const result = JSON.parse(JSON.stringify(vm.runInNewContext(focusSnapshotExpression, sandbox)));
	return { result, attributes, mutations, testedPoint };
}

test('only explicitly enabled public native-zoom screenshot capture is accepted', () => {
	assert.doesNotThrow(() => validateFocusEvidenceOptions(validOptions()));
	assert.doesNotThrow(() => validateFocusEvidenceOptions({ enabled: false }));
	for (const update of [{ nativeZoom: false }, { captureScreenshots: false }, { loginIdentity: 'synthetic-user' }, { loginPassword: 'synthetic-private-input' }]) {
		assert.throws(() => validateFocusEvidenceOptions({ ...validOptions(), ...update }), /requires native zoom/);
	}
});

test('private, queried, fragmented, encoded and noncanonical routes are rejected', () => {
	for (const route of ['kelola', 'keluarga', 'materi/contoh', 'kursus/contoh', 'kursus?q=contoh', '?q=contoh', 'daftar?token=example', 'masuk#form', '/kursus', 'kursus/', 'Kursus', ' kursus', '%6bursus', 'https://example.invalid/masuk', '//example.invalid', '../masuk', '', null, 1]) {
		if (route === '') continue;
		assert.throws(() => validateFocusEvidenceOptions({ ...validOptions(), routes: [{ path: '' }, { path: route }] }), /only public/);
	}
	assert.throws(() => validateFocusEvidenceOptions({ ...validOptions(), routes: [{}] }), /only public/);
});

test('snapshot is a fixed geometry/focus allowlist and never reads private DOM data', () => {
	const { result, mutations, attributes, testedPoint } = snapshotFixture();
	assert.deepEqual(Object.keys(result).sort(), ['center', 'documentFocused', 'element', 'focusIndicator', 'focusVisible', 'focused', 'fontsStatus', 'hit', 'hitStack', 'outline', 'outsidePage', 'point', 'rect', 'scroll', 'viewport', 'visibilityState', 'visible', 'visualViewport'].sort());
	assert.deepEqual(result.element, { tag: 'INPUT', id: 'synthetic-control', type: 'password', index: 1 });
	assert.deepEqual(result.hit, result.element);
	assert.deepEqual(result.hitStack, [result.element, { tag: 'DIV', id: 'synthetic-overlay', type: '', index: 0 }]);
	assert.deepEqual(result.rect, { top: 10, bottom: 50, left: 20, right: 100, width: 80, height: 40 });
	assert.deepEqual(result.center, { x: 60, y: 30 });
	assert.deepEqual(result.point, { x: 60, y: 30 });
	assert.deepEqual(testedPoint, result.point);
	assert.deepEqual(result.outline, { style: 'solid', width: '2px', offset: '3px', color: 'rgb(4, 80, 120)' });
	assert.deepEqual(result.scroll, { x: 3, y: 125 });
	assert.deepEqual(result.viewport, { width: 360, height: 640, clientWidth: 345, clientHeight: 620, dpr: 2 });
	assert.deepEqual(result.visualViewport, { width: 360, height: 600, top: 12, left: 2, scale: 1 });
	assert.equal(result.documentFocused, true);
	assert.equal(result.focusVisible, true);
	assert.equal(result.focused, true);
	assert.equal(result.visibilityState, 'visible');
	assert.equal(result.fontsStatus, 'loaded');
	assert.equal(result.outsidePage, false);
	assert.equal(result.visible, true);
	assert.equal(result.focusIndicator, true);
	assert.ok(attributes.length > 0 && attributes.every(attribute => attribute === 'type'));
	assert.deepEqual(mutations, []);
});

test('snapshot retains failure evidence without repairing hidden, obscured or body focus', () => {
	const obscured = snapshotFixture({ obscured: true, focused: false, outlineStyle: 'none', visual: false });
	assert.equal(obscured.result.visible, false);
	assert.equal(obscured.result.focusIndicator, false);
	assert.equal(obscured.result.documentFocused, false);
	assert.equal(obscured.result.focused, false);
	assert.equal(obscured.result.focusVisible, false);
	assert.equal(obscured.result.visibilityState, 'hidden');
	assert.equal(obscured.result.hit.id, 'synthetic-overlay');
	assert.equal(obscured.result.visualViewport, null);
	assert.deepEqual(obscured.mutations, []);
	assert.equal(snapshotFixture({ childHit: true }).result.visible, true);
	assert.equal(snapshotFixture({ noHit: true }).result.hit, null);
	assert.deepEqual(snapshotFixture({ noHit: true }).result.hitStack, []);
	assert.equal(snapshotFixture({ noHit: true }).result.visible, false);
	assert.equal(snapshotFixture({ bodyActive: true }).result.outsidePage, true);
	assert.equal(snapshotFixture({ outlineWidth: '0px' }).result.focusIndicator, false);
});

test('offscreen hit-test points are bounded without scrolling the page', () => {
	const upper = snapshotFixture({ rect: { top: -80, bottom: -40, left: -100, right: -20, width: 80, height: 40 } });
	assert.deepEqual(upper.testedPoint, { x: 0, y: 0 });
	assert.deepEqual(upper.result.center, { x: -60, y: -60 });
	assert.equal(upper.result.visible, false);
	assert.deepEqual(upper.mutations, []);
	const lower = snapshotFixture({ rect: { top: 700, bottom: 740, left: 400, right: 480, width: 80, height: 40 } });
	assert.deepEqual(lower.testedPoint, { x: 359, y: 639 });
	assert.deepEqual(lower.result.center, { x: 440, y: 720 });
	assert.equal(lower.result.visible, false);
	assert.deepEqual(lower.mutations, []);
	assert.equal(snapshotFixture({ rect: { top: 10, bottom: 10, left: 20, right: 20, width: 0, height: 0 } }).result.visible, false);
});

test('extra diagnostics disambiguate radios, bound hit layers and observe fonts without waiting', () => {
	const radio = snapshotFixture({ type: 'radio', fontsStatus: 'loading', longStack: true }).result;
	assert.deepEqual(radio.element, { tag: 'INPUT', id: 'synthetic-control', type: 'radio', index: 1 });
	assert.equal(radio.hitStack.length, 8);
	assert.equal(radio.fontsStatus, 'loading');
	assert.equal(radio.visible, true);
	assert.equal(radio.focusIndicator, true);
	assert.equal(snapshotFixture({ fontsStatus: null }).result.fontsStatus, 'unavailable');
});

test('snapshot source contains no value/content/storage access or focus/scroll/network mutation', async () => {
	assert.doesNotMatch(focusSnapshotExpression, /\b(?:value|defaultValue|textContent|innerText|innerHTML|outerHTML|cookie|localStorage|sessionStorage|documentURI|referrer|href|location)\b/);
	assert.doesNotMatch(focusSnapshotExpression, /\b(?:focus|blur|scrollTo|scrollBy|scrollIntoView|click|setAttribute|fetch|XMLHttpRequest|sendBeacon)\s*\(/);
	assert.doesNotMatch(focusSnapshotExpression, /\.(?:value|checked|selected|scrollTop|scrollLeft)\s*=/);
	const source = await readFile(new URL('./browser-focus-evidence.mjs', import.meta.url), 'utf8');
	assert.doesNotMatch(source, /(?:flag\s*:\s*['"](?:w|a|w\+|a\+)['"]|\.message\b|\.stack\b)/);
});

test('writer awaits current PNG bytes before JSON and uses exclusive creation for both', async () => {
	const events = [];
	const calls = [];
	const report = Object.freeze({ status: 'failed', reason: 'focus-not-visible', snapshot: Object.freeze({ visible: false }), screenshot: Object.freeze({ status: 'synthetic-old' }) });
	const result = await writeFocusEvidence({ directory, stem, report,
		async captureScreenshot(...args) { assert.deepEqual(args, []); events.push('capture-start'); await Promise.resolve(); events.push('capture-end'); return pngData; },
		async write(filename, data, options) { events.push(path.extname(filename)); calls.push({ filename, data, options }); await Promise.resolve(); }
	});
	assert.deepEqual(events, ['capture-start', 'capture-end', '.png', '.json']);
	assert.equal(calls[0].filename, path.join(directory, `${stem}.png`));
	assert.ok(Buffer.isBuffer(calls[0].data));
	assert.deepEqual(calls[0].data, pngBytes);
	assert.deepEqual(calls.map(call => call.options), [{ flag: 'wx' }, { flag: 'wx' }]);
	assert.equal(calls[1].filename, path.join(directory, `${stem}.json`));
	assert.equal(typeof calls[1].data, 'string');
	assert.ok(calls[1].data.endsWith('\n'));
	assert.deepEqual(JSON.parse(calls[1].data), { ...report, screenshot: { status: 'saved', filename: `${stem}.png` } });
	assert.equal(JSON.parse(calls[1].data).status, 'failed');
	assert.deepEqual(result, { filename: `${stem}.json`, screenshot: { status: 'saved', filename: `${stem}.png` } });
	assert.equal(report.screenshot.status, 'synthetic-old');
});

test('capture failure persists a failed screenshot without raw error content or a passing report', async () => {
	const calls = [];
	const result = await writeFocusEvidence({ directory, stem, report: { status: 'failed', reason: 'focus-outside-viewport' },
		captureScreenshot() { throw new Error('synthetic-private-page-content'); },
		async write(filename, data, options) { calls.push({ filename, data, options }); }
	});
	assert.equal(calls.length, 1);
	assert.equal(calls[0].filename, path.join(directory, `${stem}.json`));
	assert.deepEqual(calls[0].options, { flag: 'wx' });
	assert.deepEqual(JSON.parse(calls[0].data), { status: 'failed', reason: 'focus-outside-viewport', screenshot: { status: 'failed' } });
	assert.doesNotMatch(calls[0].data, /synthetic-private-page-content|stack/);
	assert.deepEqual(result.screenshot, { status: 'failed' });
});

test('PNG write failure is recorded in JSON and never retried with overwrite flags', async () => {
	const calls = [];
	await writeFocusEvidence({ directory, stem, report: { status: 'failed' }, captureScreenshot: async () => pngData,
		async write(filename, data, options) { calls.push({ filename, data, options }); if (filename.endsWith('.png')) throw new Error('synthetic-private-filesystem-path'); }
	});
	assert.deepEqual(calls.map(call => path.extname(call.filename)), ['.png', '.json']);
	assert.ok(calls.every(call => call.options.flag === 'wx'));
	assert.deepEqual(JSON.parse(calls[1].data), { status: 'failed', screenshot: { status: 'failed' } });
	assert.doesNotMatch(calls[1].data, /synthetic-private-filesystem-path/);
});

test('JSON-only evidence explicitly records that no screenshot was requested', async () => {
	const calls = [];
	const result = await writeFocusEvidence({ directory, stem, report: { status: 'failed' }, async write(...args) { calls.push(args); } });
	assert.equal(calls.length, 1);
	assert.deepEqual(calls[0][2], { flag: 'wx' });
	assert.deepEqual(JSON.parse(calls[0][1]), { status: 'failed', screenshot: { status: 'not-requested' } });
	assert.deepEqual(result.screenshot, { status: 'not-requested' });
});

test('JSON write failure propagates unchanged after an already saved PNG', async () => {
	const failure = new Error('synthetic-json-write-failure');
	const calls = [];
	await assert.rejects(writeFocusEvidence({ directory, stem, report: { status: 'failed' }, captureScreenshot: async () => pngData,
		async write(filename, data, options) { calls.push({ filename, data, options }); if (filename.endsWith('.json')) throw failure; }
	}), error => error === failure);
	assert.deepEqual(calls.map(call => path.extname(call.filename)), ['.png', '.json']);
	assert.ok(calls.every(call => call.options.flag === 'wx'));
});

test('JSON failure still propagates after capture failure or with no screenshot callback', async () => {
	for (const captureScreenshot of [undefined, async () => { throw new Error('synthetic-capture-failure'); }]) {
		const failure = new Error('synthetic-json-failure');
		await assert.rejects(writeFocusEvidence({ directory, stem, report: { status: 'failed' }, captureScreenshot, async write() { throw failure; } }), error => error === failure);
	}
});

test('JSON serialization failure propagates before any JSON write', async () => {
	const report = { status: 'failed' };
	report.circular = report;
	let writes = 0;
	await assert.rejects(writeFocusEvidence({ directory, stem, report, async write() { writes++; } }), /circular/i);
	assert.equal(writes, 0);
});

test('unsafe or overlong filenames fail before capture and before writing', async () => {
	for (const invalid of ['', '../proof', '..', '/proof', 'proof/name', 'proof\\name', 'proof.json', 'proof:other', 'proof name', 'proof\n', 'bukti-é', 'x'.repeat(101)]) {
		let calls = 0;
		await assert.rejects(writeFocusEvidence({ directory, stem: invalid, report: {}, async captureScreenshot() { calls++; }, async write() { calls++; } }), /Invalid focus evidence filename/);
		assert.equal(calls, 0);
	}
	for (const valid of ['a', 'A_Z-9', 'x'.repeat(100)]) {
		let output;
		await writeFocusEvidence({ directory, stem: valid, report: {}, async write(filename) { output = filename; } });
		assert.equal(output, path.join(directory, `${valid}.json`));
	}
});

for (const { name, run } of tests) {
	try { await run(); }
	catch (error) { throw new Error(`Focus evidence unit failed: ${name}`, { cause: error }); }
}
console.log(`PASS ${tests.length} deterministic focus-evidence cases: public-only options, private-data-free observation, failure-preserving exclusive PNG/JSON writes and filename guards. VM/in-memory writers only; no browser, database or files created.`);
