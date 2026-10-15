import { spawn } from 'node:child_process';
import { mkdtemp, mkdir, readFile, realpath, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { focusSnapshotExpression, validateFocusEvidenceOptions, writeFocusEvidence } from './browser-focus-evidence.mjs';
import { waitForPageLoad as createPageLoadWaiter } from './browser-load-wait.mjs';
import { runButtonThemeChecks } from './browser-button-themes.mjs';
import { runPracticeUnsavedChecks } from './browser-practice-unsaved.mjs';
import { loadPracticeRecoveryFixtures, runPracticeRecoveryChecks } from './browser-practice-recovery.mjs';
import { runReadingSaveChecks } from './browser-reading-save.mjs';

function readArgument(name, fallback) {
	const index = process.argv.indexOf(name);
	return index >= 0 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
}

const baseUrl = readArgument('--base-url', 'http://127.0.0.1:8081/index.php/');
const chromePath = readArgument('--chrome', process.env.STUDY_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');
const loginIdentity = process.env.STUDY_QA_LOGIN_IDENTITY || '';
const loginPassword = process.env.STUDY_QA_LOGIN_PASSWORD || '';
if (Boolean(loginIdentity) !== Boolean(loginPassword)) throw new Error('Provide both temporary QA login environment values or neither.');
let parsedBaseUrl;
try {
	parsedBaseUrl = new URL(baseUrl);
} catch {
	throw new Error('Provide an absolute local Study base URL with --base-url.');
}
if (parsedBaseUrl.protocol !== 'http:' || !['localhost', '127.0.0.1', '[::1]'].includes(parsedBaseUrl.hostname) ||
	parsedBaseUrl.username || parsedBaseUrl.password || parsedBaseUrl.search || parsedBaseUrl.hash || !parsedBaseUrl.pathname.endsWith('/')) {
	throw new Error('Browser QA accepts only an HTTP loopback base URL ending in /, without credentials/query/fragment.');
}

const routes = [
	{ name: 'home', path: '' },
	{ name: 'catalog', path: 'kursus' },
	{ name: 'sign-in', path: 'masuk' },
	{ name: 'sign-up', path: 'daftar' }
];
const extraPath = readArgument('--extra-path', '');
const extraQuery = readArgument('--extra-query', '');
const extraName = readArgument('--extra-name', 'extra-route');
if (extraQuery && !extraPath) throw new Error('--extra-query requires --extra-path.');
if (extraPath) {
	if (!/^[a-z0-9_-]+(?:\/[a-z0-9_-]+)*$/i.test(extraPath)) {
		throw new Error('An extra browser route must be a relative application path without query, fragment, or traversal.');
	}
	if (!/^[a-z0-9_-]{1,60}$/i.test(extraName)) throw new Error('An extra browser route name may contain only letters, numbers, dashes, and underscores.');
	const query = new URLSearchParams(extraQuery);
	const queryLimits = { q: 100, kategori: 10, anak: 18, status: 20, halaman: 10, mulai: 10, akhir: 10, aksi: 80, pelaku: 18 };
	if (extraQuery.length > 2000 || /[?#]/.test(extraQuery) || /%(?![a-f0-9]{2})/i.test(extraQuery)) throw new Error('Invalid extra query encoding or length.');
	for (const [key, value] of query) {
		if (!Object.hasOwn(queryLimits, key) || query.getAll(key).length !== 1 || [...value].length > queryLimits[key]) throw new Error('Extra query accepts only bounded, unique list/audit filters.');
	}
	routes.push({ name: extraName, path: extraPath + (query.size ? `?${query.toString()}` : '') });
}
if (process.argv.includes('--only-extra-route')) {
	const extraRoute = routes.find((route) => route.name === extraName);
	if (!extraRoute) throw new Error('--only-extra-route requires --extra-path.');
	routes.splice(0, routes.length, extraRoute);
}
const browserZoomArgument = readArgument('--browser-zoom', '');
if (process.argv.includes('--browser-zoom') && !['100', '200'].includes(browserZoomArgument)) throw new Error('--browser-zoom accepts only 100 or 200.');
const nativeZoom = Boolean(browserZoomArgument);
const zoomFactor = nativeZoom ? Number(browserZoomArgument) / 100 : 1;
const widths = nativeZoom ? [720, 768, 1280] : [360, 768, 1280];
const captureScreenshots = process.argv.includes('--capture-screenshots');
const exercisePracticeUnsaved = process.argv.includes('--exercise-practice-unsaved');
if (exercisePracticeUnsaved && (!/^qa_(?:practice|delivery)_child_[a-f0-9]{12}$/.test(loginIdentity) || !loginPassword ||
	!/^latihan\/percobaan\/[1-9][0-9]*$/.test(extraPath) || !process.argv.includes('--only-extra-route') || extraQuery || nativeZoom ||
	process.argv.includes('--capture-focus-evidence') || process.argv.includes('--block-page-scripts') ||
	process.argv.some(arg => arg.startsWith('--exercise-') && arg !== '--exercise-practice-unsaved'))) {
	throw new Error('Practice unsaved checks require one synthetic learner attempt without query, zoom or other exercise modes.');
}
const exerciseButtonThemes = process.argv.includes('--exercise-button-themes');
const exercisePracticeRecovery = process.argv.includes('--exercise-practice-recovery');
const exerciseReadingSave = process.argv.includes('--exercise-reading-save');
if (exerciseReadingSave && (!/^qa_progress_[a-f0-9]{16}$/.test(loginIdentity) || !/^materi\/qa-progress-lesson-[a-f0-9]{16}$/.test(extraPath) || !process.argv.includes('--only-extra-route') || extraQuery || nativeZoom || process.argv.some(arg => arg.startsWith('--exercise-') && arg !== '--exercise-reading-save'))) {
	throw new Error('Reading save exercise requires one owned synthetic progress lesson and learner, without other exercise modes.');
}
const exerciseMatchingTransactionRecovery = process.argv.includes('--exercise-matching-transaction-recovery');
const exerciseReadingRecovery = process.argv.includes('--exercise-reading-recovery');
const exerciseFlashcardRecovery = process.argv.includes('--exercise-flashcard-recovery');
const transactionRecoveryKind = exerciseFlashcardRecovery ? 'flashcard' : exerciseReadingRecovery ? 'reading' : exerciseMatchingTransactionRecovery ? 'matching' : 'practice';
let practiceRecoveryFixtures;
if (exercisePracticeRecovery || exerciseMatchingTransactionRecovery || exerciseReadingRecovery || exerciseFlashcardRecovery) {
	const allowedRecoveryMode = exerciseFlashcardRecovery ? '--exercise-flashcard-recovery' : exerciseReadingRecovery ? '--exercise-reading-recovery' : exerciseMatchingTransactionRecovery ? '--exercise-matching-transaction-recovery' : '--exercise-practice-recovery';
	if (loginIdentity || loginPassword || nativeZoom || extraPath || extraQuery || process.argv.includes('--only-extra-route') ||
		process.argv.some(arg => arg.startsWith('--exercise-') && arg !== allowedRecoveryMode) ||
		process.argv.includes('--capture-focus-evidence') || process.argv.includes('--focus-evidence-negative-control') || process.argv.includes('--block-page-scripts')) {
		throw new Error('Recovery component checks require an anonymous public run without extra routes, zoom or other exercise modes.');
	}
	practiceRecoveryFixtures = loadPracticeRecoveryFixtures({ phpPath:readArgument('--php-path', ''), baseUrl, kind:transactionRecoveryKind });
}
if (exerciseButtonThemes) {
	if (loginIdentity || loginPassword || nativeZoom || extraPath || process.argv.some(arg => arg.startsWith('--exercise-') && arg !== '--exercise-button-themes') || process.argv.includes('--capture-focus-evidence') || process.argv.includes('--block-page-scripts')) {
		throw new Error('Button component checks require an anonymous public run without extra routes, zoom or other exercise modes.');
	}
	routes.splice(0, routes.length, { name:'home', path:'' });
}
const captureFocusEvidence = process.argv.includes('--capture-focus-evidence');
const focusEvidenceNegativeControl = process.argv.includes('--focus-evidence-negative-control');
validateFocusEvidenceOptions({ enabled: captureFocusEvidence, nativeZoom, captureScreenshots, loginIdentity, loginPassword, routes });
if (focusEvidenceNegativeControl && !captureFocusEvidence) throw new Error('--focus-evidence-negative-control requires --capture-focus-evidence.');
const health = await fetch(baseUrl, { redirect: 'manual', signal: AbortSignal.timeout(10000) }).catch(() => {
	throw new Error('Study web server is unavailable at the requested loopback base URL; no browser was started.');
});
if (health.status !== 200) throw new Error(`Study home health check returned HTTP ${health.status}; no browser was started.`);
await health.body?.cancel();
const requestedScreenshotDir = readArgument('--screenshot-dir', '');
if (requestedScreenshotDir && !captureScreenshots) throw new Error('--screenshot-dir requires --capture-screenshots.');
let screenshotDir = null;
if (captureScreenshots) {
	if (requestedScreenshotDir) {
		const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
		const baselineRoot = path.resolve(projectRoot, 'docs', 'visual-baseline');
		screenshotDir = path.resolve(requestedScreenshotDir);
		const relativeScreenshotDir = path.relative(baselineRoot, screenshotDir);
		if (!relativeScreenshotDir || relativeScreenshotDir.startsWith('..') || path.isAbsolute(relativeScreenshotDir)) {
			throw new Error('--screenshot-dir must be a new subdirectory of docs/visual-baseline, not the baseline root itself.');
		}
		if (captureFocusEvidence) {
			if (relativeScreenshotDir.includes(path.sep) || (await realpath(baselineRoot)).toLowerCase() !== baselineRoot.toLowerCase()) {
				throw new Error('Focus evidence requires a new direct child of the non-linked docs/visual-baseline directory.');
			}
			await mkdir(screenshotDir); // Existing directories, including junctions, fail.
		} else await mkdir(screenshotDir, { recursive: true });
	}
	else screenshotDir = await mkdtemp(path.join(os.tmpdir(), 'study-visual-review-'));
}
const userDataDir = await mkdtemp(path.join(os.tmpdir(), 'study-browser-viewport-'));
let chrome;
let chromeSpawnError;

function delay(milliseconds) {
	return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function removeTemporaryProfile(directory) {
	const relative = path.relative(path.resolve(os.tmpdir()), path.resolve(directory));
	if (!relative || relative.includes(path.sep) || !relative.startsWith('study-browser-viewport-')) throw new Error('Unsafe browser profile cleanup path');
	let lastError;
	for (let attempt = 0; attempt < 20; attempt += 1) {
		try {
			await rm(directory, { recursive: true, force: true });
			return;
		} catch (error) {
			if (!['EBUSY', 'EPERM', 'ENOTEMPTY'].includes(error.code)) throw error;
			lastError = error;
			await delay(250);
		}
	}
	throw lastError;
}

async function waitForDevToolsPort(filePath) {
	for (let attempt = 0; attempt < 100; attempt += 1) {
		if (chromeSpawnError) throw new Error('Could not start the requested Chrome executable.');
		try {
			const lines = (await readFile(filePath, 'utf8')).trim().split(/\r?\n/);
			const port = Number(lines[0]);
			if (Number.isInteger(port) && port > 0) return port;
		} catch {
			// Chrome has not written its port file yet.
		}
		if (chromeSpawnError) throw new Error('Could not start the requested Chrome executable.');
		if (chrome.exitCode !== null) throw new Error(`Chrome exited early with status ${chrome.exitCode}.`);
		await delay(100);
	}
	throw new Error('Timed out waiting for Chrome DevTools to start.');
}

function connectCdp(webSocketUrl) {
	return new Promise((resolve, reject) => {
		const url = new URL(webSocketUrl);
		url.hostname = '127.0.0.1';
		const socket = new WebSocket(url);
		const pending = new Map();
		let nextId = 1;
		socket.addEventListener('open', () => resolve({ socket, pending, nextId: () => nextId++ }));
		socket.addEventListener('error', () => reject(new Error('Could not connect to Chrome DevTools.')));
		socket.addEventListener('message', (event) => {
			const message = JSON.parse(event.data);
			if (message.id && pending.has(message.id)) {
				const { resolve: resolveCommand, reject: rejectCommand, timer } = pending.get(message.id);
				clearTimeout(timer);
				pending.delete(message.id);
				if (message.error) rejectCommand(new Error(message.error.message));
				else resolveCommand(message.result || {});
			}
		});
		socket.addEventListener('close', () => {
			for (const entry of pending.values()) { clearTimeout(entry.timer); entry.reject(new Error('Chrome DevTools connection closed.')); }
			pending.clear();
		});
	});
}

try {
	if (nativeZoom) {
		// Chromium's default storage partition has key x (empty relative path).
		// Only the fresh test-owned profile is written; no CSS/device-metric zoom.
		await mkdir(path.join(userDataDir, 'Default'));
		await writeFile(path.join(userDataDir, 'Default', 'Preferences'), JSON.stringify({ partition: { default_zoom_level: { x: Math.log(zoomFactor) / Math.log(1.2) } } }), { flag: 'wx' });
	}
	chrome = spawn(chromePath, [
		'--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
		...(nativeZoom ? ['--force-device-scale-factor=1'] : []),
		'--remote-debugging-port=0', `--user-data-dir=${userDataDir}`, '--window-size=1280,900', 'about:blank'
	], { stdio: 'ignore', windowsHide: true });
	chrome.once('error', error => { chromeSpawnError = error; });
	const port = await waitForDevToolsPort(path.join(userDataDir, 'DevToolsActivePort'));
	let targets;
	for (let attempt = 0; attempt < 30; attempt += 1) {
		try {
			targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
			break;
		} catch {
			await delay(100);
		}
	}
	const page = targets?.find((target) => target.type === 'page' && target.webSocketDebuggerUrl);
	if (!page) throw new Error('Chrome did not expose a page target for viewport checks.');
	const client = await connectCdp(page.webSocketDebuggerUrl);
	const send = (method, params = {}) => {
		const id = client.nextId();
		return new Promise((resolve, reject) => {
			const timer = setTimeout(() => { client.pending.delete(id); reject(new Error(`Chrome DevTools command timed out: ${method}`)); }, 10000);
			client.pending.set(id, { resolve, reject, timer });
			client.socket.send(JSON.stringify({ id, method, params }));
		});
	};
	const waitForPageLoad = label => createPageLoadWaiter(client, label);
	const navigateTo = async (url, label) => {
		const loaded = waitForPageLoad(label);
		try { await Promise.all([send('Page.navigate', { url }), loaded]); }
		finally { loaded.cancel(); }
		const location = await send('Runtime.evaluate', { returnByValue: true, expression: '({ origin: location.origin, path: location.pathname })' });
		if (location.result?.value?.origin !== parsedBaseUrl.origin || !location.result?.value?.path.startsWith(parsedBaseUrl.pathname)) {
			throw new Error('Browser navigation left the local Study application; further interaction refused.');
		}
	};
	await send('Page.enable');
	await send('Runtime.enable');
	if (process.argv.includes('--block-page-scripts')) {
		await send('Network.enable');
		await send('Network.setBlockedURLs', { urls: ['*.js*'] });
	}
	if (loginIdentity && loginPassword) {
		await navigateTo(new URL('masuk', baseUrl).href, 'temporary QA sign-in page');
		const identityLiteral = JSON.stringify(loginIdentity);
		const passwordLiteral = JSON.stringify(loginPassword);
		const navigation = waitForPageLoad('temporary QA sign-in result');
		const submitted = await send('Runtime.evaluate', {
			expression: `(() => {
				const form = Array.from(document.forms).find((candidate) => candidate.querySelector('[name="identity"]') && candidate.querySelector('[name="password"]'));
				if (!form || new URL(form.action).origin !== location.origin) return false;
				form.querySelector('[name="identity"]').value = ${identityLiteral};
				form.querySelector('[name="password"]').value = ${passwordLiteral};
				form.requestSubmit();
				return true;
			})()`
		});
		if (submitted.result?.value !== true) throw new Error('Temporary QA credentials could not be submitted through the application sign-in form.');
		await navigation;
		const loginCheck = await send('Runtime.evaluate', {
			returnByValue: true,
			expression: `({ signedIn: !Array.from(document.forms).some((form) => form.querySelector('[name="password"]')) })`
		});
		if (!loginCheck.result?.value?.signedIn) throw new Error('Temporary QA sign-in did not reach an authenticated page.');
	}
	const results = [];
	const screenshots = [];
	const browserVersion = (await send('Browser.getVersion')).product;
	const nativeWindow = nativeZoom ? await send('Browser.getWindowForTarget', { targetId: page.id }) : null;

	for (const width of widths) {
		if (nativeZoom) await send('Browser.setContentsSize', { windowId: nativeWindow.windowId, width, height: 900 });
		else await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
		for (const route of routes) {
			await navigateTo(new URL(route.path, baseUrl).href, `${route.name} at ${width}px`);
			await delay(100);
			if (process.argv.includes('--open-details')) await send('Runtime.evaluate', { expression: "document.querySelectorAll('details:not([open]) > summary').forEach(summary => summary.click()); true" });
			let navigationCheck;
			if (process.argv.includes('--exercise-navigation')) {
				await send('Runtime.evaluate', { expression: `(() => { const button = document.querySelector('.navbar-toggler'); if (button && button.getBoundingClientRect().width > 0 && button.getAttribute('aria-expanded') === 'false') button.click(); })()` });
				await delay(500);
				const navigation = await send('Runtime.evaluate', { returnByValue: true, expression: `(() => {
					const links = [...document.querySelectorAll('.site-header a[aria-current]')];
					const button = document.querySelector('.navbar-toggler');
					const menu = document.getElementById('navigasi-utama');
					return { enhanced: menu?.dataset.navigationEnhanced === 'true', bootstrapReady: typeof window.bootstrap?.Collapse === 'function', toggleHidden: button?.hidden, allMenuLinksVisible: !!menu && [...menu.querySelectorAll('a')].every(link => link.getBoundingClientRect().width > 0 && link.getBoundingClientRect().height > 0), menuExpanded: !button || button.getBoundingClientRect().width === 0 || button.getAttribute('aria-expanded') === 'true', links: links.map(link => {
						const style = getComputedStyle(link); const rect = link.getBoundingClientRect();
						return { state: link.getAttribute('aria-current'), visible: rect.width > 0 && rect.height > 0, underline: style.textDecorationLine.includes('underline'), thickness: parseFloat(style.textDecorationThickness), weight: parseInt(style.fontWeight, 10) };
					}) };
				})()` });
				navigationCheck = navigation.result?.value;
				const expectEnhancedNavigation = !process.argv.includes('--block-page-scripts');
				if (navigationCheck?.enhanced !== expectEnhancedNavigation || navigationCheck.bootstrapReady !== expectEnhancedNavigation || navigationCheck.toggleHidden === expectEnhancedNavigation || !navigationCheck.allMenuLinksVisible) throw new Error('Navigation enhancement/fallback state mismatch: ' + JSON.stringify(navigationCheck));
				if (!navigationCheck?.menuExpanded || navigationCheck.links.length !== 1 || navigationCheck.links.some(link => !['page', 'location'].includes(link.state) || !link.visible || !link.underline || link.thickness < 2 || link.weight < 700)) throw new Error('Current navigation must be visible and distinguished without color alone: ' + JSON.stringify(navigationCheck));
			}
			await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
			await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
			const evaluation = await send('Runtime.evaluate', {
				returnByValue: true,
					expression: `(() => {
					const viewport = window.innerWidth;
					const skipLink = document.querySelector('.skip-link');
					const skipRect = skipLink?.getBoundingClientRect();
					const keyboardStart = document.activeElement === skipLink && skipRect && skipRect.top >= 0 && skipRect.bottom <= window.innerHeight;
					const getVisibleHorizontalBounds = (element) => {
						const rect = element.getBoundingClientRect();
						let left = rect.left;
						let right = rect.right;
						for (let ancestor = element.parentElement; ancestor && right > left; ancestor = ancestor.parentElement) {
							const overflowX = getComputedStyle(ancestor).overflowX;
							if (!['hidden', 'clip', 'auto', 'scroll'].includes(overflowX)) continue;
							const ancestorRect = ancestor.getBoundingClientRect();
							left = Math.max(left, ancestorRect.left);
							right = Math.min(right, ancestorRect.right);
						}
						return { left, right, width: Math.max(0, right - left) };
					};
					const clipProbe = document.createElement('div');
					clipProbe.style.cssText = 'position:fixed;left:0;top:0;width:20px;height:10px;overflow-x:clip;';
					const clippedChild = document.createElement('div');
					clippedChild.style.cssText = 'position:absolute;left:10px;top:0;width:30px;height:5px;';
					clipProbe.appendChild(clippedChild);
					document.body.appendChild(clipProbe);
					const clippedBounds = getVisibleHorizontalBounds(clippedChild);
					const isOutsideViewport = (bounds) => bounds.width > 0 && (bounds.right > viewport + 1 || bounds.left < -1);
					const geometrySelfTests = {
						clippedChildIgnored: !isOutsideViewport(clippedBounds) && Math.round(clippedBounds.width) === 10,
						unclippedOverflowDetected: isOutsideViewport({ left: viewport + 10, right: viewport + 30, width: 20 })
					};
					clipProbe.remove();
					const offenders = Array.from(document.querySelectorAll('body *')).map((element) => {
						const bounds = getVisibleHorizontalBounds(element);
						return { tag: element.tagName.toLowerCase(), id: element.id, className: typeof element.className === 'string' ? element.className : '', left: Math.round(bounds.left), right: Math.round(bounds.right), width: Math.max(0, Math.round(bounds.width)), text: (element.innerText || '').trim().slice(0, 48) };
					}).filter((item) => item.width > 0 && (item.right > viewport + 1 || item.right < -1)).slice(0, 12);
					const accessibilityIssues = [];
					const findClippedText = root => {
						const clippedText = [];
						const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
						while (walker.nextNode()) {
							const node = walker.currentNode;
							const parent = node.parentElement;
							if (!node.textContent.trim() || parent.closest('script, style, [hidden], [aria-hidden="true"], .visually-hidden, .visually-hidden-focusable') || getComputedStyle(parent).visibility !== 'visible') continue;
							const range = document.createRange(); range.selectNodeContents(node);
							const lines = [...range.getClientRects()].filter(rect => rect.width > 0 && rect.height > 0);
							let clipped = false;
							for (let ancestor = parent; ancestor && !clipped; ancestor = ancestor.parentElement) {
								const style = getComputedStyle(ancestor);
								const bounds = ancestor.getBoundingClientRect();
								clipped = lines.some(line => (['hidden', 'clip'].includes(style.overflowX) && (line.left < bounds.left - 2 || line.right > bounds.right + 2)) || (['hidden', 'clip'].includes(style.overflowY) && (line.top < bounds.top - 2 || line.bottom > bounds.bottom + 2)));
							}
							if (clipped) clippedText.push(node.textContent.trim().slice(0, 60));
						}
						return clippedText;
					};
					const clippedText = ${nativeZoom} ? findClippedText(document.body) : [];
					if (${nativeZoom}) {
						const textProbe = document.createElement('div');
						textProbe.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;overflow:hidden;';
						textProbe.textContent = 'Study zoom clipping probe'; document.body.append(textProbe);
						geometrySelfTests.clippedTextDetected = findClippedText(textProbe).length === 1;
						textProbe.setAttribute('aria-hidden', 'true');
						geometrySelfTests.decorativeTextIgnored = findClippedText(textProbe).length === 0;
						textProbe.removeAttribute('aria-hidden'); textProbe.style.cssText = 'position:fixed;left:0;top:0;width:300px;height:auto;overflow:visible;';
						geometrySelfTests.visibleTextAccepted = findClippedText(textProbe).length === 0;
						textProbe.remove();
					}
					for (const element of document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea')) {
						if (!element.labels?.length && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby')) accessibilityIssues.push('Form control tanpa label: ' + element.outerHTML.slice(0, 100));
					}
					const hiddenByClosedDetails = (element) => {
						for (let parent = element.parentElement; parent; parent = parent.parentElement) {
							if (parent.matches('details:not([open])') && !parent.querySelector(':scope > summary')?.contains(element)) return true;
						}
						return false;
					};
					const detailProbe = document.createElement('details');
					const summaryProbe = document.createElement('summary'); summaryProbe.textContent = 'Synthetic QA summary';
					const detailButton = document.createElement('button'); detailProbe.append(summaryProbe, detailButton); document.body.append(detailProbe);
					geometrySelfTests.closedDetailsActionHidden = hiddenByClosedDetails(detailButton) && !hiddenByClosedDetails(summaryProbe);
					detailProbe.open = true;
					geometrySelfTests.openDetailsActionChecked = !hiddenByClosedDetails(detailButton) && !detailButton.innerText.trim();
					detailProbe.remove();
					for (const element of document.querySelectorAll('button, a')) {
						if (hiddenByClosedDetails(element)) continue;
						if (!(element.innerText || '').trim() && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby') && !element.title) accessibilityIssues.push('Tombol/tautan tanpa nama: ' + element.outerHTML.slice(0, 100));
					}
					for (const element of document.querySelectorAll('img:not([alt])')) {
						if (element.getAttribute('aria-hidden') !== 'true') accessibilityIssues.push('Gambar tanpa atribut alt: ' + element.outerHTML.slice(0, 100));
					}
					for (const element of document.querySelectorAll('[role="img"]')) {
						const label = element.getAttribute('aria-label')?.trim();
						const labelledBy = element.getAttribute('aria-labelledby')?.trim();
						const labelledByText = labelledBy && labelledBy.split(/\\s+/).map((id) => document.getElementById(id)?.textContent.trim() || '').join(' ').trim();
						if (!label && !labelledByText) accessibilityIssues.push('Ilustrasi tanpa nama aksesibel: ' + element.outerHTML.slice(0, 100));
					}
					for (const element of document.querySelectorAll('[tabindex]')) {
						if (Number(element.getAttribute('tabindex')) > 0) accessibilityIssues.push('Urutan fokus manual bernilai positif: ' + element.outerHTML.slice(0, 100));
					}
					const selectors = ['.container', '.hero-section .row', '.hero-section .col-lg-7', '.hero-title', '.hero-copy', '.auth-card'];
					const probes = selectors.map((selector) => {
						const element = document.querySelector(selector);
						if (!element) return null;
						const rect = element.getBoundingClientRect();
						const style = getComputedStyle(element);
						const range = document.createRange();
						range.selectNodeContents(element);
						const textLines = Array.from(range.getClientRects()).map((line) => ({ left: Math.round(line.left), right: Math.round(line.right), top: Math.round(line.top), width: Math.round(line.width) }));
						return { selector, left: Math.round(rect.left), right: Math.round(rect.right), width: Math.round(rect.width), scrollWidth: element.scrollWidth, clientWidth: element.clientWidth, fontSize: style.fontSize, overflowWrap: style.overflowWrap, textLines };
					}).filter(Boolean);
					return { route: location.pathname, title: document.title, viewport, clientWidth: document.documentElement.clientWidth, documentWidth: document.documentElement.scrollWidth, bodyWidth: document.body.scrollWidth, offenders, accessibilityIssues, keyboardStart, geometrySelfTests, probes, clippedText };
				})()`
			});
			const result = evaluation.result?.value;
			if (!result || result.title === 'Not Found') throw new Error(`Route ${route.name} did not render at ${width}px.`);
			result.navigationCheck = navigationCheck;
			if (nativeZoom) {
				const measurement = await send('Runtime.evaluate', { returnByValue: true, expression: '({ dpr: devicePixelRatio, pinch: visualViewport.scale, cssZoom: getComputedStyle(document.documentElement).zoom })' });
				const layout = await send('Page.getLayoutMetrics');
				const zoomEvidence = { ...measurement.result.value, browserZoom: layout.cssVisualViewport.zoom };
				if (!Number.isFinite(zoomEvidence.browserZoom) || !Number.isFinite(zoomEvidence.dpr) || Math.abs(zoomEvidence.dpr - zoomFactor) > .01 || zoomEvidence.pinch !== 1 || zoomEvidence.cssZoom !== '1' || Math.abs(zoomEvidence.browserZoom - zoomFactor) > .01) throw new Error('Native page zoom was not proven: ' + JSON.stringify(zoomEvidence));
				result.zoomEvidence = zoomEvidence;
				const focusChecks = [];
				const focusReport = { schemaVersion: 1, browserVersion, route: route.name, browserContentWidth: width, zoomEvidence, navigationCheck, negativeControl: focusEvidenceNegativeControl, steps: [] };
				const readFocusSnapshot = async () => {
					const snapshot = await send('Runtime.evaluate', { returnByValue: true, expression: focusSnapshotExpression });
					if (snapshot.exceptionDetails || !snapshot.result?.value) throw new Error('Unable to inspect native zoom keyboard focus');
					return snapshot.result.value;
				};
				const persistFocusEvidence = async (failed) => {
					if (!captureFocusEvidence) return;
					const actualPath = await send('Runtime.evaluate', { returnByValue: true, expression: '({ origin: location.origin, path: location.pathname })' });
					if (actualPath.result?.value?.origin !== parsedBaseUrl.origin || actualPath.result?.value?.path !== new URL(route.path, baseUrl).pathname) throw new Error('Focus evidence route changed; capture refused.');
					const artifacts = await writeFocusEvidence({
						directory: screenshotDir,
						stem: `${route.name}-${width}-zoom${browserZoomArgument}-focus-${failed ? 'failed' : 'passed'}`,
						report: { ...focusReport, status: failed ? 'failed' : 'passed' },
						captureScreenshot: failed ? async () => (await send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false })).data : undefined
					});
					console.log(JSON.stringify({ focusEvidence: artifacts, directory: screenshotDir }));
					if (artifacts.screenshot.status === 'failed') console.error('Focus failure screenshot could not be saved; JSON and failing status retained.');
				};
				try {
				await send('Runtime.evaluate', { expression: 'window.studyQaFocusSeen = new WeakSet(); window.studyQaFocusSeen.add(document.activeElement); true' });
				for (let step = 0; step < 100; step++) {
					const trace = { step, beforeTab: captureFocusEvidence ? await readFocusSnapshot() : undefined, scrollSamples: [] };
					focusReport.steps.push(trace);
					await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
					await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
					// Poll from the test process: page animation frames may stop when
					// Tab moves focus into browser chrome, outside the document.
					const scrollStarted = Date.now();
					let stableSince = scrollStarted;
					let previousPosition = null;
					let scrollSettled = false;
					while (Date.now() - scrollStarted < 3000) {
						const sample = await send('Runtime.evaluate', { returnByValue: true, expression: captureFocusEvidence ? focusSnapshotExpression : '[scrollX, scrollY, document.activeElement === document.body]' });
						const observed = sample.result?.value;
						const position = captureFocusEvidence && observed ? [observed.scroll.x, observed.scroll.y, observed.outsidePage] : observed;
						if (!Array.isArray(position) || !Number.isFinite(position[0]) || !Number.isFinite(position[1])) throw new Error('Unable to measure keyboard scroll position');
						if (captureFocusEvidence) trace.scrollSamples.push({ elapsedMs: Date.now() - scrollStarted, ...observed });
						if (position[2]) { scrollSettled = true; break; }
						if (!previousPosition || Math.abs(previousPosition[0] - position[0]) > .1 || Math.abs(previousPosition[1] - position[1]) > .1) stableSince = Date.now();
						else if (Date.now() - stableSince >= 150) { scrollSettled = true; break; }
						previousPosition = position;
						await delay(50);
					}
					trace.scrollSettled = scrollSettled;
					if (!scrollSettled) throw new Error(`Keyboard scrolling did not settle: ${route.name}, ${width}px, step ${step}`);
					if (focusEvidenceNegativeControl && step === 0) {
						// Synthetic DOM-only detector test: do not alter application CSS.
						await send('Runtime.evaluate', { expression: "document.activeElement.style.setProperty('outline', 'none', 'important'); true" });
					}
					const focus = await send('Runtime.evaluate', { returnByValue: true, expression: `(() => {
						const snapshot = ${focusSnapshotExpression};
						const element = document.activeElement;
						if (element === document.body) return snapshot;
						if (window.studyQaFocusSeen.has(element)) return { ...snapshot, cycled: true };
						window.studyQaFocusSeen.add(element);
						return { ...snapshot, cycled: false };
					})()` });
					if (!focus.result?.value) throw new Error('Unable to inspect native zoom keyboard focus');
					const entry = focus.result.value;
					trace.afterTab = entry;
					if (entry.cycled) break;
					if (!entry.outsidePage) focusChecks.push(entry);
					if (!entry.outsidePage && (!entry.visible || !entry.focusIndicator)) throw new Error(`Keyboard focus obscured at browser zoom: ${route.name}, ${width}px, step ${step}; ` + JSON.stringify(entry));
					if (step === 99) throw new Error('Keyboard focus did not cycle within 100 tabs');
				}
				if (!focusChecks.length || focusChecks.some(entry => !entry.visible || !entry.focusIndicator)) throw new Error(`Keyboard focus obscured at browser zoom: ${route.name}, ${width}px; ` + JSON.stringify(focusChecks.filter(entry => !entry.visible || !entry.focusIndicator)));
				await persistFocusEvidence(false);
				} catch (error) {
					// Capture before any next Tab, scroll reset, blur or browser close.
					try { focusReport.failureSnapshot = await readFocusSnapshot(); } catch { focusReport.snapshotFailed = true; }
					try { await persistFocusEvidence(true); } catch { console.error('Focus evidence could not be saved; original failure retained.'); }
					throw error;
				}
				await send('Runtime.evaluate', { expression: 'delete window.studyQaFocusSeen; window.scrollTo(0, 0); true' });
				result.keyboardControlsChecked = focusChecks.length;
				if (['sign-in', 'sign-up'].includes(route.name)) {
					const validation = await send('Runtime.evaluate', { returnByValue: true, expression: `(() => { const form = [...document.forms].find(form => form.querySelector('[name="password"]')); return form && { valid: form.reportValidity(), focusedInvalid: document.activeElement.matches(':invalid') }; })()` });
					if (validation.result?.value?.valid !== false || !validation.result?.value?.focusedInvalid) throw new Error('Empty authentication form did not focus an invalid control at native zoom');
					await send('Runtime.evaluate', { expression: 'document.activeElement.blur(); window.scrollTo(0, 0); true' });
				}
			}
			results.push({ requestedWidth: width / zoomFactor, browserContentWidth: nativeZoom ? width : undefined, ...result });
			if (captureScreenshots && (width === widths[0] || width === 1280)) {
				await send('Runtime.evaluate', { expression: 'if (document.activeElement && document.activeElement.blur) document.activeElement.blur(); true' });
				await send('Runtime.evaluate', { expression: 'document.fonts.ready', awaitPromise: true });
				const metrics = await send('Page.getLayoutMetrics');
				const pageSize = nativeZoom ? metrics.contentSize : (metrics.cssContentSize || metrics.contentSize);
				const pageHeight = Math.max(1, Math.min(8000, Math.ceil(pageSize.height)));
				const capture = await send('Page.captureScreenshot', {
					format: 'png',
					fromSurface: true,
					captureBeyondViewport: true,
					clip: { x: 0, y: 0, width, height: pageHeight, scale: 1 }
				});
				const screenshotPath = path.join(screenshotDir, `${route.name}-${width}${nativeZoom ? '-zoom' + browserZoomArgument : ''}.png`);
				await writeFile(screenshotPath, Buffer.from(capture.data, 'base64'), { flag: 'wx' });
				screenshots.push(screenshotPath);
			}
			if (exercisePracticeUnsaved) console.log(JSON.stringify({ practiceUnsaved: await runPracticeUnsavedChecks({ send, client, navigateTo, waitForPageLoad, baseUrl, route, width, screenshotDir }) }));
			if (exerciseReadingSave) console.log(JSON.stringify({ readingSave: await runReadingSaveChecks({ send, client, navigateTo, waitForPageLoad, baseUrl, route, width }) }));
			if (process.argv.includes('--exercise-saved-matching-recovery')) {
				const evaluate = async expression => {
					const result = await send('Runtime.evaluate', { returnByValue: true, expression });
					if (result.exceptionDetails) throw new Error('Recovery browser assertion failed: ' + (result.exceptionDetails.exception?.description || 'unknown'));
					return result.result?.value;
				};
				const currentRevision = () => evaluate(`Number(document.querySelector('#saved-matching-form [name="revision"]')?.value)`);
				const chooseWrong = () => evaluate(`(() => {
					const form = document.getElementById('saved-matching-form');
					const left = form.querySelector('[name="left"]'); left.click();
					Array.from(form.querySelectorAll('[name="right"]')).find(input => input.value !== left.value).click();
					form.querySelector('button[type="submit"]').click(); return true;
				})()`);
				const awaitRecovery = async () => {
					for (let attempt = 0; attempt < 100; attempt++) {
						const ready = await evaluate(`(() => { const link = document.getElementById('matching-save-recovery'); return link && !link.hidden && document.querySelector('#saved-matching-form button').disabled && document.getElementById('matching-save-state').textContent.includes('mungkin sudah tersimpan'); })()`);
						if (ready) return;
						await delay(100);
					}
					throw new Error('Uncertain-save message and recovery link were not shown.');
				};
				const recover = async () => {
					const loaded = waitForPageLoad('recovery GET');
					await evaluate(`document.getElementById('matching-save-recovery').click(); true`);
					await loaded;
				};
				const before = await currentRevision();
				await send('Network.enable');
				await send('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0 });
				try { await chooseWrong(); await awaitRecovery(); }
				finally { await send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 }); }
				await recover();
				if (await currentRevision() !== before) throw new Error('Offline click unexpectedly wrote a choice.');
				let failedAfterCommit = false; let interceptionError = null;
				const intercept = event => {
					const message = JSON.parse(event.data);
					if (message.method !== 'Fetch.requestPaused') return;
					const paused = message.params;
					if (paused.request.method !== 'POST') { send('Fetch.continueRequest', { requestId: paused.requestId }).catch(error => { interceptionError = error; }); return; }
					failedAfterCommit = paused.responseStatusCode === 303;
					send('Fetch.failRequest', { requestId: paused.requestId, errorReason: 'ConnectionClosed' }).catch(error => { interceptionError = error; });
				};
				client.socket.addEventListener('message', intercept);
				await send('Fetch.enable', { patterns: [{ urlPattern: '*index.php/permainan/tersimpan/*', requestStage: 'Response' }] });
				try {
					await chooseWrong(); await awaitRecovery();
					if (interceptionError) throw interceptionError;
					if (!failedAfterCommit || await currentRevision() !== before) throw new Error('Lost response boundary or no-optimistic-state check failed.');
					if (captureScreenshots && (width === 360 || width === 1280)) {
						const metrics = await send('Page.getLayoutMetrics');
						const pageSize = metrics.cssContentSize || metrics.contentSize;
						const capture = await send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: Math.min(8000, Math.ceil(pageSize.height)), scale: 1 } });
						const screenshotPath = path.join(screenshotDir, `${route.name}-uncertain-${width}.png`);
						await writeFile(screenshotPath, Buffer.from(capture.data, 'base64'), { flag: 'wx' }); screenshots.push(screenshotPath);
					}
				} finally { await send('Fetch.disable'); client.socket.removeEventListener('message', intercept); }
				await recover();
				if (await currentRevision() !== before + 1) throw new Error('Recovery GET did not show exactly one committed choice.');
				console.log(JSON.stringify({ savedMatchingRecovery: { offlineNoWrite: true, responseLostAfter303: true, uncertainMessage: true, freshGetRecoveredOneChoice: true }, width }));
			}
			if (process.argv.includes('--exercise-saved-matching-navigation')) {
				const evaluate = async expression => {
					const value = await send('Runtime.evaluate', { returnByValue: true, expression });
					if (value.exceptionDetails) throw new Error('Navigation probe failed: ' + (value.exceptionDetails.exception?.description || 'unknown'));
					return value.result?.value;
				};
				const waitUntil = async (probe, label) => {
					for (let attempt = 0; attempt < 100; attempt++) {
						if (await probe()) return;
						await delay(100);
					}
					throw new Error('Timed out waiting for ' + label);
				};
				const runUrl = new URL(route.path, baseUrl).href;
				const historyUrl = new URL('permainan/riwayat', baseUrl).href;
				await navigateTo(historyUrl, 'navigation origin');
				await navigateTo(runUrl, 'navigation run');
				const before = await evaluate(`Number(document.querySelector('#saved-matching-form [name="revision"]').value)`);
				if (!Number.isInteger(before)) throw new Error('Navigation fixture revision missing');
				let pausedPost = null; let interceptionError = null; let postCount = 0;
				const intercept = event => {
					const message = JSON.parse(event.data);
					if (message.method !== 'Fetch.requestPaused') return;
					const paused = message.params;
					if (paused.request.method === 'POST') { pausedPost = paused; postCount++; }
					else send('Fetch.continueRequest', { requestId: paused.requestId }).catch(error => { interceptionError = error; });
				};
				client.socket.addEventListener('message', intercept);
				try {
					for (const mode of ['back-forward', 'refresh']) {
						pausedPost = null;
						await send('Fetch.enable', { patterns: [{ urlPattern: '*index.php/permainan/tersimpan/*', requestStage: 'Response' }] });
						await evaluate(`(() => { const form = document.getElementById('saved-matching-form'); const left = form.querySelector('[name="left"]'); left.click(); Array.from(form.querySelectorAll('[name="right"]')).find(input => input.value !== left.value).click(); form.querySelector('button[type="submit"]').click(); return true; })()`);
						await waitUntil(() => pausedPost !== null, 'committed POST response');
						if (pausedPost.responseStatusCode !== 303 || interceptionError) throw new Error('Navigation did not hold a committed 303 response');
						if (!await evaluate(`document.getElementById('saved-matching-form').getAttribute('aria-busy') === 'true'`)) throw new Error('Navigation was not exercised during an in-flight submission');
						if (mode === 'back-forward') {
							const history = await send('Page.getNavigationHistory');
							const previous = history.entries[history.currentIndex - 1];
							const current = history.entries[history.currentIndex];
							if (!previous || previous.url !== historyUrl) throw new Error('History origin was not the game history page');
							await send('Page.navigateToHistoryEntry', { entryId: previous.id });
							await waitUntil(() => evaluate(`location.href === ${JSON.stringify(historyUrl)} && document.readyState === 'complete'`), 'Back navigation');
							await send('Fetch.disable');
							await send('Page.navigateToHistoryEntry', { entryId: current.id });
						} else {
							await send('Page.reload');
						}
						const expected = before + (mode === 'back-forward' ? 1 : 2);
						await waitUntil(() => evaluate(`(() => { const form = document.getElementById('saved-matching-form'); return form && Number(form.querySelector('[name="revision"]').value) === ${expected} && !form.querySelector('button[type="submit"]').disabled && document.readyState === 'complete'; })()`), mode + ' fresh revision');
						await send('Fetch.disable');
					}
					if (postCount !== 2 || interceptionError) throw new Error('History navigation unexpectedly replayed a POST');
					console.log(JSON.stringify({ savedMatchingNavigation: { backForwardDuringPost: true, refreshDuringPost: true, twoPostsTwoRevisions: true, editableFreshForm: true }, width }));
				} finally { await send('Fetch.disable'); client.socket.removeEventListener('message', intercept); }
			}
			if (process.argv.includes('--exercise-saved-matching')) {
				const before = await send('Runtime.evaluate', { returnByValue: true, expression: `(() => {
					const form = document.getElementById('saved-matching-form');
					if (!form) throw new Error('Saved matching form missing');
					form.querySelector('[name="left"]').focus();
					return Number(form.querySelector('[name="revision"]').value);
				})()` });
				if (!Number.isInteger(before.result?.value)) throw new Error('Missing saved matching revision');
				await send('Input.dispatchKeyEvent', { type: 'keyDown', key: ' ', code: 'Space', windowsVirtualKeyCode: 32, text: ' ', unmodifiedText: ' ' });
				await send('Input.dispatchKeyEvent', { type: 'keyUp', key: ' ', code: 'Space', windowsVirtualKeyCode: 32 });
				const selection = await send('Runtime.evaluate', { returnByValue: true, expression: `(() => {
					const form = document.getElementById('saved-matching-form');
					const left = form.querySelector('[name="left"]:checked');
					if (!left) throw new Error('Native keyboard radio selection failed');
					Array.from(form.querySelectorAll('[name="right"]')).find(input => input.value !== left.value).click();
					form.querySelector('button[type="submit"]').click();
					return true;
				})()` });
				if (selection.result?.value !== true) throw new Error('Saved matching native submission failed');
				let saved = false;
				for (let attempt = 0; attempt < 100; attempt++) {
					await delay(100);
					const current = await send('Runtime.evaluate', { returnByValue: true, expression: `({revision:Number(document.querySelector('#saved-matching-form [name="revision"]')?.value), message:document.getElementById('hasil-pilihan')?.textContent})` });
					if (current.result?.value?.revision === before.result.value + 1 && current.result.value.message?.includes('Belum cocok')) { saved = true; break; }
				}
				if (!saved) throw new Error('Saved mismatch did not reach server-rendered result');
				await navigateTo(new URL(route.path, baseUrl).href, 'saved matching refresh');
				const resumed = await send('Runtime.evaluate', { returnByValue: true, expression: `Number(document.querySelector('#saved-matching-form [name="revision"]')?.value)` });
				if (resumed.result?.value !== before.result.value + 1) throw new Error('Refresh lost saved matching revision');
				console.log(JSON.stringify({ savedMatchingInteraction: { keyboardRadio: true, postedToServer: true, refreshPreservedRevision: true }, width }));
			}
			if (process.argv.includes('--exercise-matching')) {
				await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
				await send('Runtime.evaluate', { expression: "document.querySelector('#matching-left button')?.focus()" });
				await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r', unmodifiedText: '\r' });
				await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
				const exercise = await send('Runtime.evaluate', { returnByValue: true, expression: `(() => {
					const check = (ok, name) => { if (!ok) throw new Error(name); };
					const root = document.getElementById('matching-game');
					check(root && !root.hidden, 'Game did not initialize');
					const first = document.querySelector('#matching-left button');
					check(first.getAttribute('aria-pressed') === 'true', 'Keyboard Enter did not select');
					Array.from(document.querySelectorAll('#matching-right button')).find(b => b.dataset.cardId !== first.dataset.cardId).click();
					const progress = document.getElementById('matching-progress');
					check(progress.value === 0 && document.getElementById('matching-message').textContent.includes('Belum cocok'), 'Mismatch changed progress');
					let total = 0; let rounds = 1; let done = false;
					for (let step = 0; step < 150; step++) {
						const left = document.querySelector('#matching-left button:not(:disabled)');
						if (!left) {
							const next = document.getElementById('matching-next');
							if (next.hidden) { done = true; break; }
							next.click(); rounds++; continue;
						}
						left.click();
						Array.from(document.querySelectorAll('#matching-right button')).find(b => b.dataset.cardId === left.dataset.cardId).click();
						const value = progress.value; left.click();
						check(progress.value === value && left.disabled && left.textContent.startsWith('✓ Cocok:'), 'Repeat or match state invalid');
						check(parseFloat(getComputedStyle(left).animationDuration) <= .001, 'Reduced motion not honored');
						total++;
					}
					check(done && rounds >= 2 && total === JSON.parse(document.getElementById('matching-data').textContent).length, 'Not all rounds completed exactly once');
					check(document.getElementById('matching-message').textContent.includes('Semua babak selesai'), 'Completion message missing');
					document.getElementById('matching-restart').click();
					check(progress.value === 0 && document.querySelectorAll('.matching-choice:disabled').length === 0, 'Restart did not clear progress');
					return { total, rounds, keyboard: true, reducedMotion: true, restarted: true };
				})()` });
				if (!exercise.result?.value?.restarted) throw new Error('Matching interaction failed: ' + (exercise.exceptionDetails?.exception?.description || 'unknown'));
				console.log(JSON.stringify({ matchingInteraction: exercise.result.value, width }));
				await send('Emulation.setEmulatedMedia', { features: [] });
			}
		}
	}
	if (exerciseButtonThemes) console.log(JSON.stringify({ buttonThemes: await runButtonThemeChecks({ send, navigateTo, baseUrl, screenshotDir }) }, null, 2));
	if (exercisePracticeRecovery || exerciseMatchingTransactionRecovery || exerciseReadingRecovery || exerciseFlashcardRecovery) console.log(JSON.stringify({ transactionRecovery: await runPracticeRecoveryChecks({ send, navigateTo, baseUrl, screenshotDir, fixtures:practiceRecoveryFixtures, kind:transactionRecoveryKind }) }, null, 2));
	client.socket.close();

	const failures = results.filter((result) => result.viewport !== result.requestedWidth || result.documentWidth > result.viewport + 1 || result.bodyWidth > result.viewport + 1 || (nativeZoom && (result.clippedText.length || result.offenders.length)));
	const geometrySelfTestFailures = results.filter((result) => !result.geometrySelfTests?.clippedChildIgnored || !result.geometrySelfTests?.unclippedOverflowDetected || !result.geometrySelfTests.closedDetailsActionHidden || !result.geometrySelfTests.openDetailsActionChecked || (nativeZoom && (!result.geometrySelfTests.clippedTextDetected || !result.geometrySelfTests.decorativeTextIgnored || !result.geometrySelfTests.visibleTextAccepted)));
	const summary = results.map((result) => ({
		route: result.route,
		viewport: result.requestedWidth,
		browserContentWidth: result.browserContentWidth,
		zoomEvidence: result.zoomEvidence,
		clippedText: result.clippedText,
		keyboardControlsChecked: result.keyboardControlsChecked,
		navigationCheck: result.navigationCheck,
		layoutWidth: result.documentWidth,
		bodyWidth: result.bodyWidth,
		overflowingElements: result.offenders.length,
		accessibilityIssues: result.accessibilityIssues,
		skipLinkKeyboardFocus: result.keyboardStart
	}));
	const accessibilityFailures = results.filter((result) => result.accessibilityIssues.length > 0);
	const keyboardFailures = results.filter((result) => !result.keyboardStart);
	console.log(JSON.stringify({ browserVersion, summary, failures, accessibilityFailures, keyboardFailures, geometrySelfTestFailures: geometrySelfTestFailures.map((result) => ({ route: result.route, viewport: result.requestedWidth, checks: result.geometrySelfTests })), screenshots }, null, 2));
	if (failures.length) process.exitCode = 1;
	if (accessibilityFailures.length) process.exitCode = 1;
	if (keyboardFailures.length) process.exitCode = 1;
	if (geometrySelfTestFailures.length) process.exitCode = 1;
} finally {
	if (chrome?.pid && chrome.exitCode === null && chrome.signalCode === null) {
		const closed = new Promise((resolve) => chrome.once('exit', () => resolve(true)));
		chrome.kill();
		let exited = await Promise.race([closed, delay(5000).then(() => false)]);
		if (!exited && chrome.exitCode === null && chrome.signalCode === null) {
			chrome.kill('SIGKILL');
			exited = await Promise.race([closed, delay(5000).then(() => false)]);
		}
		if (!exited && chrome.exitCode === null && chrome.signalCode === null) throw new Error('Test Chrome did not stop; its profile was retained.');
	}
	await removeTemporaryProfile(userDataDir);
}
