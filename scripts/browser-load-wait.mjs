// Observe one page-load event without leaving listeners or an unhandled rejection
// behind when a CDP command fails before the caller starts awaiting this promise.
export function waitForPageLoad(client, label, timeoutMs = 10000) {
	if (!Number.isInteger(timeoutMs) || timeoutMs < 1 || timeoutMs > 2147483647) {
		throw new TypeError('Page-load timeout must be a positive bounded integer.');
	}
	const socket = client.socket;
	let settled = false;
	let timer;
	let resolveWait;
	let rejectWait;
	const pending = new Promise((resolve, reject) => { resolveWait = resolve; rejectWait = reject; });
	// Keep the original promise rejected for a later await, but mark it handled now.
	pending.catch(() => {});
	const cleanup = () => {
		clearTimeout(timer);
		socket.removeEventListener('message', onMessage);
		socket.removeEventListener('close', onClose);
	};
	const settle = (error) => {
		if (settled) return false;
		settled = true;
		cleanup();
		if (error) rejectWait(error);
		else resolveWait();
		return true;
	};
	const onMessage = (event) => {
		let message;
		try { message = JSON.parse(event.data); }
		catch { return; }
		if (message?.method === 'Page.loadEventFired') settle();
	};
	const onClose = () => settle(new Error(`Chrome DevTools connection closed while loading ${label}.`));
	Object.defineProperty(pending, 'cancel', {
		value: () => settle(new Error(`Cancelled loading ${label}.`))
	});
	timer = setTimeout(() => settle(new Error(`Timed out loading ${label}.`)), timeoutMs);
	try {
		socket.addEventListener('message', onMessage);
		socket.addEventListener('close', onClose);
	} catch {
		settle(new Error('Could not register Chrome page-load wait.'));
	}
	return pending;
}
