import { writeFile } from 'node:fs/promises';
import path from 'node:path';

// Deliberately excludes values, labels, text, HTML, URLs, cookies and storage.
// This expression observes focus without focusing, scrolling or repairing it.
export const focusSnapshotExpression = `(() => {
	const element = document.activeElement;
	const describe = node => node ? { tag: node.tagName, id: node.id || '', type: node.getAttribute('type') || '', index: Array.prototype.indexOf.call(document.getElementsByTagName(node.tagName), node) } : null;
	const rect = element.getBoundingClientRect();
	const style = getComputedStyle(element);
	const center = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
	const x = Math.max(0, Math.min(innerWidth - 1, center.x));
	const y = Math.max(0, Math.min(innerHeight - 1, center.y));
	const hit = document.elementFromPoint(x, y);
	return {
		element: describe(element), outsidePage: element === document.body,
		documentFocused: document.hasFocus(), visibilityState: document.visibilityState,
		fontsStatus: document.fonts ? document.fonts.status : 'unavailable',
		focusVisible: element.matches(':focus-visible'), focused: element.matches(':focus'),
		rect: { top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right, width: rect.width, height: rect.height },
		center, point: { x, y }, hit: describe(hit),
		hitStack: document.elementsFromPoint(x, y).slice(0, 8).map(describe),
		outline: { style: style.outlineStyle, width: style.outlineWidth, offset: style.outlineOffset, color: style.outlineColor },
		scroll: { x: scrollX, y: scrollY },
		viewport: { width: innerWidth, height: innerHeight, clientWidth: document.documentElement.clientWidth, clientHeight: document.documentElement.clientHeight, dpr: devicePixelRatio },
		visualViewport: visualViewport ? { width: visualViewport.width, height: visualViewport.height, top: visualViewport.offsetTop, left: visualViewport.offsetLeft, scale: visualViewport.scale } : null,
		visible: rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.left < innerWidth && rect.bottom > 0 && rect.top < innerHeight && (element === hit || element.contains(hit)),
		focusIndicator: style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0
	};
})()`;

export function validateFocusEvidenceOptions({ enabled, nativeZoom, captureScreenshots, loginIdentity, loginPassword, routes }) {
	if (!enabled) return;
	const publicPaths = ['', 'kursus', 'masuk', 'daftar'];
	if (!nativeZoom || !captureScreenshots || loginIdentity || loginPassword || routes.some(route => !publicPaths.includes(route.path))) {
		throw new Error('--capture-focus-evidence requires native zoom, --capture-screenshots, no login credentials and only public home/catalog/sign-in/sign-up routes without query.');
	}
}

// Capture the current viewport, not a full-page capture that changes the view.
// An image failure is retained in JSON, never converted into a passing check.
export async function writeFocusEvidence({ directory, stem, report, captureScreenshot, write = writeFile }) {
	if (!/^[a-z0-9_-]{1,100}$/i.test(stem)) throw new Error('Invalid focus evidence filename.');
	const output = { ...report, screenshot: { status: 'not-requested' } };
	if (captureScreenshot) {
		try {
			const data = await captureScreenshot();
			const filename = `${stem}.png`;
			await write(path.join(directory, filename), Buffer.from(data, 'base64'), { flag: 'wx' });
			output.screenshot = { status: 'saved', filename };
		} catch {
			// Do not persist arbitrary CDP error descriptions or page content.
			output.screenshot = { status: 'failed' };
		}
	}
	const filename = `${stem}.json`;
	await write(path.join(directory, filename), JSON.stringify(output, null, 2) + '\n', { flag: 'wx' });
	return { filename, screenshot: output.screenshot };
}
