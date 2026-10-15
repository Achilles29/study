import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../assets/js/unsaved-form.js', import.meta.url), 'utf8');
const cleanText = 'Belum ada perubahan baru.';
const dirtyText = 'Ada perubahan yang belum disimpan.';

class FakeTarget {
	constructor() { this.listeners = new Map(); this.registrations = []; }
	addEventListener(type, callback, options = false) {
		if (!this.listeners.has(type)) this.listeners.set(type, new Set());
		this.listeners.get(type).add(callback);
		this.registrations.push({ type, callback, capture: options === true || Boolean(options?.capture) });
	}
	removeEventListener(type, callback) { this.listeners.get(type)?.delete(callback); }
	listenerCount(type) { return this.listeners.get(type)?.size || 0; }
	dispatch(type, properties = {}) {
		const event = { type, target: this, currentTarget: this, defaultPrevented: false, returnValue: undefined,
			preventDefault() { this.defaultPrevented = true; }, ...properties };
		for (const callback of [...(this.listeners.get(type) || [])]) callback.call(this, event);
		return event;
	}
}

class FakeControl {
	constructor({ tag = 'INPUT', type = 'text', name = 'title', value = '', defaultValue = value, checked = false, defaultChecked = checked, disabled = false, fieldsetDisabled = false, multiple = false, size = 0, options = [] } = {}) {
		Object.assign(this, { tagName: tag, nodeName: tag, type, name, value, checked, disabled, fieldsetDisabled, multiple, size, defaultValue, defaultChecked });
		this.options = options.map((option) => ({ disabled: false, optgroupDisabled: false, ...option, defaultSelected: option.defaultSelected ?? Boolean(option.selected),
			matches(selector) { assert.equal(selector, ':disabled'); return this.disabled || this.optgroupDisabled; } }));
		this.defaultSelection = this.options.map((option) => Boolean(option.defaultSelected));
		if (tag === 'SELECT' && !multiple) {
			let defaultIndex = this.options.findLastIndex((option) => option.defaultSelected);
			if (defaultIndex === -1 && size <= 1) defaultIndex = this.options.findIndex((option) => !option.matches(':disabled'));
			this.defaultSelection = this.options.map((option, index) => index === defaultIndex);
		}
	}
	get selectedOptions() { return this.options.filter((option) => option.selected); }
	get selectedIndex() { return this.options.findIndex((option) => option.selected); }
	matches(selector) {
		if (selector === ':disabled') return this.disabled || this.fieldsetDisabled;
		throw new Error(`Unexpected control selector: ${selector}`);
	}
	getAttribute(name) {
		if (name === 'name') return this.name || null;
		if (name === 'type') return this.type;
		return null;
	}
	restoreDefault() {
		this.value = this.defaultValue;
		this.checked = this.defaultChecked;
		this.options.forEach((option, index) => { option.selected = this.defaultSelection[index]; });
		if (this.tagName === 'SELECT') this.value = this.selectedOptions[0]?.value || '';
	}
}

class FakeForm extends FakeTarget {
	constructor(controls = [], { optedIn = true, initial = '', status = true } = {}) {
		super();
		this.elements = controls;
		this.optedIn = optedIn;
		this.dataset = { ...(optedIn ? { unsavedForm: '' } : {}), unsavedInitial: initial };
		this.status = status ? { textContent: 'Server fallback', hidden: true } : null;
		this.tagName = 'FORM';
	}
	querySelector(selector) {
		assert.equal(selector, '[data-unsaved-status]');
		return this.status;
	}
	querySelectorAll(selector) {
		const tags = selector.split(',').map((tag) => tag.trim().toUpperCase());
		assert.ok(tags.every((tag) => ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(tag)), `Unexpected form selector: ${selector}`);
		return this.elements.filter((control) => tags.includes(control.tagName));
	}
	getAttribute(name) {
		if (name === 'data-unsaved-form') return this.optedIn ? '' : null;
		if (name === 'data-unsaved-initial') return this.dataset.unsavedInitial;
		return null;
	}
	hasAttribute(name) { return this.getAttribute(name) !== null; }
	reset() {
		const event = this.dispatch('reset');
		if (!event.defaultPrevented) this.elements.forEach((control) => control.restoreDefault());
		return event;
	}
}

function fixture(forms = []) {
	const window = new FakeTarget();
	const document = new FakeTarget();
	const forbidden = [];
	const timers = new Map();
	let timerId = 0;
	const deny = (name) => () => { forbidden.push(name); throw new Error(`Unexpected browser side effect: ${name}`); };
	window.setTimeout = (callback, delay) => {
		assert.equal(delay, 0, 'deferred form-state cleanup must use a zero-delay task');
		timers.set(++timerId, callback);
		return timerId;
	};
	window.clearTimeout = (id) => timers.delete(id);
	window.queueMicrotask = queueMicrotask;
	window.fetch = deny('fetch');
	window.XMLHttpRequest = deny('window XMLHttpRequest');
	window.WebSocket = deny('window WebSocket');
	window.navigator = { sendBeacon: deny('sendBeacon') };
	for (const name of ['localStorage', 'sessionStorage', 'indexedDB', 'caches']) Object.defineProperty(window, name, { get: deny(name) });
	document.readyState = 'complete';
	document.forms = forms;
	document.querySelectorAll = (selector) => {
		assert.equal(selector, 'form[data-unsaved-form]');
		return forms.filter((form) => form.optedIn);
	};
	Object.defineProperty(document, 'cookie', { get: deny('cookie read'), set: deny('cookie write') });
	const context = vm.createContext({ window, document, queueMicrotask, setTimeout: window.setTimeout, clearTimeout: window.clearTimeout,
		fetch: deny('global fetch'), XMLHttpRequest: deny('XMLHttpRequest'), WebSocket: deny('WebSocket'), navigator: window.navigator });
	for (const name of ['localStorage', 'sessionStorage', 'indexedDB', 'caches']) Object.defineProperty(context, name, { get: deny(`global ${name}`) });
	vm.runInContext(source, context, { filename: 'unsaved-form.js' });
	return {
		window, forms, forbidden,
		runAgain() { vm.runInContext(source, context, { filename: 'unsaved-form.js' }); },
		async microtasks() { await Promise.resolve(); await Promise.resolve(); },
		runTimers() {
			const pending = [...timers.values()];
			timers.clear();
			pending.forEach((callback) => callback());
		},
		change(form, control, type = 'input') { return form.dispatch(type, { target: control }); },
		unload(expected, label) {
			const event = window.dispatch('beforeunload');
			assert.equal(event.defaultPrevented, expected, label);
			if (expected) assert.equal(event.returnValue, true, 'legacy beforeunload signal must be set');
			return event;
		},
		clean(form, label) {
			assert.equal(form.status?.textContent, cleanText, label);
			assert.equal(window.listenerCount('beforeunload'), 0, 'clean page must not retain a beforeunload listener');
		},
		dirty(form, label) {
			assert.equal(form.status?.textContent, dirtyText, label);
			assert.ok(window.listenerCount('beforeunload') > 0, 'dirty page must register a beforeunload listener');
		},
		noSideEffects() { assert.deepEqual(forbidden, [], 'unsaved guard must not persist or transmit form contents'); }
	};
}

let cases = 0;
async function test(label, run) {
	try { await run(); cases++; }
	catch (error) { error.message = `${label}: ${error.message}`; throw error; }
}

await test('opt-in only and no forms', () => {
	const empty = fixture();
	assert.equal(empty.window.listenerCount('beforeunload'), 0);
	empty.noSideEffects();
	const control = new FakeControl({ value: 'Original' });
	const ignored = new FakeForm([control], { optedIn: false });
	const missingStatus = new FakeForm([new FakeControl()], { status: false });
	const page = fixture([ignored, missingStatus]);
	control.value = 'Changed outside opt-in';
	page.change(ignored, control);
	assert.equal(ignored.status.textContent, 'Server fallback');
	assert.equal(ignored.status.hidden, true);
	assert.equal(missingStatus.listenerCount('input'), 0, 'missing status leaves native form behavior alone');
	assert.equal(page.window.listenerCount('beforeunload'), 0);
	page.noSideEffects();
});

await test('text, textarea, exact reversion and ignored controls', () => {
	const title = new FakeControl({ value: 'Judul awal' });
	const body = new FakeControl({ tag: 'TEXTAREA', type: 'textarea', name: 'body', value: 'Teks awal' });
	const ignored = [new FakeControl({ type: 'hidden', name: 'csrf', value: 'token' }), new FakeControl({ name: '', value: 'unnamed' }),
		new FakeControl({ disabled: true, name: 'locked', value: 'disabled' }), new FakeControl({ fieldsetDisabled: true, name: 'conflict', value: 'fieldset' }),
		...['button', 'submit', 'reset', 'image'].map((type) => new FakeControl({ type, name: type, value: type })),
		new FakeControl({ tag: 'BUTTON', type: 'submit', name: 'action', value: 'save' })];
	const form = new FakeForm([title, body, ...ignored]);
	const page = fixture([form]);
	page.clean(form);
	assert.equal(form.status.hidden, false, 'status appears only after enhancement initializes');
	for (const control of ignored) { control.value += ' changed'; page.change(form, control); }
	page.clean(form, 'hidden/disabled/unnamed/button changes are not user edits');
	title.value = 'Judul baru'; page.change(form, title);
	page.dirty(form); page.unload(true);
	title.value = title.defaultValue; page.change(form, title, 'change');
	page.clean(form, 'reverting text removes the guard');
	body.value += '\nBaris baru'; page.change(form, body);
	page.dirty(form);
	body.value = body.defaultValue; page.change(form, body);
	page.clean(form);
	page.noSideEffects();
});

await test('checkbox, radio, select and multiselect state', () => {
	const checkbox = new FakeControl({ type: 'checkbox', name: 'free', value: '1', checked: false });
	const firstRadio = new FakeControl({ type: 'radio', name: 'age', value: 'primary', checked: true });
	const secondRadio = new FakeControl({ type: 'radio', name: 'age', value: 'secondary', checked: false });
	const single = new FakeControl({ tag: 'SELECT', type: 'select-one', name: 'category', value: '1', options: [{ value: '1', selected: true }, { value: '2', selected: false }] });
	const multiple = new FakeControl({ tag: 'SELECT', type: 'select-multiple', name: 'topics', value: 'a', multiple: true, options: [{ value: 'a', selected: true }, { value: 'b', selected: false }] });
	const form = new FakeForm([checkbox, firstRadio, secondRadio, single, multiple]);
	const page = fixture([form]);
	page.clean(form);
	checkbox.checked = true; page.change(form, checkbox, 'change'); page.dirty(form);
	checkbox.checked = false; page.change(form, checkbox, 'change'); page.clean(form);
	firstRadio.checked = false; secondRadio.checked = true; page.change(form, secondRadio, 'change'); page.dirty(form);
	firstRadio.checked = true; secondRadio.checked = false; page.change(form, firstRadio, 'change'); page.clean(form);
	single.value = '2'; single.options[0].selected = false; single.options[1].selected = true; page.change(form, single, 'change'); page.dirty(form);
	single.restoreDefault(); page.change(form, single, 'change'); page.clean(form);
	multiple.options[1].selected = true; page.change(form, multiple, 'change'); page.dirty(form, 'additional selection must be detected even when select.value is unchanged');
	multiple.restoreDefault(); page.change(form, multiple, 'change'); page.clean(form);
	page.noSideEffects();
});

await test('pre-initialization restored controls compare with server defaults', () => {
	const restoredControls = [
		new FakeControl({ value: 'Browser-restored title', defaultValue: 'Server title' }),
		new FakeControl({ tag: 'TEXTAREA', type: 'textarea', name: 'body', value: 'Restored body', defaultValue: 'Server body' }),
		new FakeControl({ type: 'checkbox', name: 'free', value: '1', checked: true, defaultChecked: false }),
		new FakeControl({ type: 'radio', name: 'age', value: 'primary', checked: false, defaultChecked: true }),
		new FakeControl({ tag: 'SELECT', type: 'select-one', name: 'category', value: 'b', options: [{ value: 'a', selected: false, defaultSelected: true }, { value: 'b', selected: true, defaultSelected: false }] }),
		new FakeControl({ tag: 'SELECT', type: 'select-multiple', multiple: true, name: 'topics', value: 'b', options: [{ value: 'a', selected: false, defaultSelected: true }, { value: 'b', selected: true, defaultSelected: false }] })
	];
	for (const control of restoredControls) {
		const form = new FakeForm([control]);
		const page = fixture([form]);
		page.dirty(form, 'history/autofill restoration before script must not become a false saved baseline');
		page.unload(true);
		control.restoreDefault(); page.change(form, control, 'change');
		page.clean(form, 'reverting to server defaults removes the restored-value guard');
		page.noSideEffects();
	}
});

await test('single-select default selection follows native fallback rules', () => {
	const cases = [
		{ value: 'b', options: [{ value: 'a', selected: false, defaultSelected: false, disabled: true }, { value: 'b', selected: true, defaultSelected: false }] },
		{ value: 'b', options: [{ value: 'a', selected: false, defaultSelected: false, optgroupDisabled: true }, { value: 'b', selected: true, defaultSelected: false }] },
		{ value: 'b', options: [{ value: 'a', selected: false, defaultSelected: true }, { value: 'b', selected: true, defaultSelected: true }] },
		{ value: '', size: 3, options: [{ value: 'a', selected: false, defaultSelected: false }, { value: 'b', selected: false, defaultSelected: false }] }
	];
	for (const selection of cases) {
		const control = new FakeControl({ tag: 'SELECT', type: 'select-one', name: 'category', ...selection });
		const form = new FakeForm([control]);
		const page = fixture([form]);
		page.clean(form, 'untouched native default selection must not create a false dirty state');
		page.noSideEffects();
	}
});

await test('multiple forms keep independent baselines and aggregate protection', () => {
	const first = new FakeForm([new FakeControl({ value: 'First' })]);
	const second = new FakeForm([new FakeControl({ value: 'Second' })]);
	const page = fixture([first, second]);
	first.elements[0].value = 'First changed'; page.change(first, first.elements[0]);
	assert.equal(second.status.textContent, cleanText);
	second.elements[0].value = 'Second changed'; page.change(second, second.elements[0]);
	first.elements[0].restoreDefault(); page.change(first, first.elements[0]);
	assert.equal(first.status.textContent, cleanText);
	page.dirty(second); page.unload(true, 'reverting one form must not disarm another dirty form');
	second.elements[0].restoreDefault(); page.change(second, second.elements[0]);
	page.clean(second);
	page.noSideEffects();
});

await test('initial failed POST/conflict remains dirty through reversion and reset', async () => {
	const control = new FakeControl({ value: 'Preserved failed submission' });
	const form = new FakeForm([control], { initial: 'true' });
	const page = fixture([form]);
	page.dirty(form); page.unload(true);
	control.value += ' edited'; page.change(form, control);
	control.restoreDefault(); page.change(form, control);
	page.dirty(form, 'the server marked these original displayed values unsaved');
	form.reset(); await page.microtasks(); page.runTimers(); page.dirty(form);
	page.window.dispatch('pageshow', { persisted: true }); page.dirty(form); page.unload(true);
	page.noSideEffects();
	const cleanForm = new FakeForm([new FakeControl()], { initial: 'false' });
	fixture([cleanForm]).clean(cleanForm, 'only the exact true initial marker opts into dirty state');
});

await test('cancelled submit, including cancellation after a microtask, still guards', async () => {
	for (const lateCancellation of [false, true]) {
		const control = new FakeControl({ value: 'Before' });
		const form = new FakeForm([control]);
		const page = fixture([form]);
		control.value = 'After'; page.change(form, control);
		if (!lateCancellation) form.addEventListener('submit', (event) => event.preventDefault());
		const submission = form.dispatch('submit');
		await page.microtasks();
		if (lateCancellation) submission.preventDefault();
		page.dirty(form); page.unload(true, 'later cancellation must be checked at beforeunload, not frozen in an earlier microtask');
		page.runTimers(); page.unload(true);
		page.noSideEffects();
	}
});

await test('native submit suppresses one navigation without claiming saved', async () => {
	const control = new FakeControl({ value: 'Before' });
	const form = new FakeForm([control]);
	const page = fixture([form]);
	control.value = 'After'; page.change(form, control);
	form.dispatch('submit'); await page.microtasks();
	assert.equal(form.status.textContent, dirtyText, 'dispatching a submit is not proof that persistence succeeded');
	page.unload(false, 'uncancelled native submission should not produce a redundant leave-page warning');
	page.unload(true, 'submission intent is consumed, not a permanent navigation bypass');
	form.dispatch('submit'); await page.microtasks();
	page.runTimers();
	page.unload(false, 'native navigation may run after timers; pending submission must survive until beforeunload');
	page.unload(true, 'submission intent is consumed after the queued native navigation check');
	page.noSideEffects();
});

await test('input, change and pageshow restore guarding after pending submit', async () => {
	for (const eventType of ['input', 'change', 'pageshow']) {
		const control = new FakeControl({ value: 'Before' });
		const form = new FakeForm([control]);
		const page = fixture([form]);
		control.value = 'After'; page.change(form, control);
		form.dispatch('submit'); await page.microtasks();
		if (eventType === 'pageshow') page.window.dispatch('pageshow', { persisted: true });
		else page.change(form, control, eventType);
		page.dirty(form); page.unload(true, `${eventType} must clear temporary navigation suppression`);
		page.noSideEffects();
	}
});

await test('next pointer or keyboard action rearms the guard before navigation', async () => {
	for (const type of ['pointerdown', 'keydown']) {
		const control = new FakeControl({ value: 'Before' });
		const form = new FakeForm([control]);
		const page = fixture([form]);
		assert.ok(page.window.registrations.some((registration) => registration.type === type && registration.capture), `${type} must be observed in capture phase before the next user action`);
		control.value = 'After'; page.change(form, control);
		form.dispatch('submit'); await page.microtasks(); page.runTimers();
		page.window.dispatch(type, { target: control });
		page.dirty(form); page.unload(true, `${type} must clear pending intent before a later unrelated navigation`);
		page.noSideEffects();
	}
});

await test('submitting one form does not abandon another dirty form', async () => {
	const first = new FakeForm([new FakeControl({ value: 'First' })]);
	const second = new FakeForm([new FakeControl({ value: 'Second' })]);
	const page = fixture([first, second]);
	for (const form of [first, second]) { form.elements[0].value += ' changed'; page.change(form, form.elements[0]); }
	first.dispatch('submit'); await page.microtasks();
	page.unload(true, 'the unsubmitted second form is still at risk');
	page.dirty(first); page.dirty(second);
	page.noSideEffects();
});

await test('reset waits for default action and cancelled reset keeps edits', async () => {
	const control = new FakeControl({ value: 'Before' });
	const form = new FakeForm([control]);
	const page = fixture([form]);
	control.value = 'After'; page.change(form, control);
	form.dispatch('submit'); await page.microtasks();
	form.reset(); page.unload(true, 'reset clears pending submit synchronously before deferred value sampling');
	await page.microtasks(); page.runTimers(); page.clean(form, 'reset state must be sampled after controls return to their defaults');
	control.value = 'Changed again'; page.change(form, control);
	form.addEventListener('reset', (event) => event.preventDefault());
	form.reset(); await page.microtasks(); page.runTimers(); page.dirty(form); page.unload(true);
	page.noSideEffects();
});

await test('post-pageshow restoration is detected without cancelling a newer submit', async () => {
	const control = new FakeControl({ value: 'Server original' });
	const form = new FakeForm([control]);
	const page = fixture([form]);
	page.window.dispatch('pageshow', { persisted: true });
	page.clean(form);
	control.value = 'Restored after pageshow';
	page.runTimers();
	page.dirty(form, 'history restoration after pageshow needs the deferred second sample');
	page.unload(true);
	page.window.dispatch('pageshow', { persisted: true });
	form.dispatch('submit'); await page.microtasks();
	page.runTimers();
	page.unload(false, 'deferred pageshow refresh must not erase a newer pending native submit');
	page.unload(true);
	page.noSideEffects();
});

await test('pageshow resamples restored values and repeated initialization is harmless', () => {
	const control = new FakeControl({ value: 'Original' });
	const form = new FakeForm([control]);
	const page = fixture([form]);
	const listeners = { input: form.listenerCount('input'), submit: form.listenerCount('submit'), pageshow: page.window.listenerCount('pageshow') };
	page.runAgain();
	assert.equal(form.listenerCount('input'), listeners.input);
	assert.equal(form.listenerCount('submit'), listeners.submit);
	assert.equal(page.window.listenerCount('pageshow'), listeners.pageshow);
	control.value = 'Browser restored value';
	page.window.dispatch('pageshow', { persisted: true });
	page.dirty(form); page.unload(true);
	control.restoreDefault(); page.window.dispatch('pageshow', { persisted: false });
	page.clean(form);
	page.noSideEffects();
});

console.log(`PASS unsaved-form behavior: ${cases} groups covering opt-in snapshots, ignored controls, choices, multiple forms, failed initial state, cancellation/native submit intent, reset/pageshow, and no browser storage/network.`);
console.log('LIMITATION synthetic DOM/events and explicit microtask/timer scheduling only; native browser prompts, form validation, navigation ordering and BFCache require browser tests.');
