import assert from 'node:assert/strict';
import { waitForPageLoad } from './browser-load-wait.mjs';

const tests = [];
const test = (name, run) => tests.push({ name, run });

class FakeSocket {
	listeners = new Map();
	addEventListener(type, listener) {
		if (!this.listeners.has(type)) this.listeners.set(type, new Set());
		this.listeners.get(type).add(listener);
	}
	removeEventListener(type, listener) { this.listeners.get(type)?.delete(listener); }
	emit(type, event = {}) { for (const listener of [...(this.listeners.get(type) || [])]) listener(event); }
	count(type) { return this.listeners.get(type)?.size || 0; }
	load() { this.emit('message', { data: JSON.stringify({ method: 'Page.loadEventFired' }) }); }
}

async function withTimers(run) {
	const originalSetTimeout = globalThis.setTimeout;
	const originalClearTimeout = globalThis.clearTimeout;
	const timers = new Map();
	const cleared = [];
	let nextId = 1;
	globalThis.setTimeout = (callback, milliseconds) => {
		const id = nextId++;
		timers.set(id, { callback, milliseconds });
		return id;
	};
	globalThis.clearTimeout = (id) => { cleared.push(id); timers.delete(id); };
	try {
		await run({ timers, cleared, fire(id = timers.keys().next().value) {
			const timer = timers.get(id);
			assert.ok(timer, 'The expected pending timer must exist.');
			timers.delete(id);
			timer.callback();
		} });
		assert.equal(timers.size, 0, 'Every settled wait must remove its timer.');
	} finally {
		globalThis.setTimeout = originalSetTimeout;
		globalThis.clearTimeout = originalClearTimeout;
	}
}

function expectDetached(socket) {
	assert.equal(socket.count('message'), 0);
	assert.equal(socket.count('close'), 0);
}

test('load resolves once and removes both listeners and its default timer', () => withTimers(async clock => {
	const socket = new FakeSocket();
	const pending = waitForPageLoad({ socket }, 'synthetic home');
	assert.ok(pending instanceof Promise);
	assert.equal(typeof pending.cancel, 'function');
	assert.equal(socket.count('message'), 1);
	assert.equal(socket.count('close'), 1);
	assert.equal([...clock.timers.values()][0].milliseconds, 10000);
	const staleTimeout = [...clock.timers.values()][0].callback;
	socket.load();
	assert.equal(await pending, undefined);
	expectDetached(socket);
	assert.equal(clock.timers.size, 0);
	assert.equal(clock.cleared.length, 1);
	assert.equal(pending.cancel(), false);
	socket.emit('close');
	staleTimeout();
	assert.equal(await pending, undefined);
	assert.equal(clock.cleared.length, 1);
}));

test('unrelated, malformed and empty CDP messages cannot resolve the wait', () => withTimers(async clock => {
	const socket = new FakeSocket();
	const pending = waitForPageLoad({ socket }, 'synthetic catalog', 25);
	for (const data of ['invalid json', 'null', '{}', '[]', JSON.stringify({ method: 'Runtime.consoleAPICalled' }), JSON.stringify({ id: 1, result: {} })]) {
		socket.emit('message', { data });
		assert.equal(clock.timers.size, 1);
		assert.equal(socket.count('message'), 1);
	}
	assert.equal([...clock.timers.values()][0].milliseconds, 25);
	socket.load();
	await pending;
	expectDetached(socket);
}));

test('timeout rejects without raw event data and removes every resource', () => withTimers(async clock => {
	const socket = new FakeSocket();
	const pending = waitForPageLoad({ socket }, 'synthetic sign-in', 10);
	clock.fire();
	await assert.rejects(pending, { message: 'Timed out loading synthetic sign-in.' });
	expectDetached(socket);
	assert.equal(clock.cleared.length, 1);
	assert.equal(pending.cancel(), false);
	socket.load();
	await assert.rejects(pending, /Timed out loading/);
}));

test('socket close rejects once and does not include its reason or payload', () => withTimers(async clock => {
	const socket = new FakeSocket();
	const pending = waitForPageLoad({ socket }, 'synthetic sign-up');
	socket.emit('close', { reason: 'private synthetic payload' });
	await assert.rejects(pending, { message: 'Chrome DevTools connection closed while loading synthetic sign-up.' });
	expectDetached(socket);
	assert.equal(clock.timers.size, 0);
	assert.equal(pending.cancel(), false);
}));

test('cancel rejects, is idempotent and does not disturb unrelated socket listeners', () => withTimers(async clock => {
	const socket = new FakeSocket();
	let unrelatedCalls = 0;
	const unrelated = () => { unrelatedCalls++; };
	socket.addEventListener('message', unrelated);
	socket.addEventListener('close', unrelated);
	const pending = waitForPageLoad({ socket }, 'synthetic cancelled route');
	assert.equal(pending.cancel(), true);
	assert.equal(pending.cancel(), false);
	await assert.rejects(pending, { message: 'Cancelled loading synthetic cancelled route.' });
	assert.equal(socket.count('message'), 1);
	assert.equal(socket.count('close'), 1);
	assert.equal(clock.timers.size, 0);
	socket.load();
	socket.emit('close');
	assert.equal(unrelatedCalls, 2);
}));

test('independent waits clean up only their own resources', () => withTimers(async clock => {
	const socket = new FakeSocket();
	const first = waitForPageLoad({ socket }, 'synthetic first');
	const second = waitForPageLoad({ socket }, 'synthetic second');
	assert.equal(socket.count('message'), 2);
	assert.equal(clock.timers.size, 2);
	first.cancel();
	await assert.rejects(first, /Cancelled loading synthetic first/);
	assert.equal(socket.count('message'), 1);
	assert.equal(socket.count('close'), 1);
	assert.equal(clock.timers.size, 1);
	socket.load();
	await second;
	expectDetached(socket);
}));

test('late awaiting timeout, close and cancellation never emits unhandled rejection', async () => {
	const unhandled = [];
	const handledLate = [];
	const onUnhandled = reason => unhandled.push(reason);
	const onHandledLate = promise => handledLate.push(promise);
	process.on('unhandledRejection', onUnhandled);
	process.on('rejectionHandled', onHandledLate);
	try {
		for (const mode of ['timeout', 'close', 'cancel']) {
			await withTimers(async clock => {
				const socket = new FakeSocket();
				const pending = waitForPageLoad({ socket }, 'synthetic late await', 10);
				if (mode === 'timeout') clock.fire();
				else if (mode === 'close') socket.emit('close');
				else pending.cancel();
				// Cross an event-loop turn before attaching the consumer's handler.
				await new Promise(resolve => setImmediate(resolve));
				assert.deepEqual(unhandled, []);
				await assert.rejects(pending, /synthetic late await/);
				await new Promise(resolve => setImmediate(resolve));
				assert.deepEqual(handledLate, []);
				expectDetached(socket);
			});
		}
	} finally {
		process.removeListener('unhandledRejection', onUnhandled);
		process.removeListener('rejectionHandled', onHandledLate);
	}
});

test('registration failure clears a partially registered listener and timeout', () => withTimers(async clock => {
	const socket = new FakeSocket();
	const add = socket.addEventListener.bind(socket);
	socket.addEventListener = (type, listener) => {
		if (type === 'close') throw new Error('private synthetic registration error');
		add(type, listener);
	};
	const pending = waitForPageLoad({ socket }, 'synthetic setup failure');
	await assert.rejects(pending, { message: 'Could not register Chrome page-load wait.' });
	expectDetached(socket);
	assert.equal(clock.timers.size, 0);
}));

test('invalid timeout is rejected before registering any resource', () => withTimers(async clock => {
	const socket = new FakeSocket();
	for (const timeout of [0, -1, 0.5, NaN, Infinity, '10', null, 2147483648]) {
		assert.throws(() => waitForPageLoad({ socket }, 'synthetic invalid timeout', timeout), /positive bounded integer/);
		expectDetached(socket);
		assert.equal(clock.timers.size, 0);
	}
}));

for (const { name, run } of tests) {
	try { await run(); }
	catch (error) { throw new Error(`Browser load-wait unit failed: ${name}`, { cause: error }); }
}
console.log(`PASS ${tests.length} browser load-wait cases: success/timeout/close/cancel, late-await rejection safety, listener/timer cleanup and independent waits. Fake socket/timers only; no browser, database or files created.`);
