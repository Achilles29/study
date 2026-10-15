import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { realpath, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const states = ['unavailable', 'uncertain'];

// Preflight before Chrome starts. Execute only the isolated fixed renderer, not CI.
export function loadPracticeRecoveryFixtures({ phpPath, baseUrl, kind = 'practice' }) {
	assert(['practice', 'matching', 'reading', 'flashcard'].includes(kind), 'Unknown recovery fixture kind.');
	const parsed = new URL(baseUrl);
	assert(parsed.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(parsed.hostname) &&
		!parsed.username && !parsed.password && !parsed.search && !parsed.hash && parsed.pathname.endsWith('/') && !/[\u0000-\u0020\u007f<>"'`\\]/.test(baseUrl), 'Recovery renderer requires an HTTP loopback base URL.');
	assert(path.isAbsolute(phpPath) && /^php(?:\.exe)?$/i.test(path.basename(phpPath)), 'Recovery renderer requires an absolute PHP CLI executable path.');
	let rendered;
	try {
		rendered = JSON.parse(execFileSync(phpPath, [path.join(scriptDirectory, 'render-practice-recovery.php'), baseUrl, kind], {
			encoding: 'utf8', shell: false, windowsHide: true, timeout: 10000, maxBuffer: 131072, stdio: ['ignore', 'pipe', 'pipe']
		}));
	} catch {
		throw new Error('Isolated production recovery-view rendering failed before the browser started.');
	}
	assert(rendered && Object.keys(rendered).sort().join(',') === [...states].sort().join(',') &&
		states.every(state => typeof rendered[state] === 'string' && rendered[state].length > 0 && rendered[state].length < 20000), 'Unexpected recovery renderer output.');
	return rendered;
}

async function checkedScreenshotDirectory(directory) {
	if (!directory) return null;
	const resolved = path.resolve(directory), canonical = await realpath(resolved);
	assert(resolved.toLowerCase() === canonical.toLowerCase(), 'Recovery capture directory must not be redirected.');
	const baseline = await realpath(path.join(scriptDirectory, '..', 'docs', 'visual-baseline'));
	const relative = path.relative(baseline, canonical);
	const withinBaseline = relative && !relative.startsWith('..') && !path.isAbsolute(relative);
	const temporaryRoot = await realpath(os.tmpdir());
	const temporaryChild = path.dirname(canonical).toLowerCase() === temporaryRoot.toLowerCase() && /^study-visual-review-[A-Za-z0-9]+$/.test(path.basename(canonical));
	assert(withinBaseline || temporaryChild, 'Recovery captures require the supplied baseline or test-owned temporary directory.');
	return canonical;
}

// Component evidence only: use a fresh anonymous DOM and actual production CSS.
// Never click the synthetic attempt link, submit a form, or inject an HTTP error.
export async function runPracticeRecoveryChecks({ send, navigateTo, baseUrl, screenshotDir, fixtures, kind = 'practice' }) {
	assert(['practice', 'matching', 'reading', 'flashcard'].includes(kind), 'Unknown recovery browser kind.');
	const matching = kind === 'matching';
	const heading = kind === 'flashcard' ? 'Periksa kembali kartu belajar' : kind === 'reading' ? 'Periksa kembali progres baca' : matching ? 'Periksa kembali permainan' : 'Periksa kembali latihan';
	const historyLabel = ['reading', 'flashcard'].includes(kind) ? 'Beranda belajar saya' : matching ? 'Riwayat permainan saya' : 'Riwayat latihan saya';
	const captureDirectory = await checkedScreenshotDirectory(screenshotDir);
	const evaluate = async expression => {
		const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
		assert(!result.exceptionDetails, 'Recovery component browser evaluation failed.');
		return result.result.value;
	};
	const expectedLinks = (kind === 'flashcard' ? ['materi/uji#kartu-belajar', 'beranda'] : kind === 'reading' ? ['materi/uji', 'beranda'] : matching ? ['permainan/tersimpan/1', 'permainan/riwayat'] : ['latihan/percobaan/1', 'latihan']).map(uri => new URL(uri, baseUrl).href);
	const summaries = [], screenshots = [];
	await navigateTo(baseUrl, 'anonymous recovery component fixture');
	assert(await evaluate(`(() => {
		const sheets = [...document.querySelectorAll('link[rel="stylesheet"]')];
		return sheets.some(link => link.href.includes('/assets/vendor/bootstrap/')) && sheets.some(link => link.href.includes('/assets/css/app.css')) && !document.querySelector('form[action$="/keluar"]');
	})()`), 'Recovery fixture must use actual Bootstrap/application assets on an anonymous local page.');
	await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
	try {
		for (const state of states) for (const theme of ['theme-adult', 'theme-child-primary']) for (const width of [360, 768, 1280]) {
			const context = `${kind}/${state}/${theme}/${width}`;
			await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
			await evaluate(`(() => {
				document.body.className = ${JSON.stringify(theme)};
				const main = document.createElement('main'); main.id = 'practice-recovery-fixture'; main.tabIndex = -1;
				const note = document.createElement('p'); note.className = 'container pt-3 text-secondary'; note.textContent = 'Fixture uji; bukan respons HTTP 503'; main.append(note);
				const fragment = document.createElement('template'); fragment.innerHTML = ${JSON.stringify(fixtures[state])}; main.append(fragment.content);
				document.body.replaceChildren(main); main.focus(); window.scrollTo(0, 0); return true;
			})()`);
			await evaluate('document.fonts.ready.then(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve(true)))))');
			const shape = await evaluate(`(() => {
				const card = document.querySelector(${JSON.stringify(`[data-${kind}-recovery]`)});
				const links = card ? [...card.querySelectorAll('a[href]')] : [];
				return { state:card?.getAttribute(${JSON.stringify(`data-${kind}-recovery`)}), title:card?.querySelector('h1')?.textContent, status:card?.querySelector('[role="status"]')?.textContent,
					readOnly:!document.querySelector('form,button,input,select,textarea,script,iframe,object'),
					links:links.map(link => ({href:link.href,text:link.textContent.trim(),native:!link.hasAttribute('onclick') && !link.hasAttribute('role') && !link.hasAttribute('download') && !link.hasAttribute('target'),height:link.getBoundingClientRect().height,width:link.getBoundingClientRect().width})),
					viewport:innerWidth,document:document.documentElement.scrollWidth,body:document.body.scrollWidth };
			})()`);
			assert.equal(shape.state, state, `${context} actual recovery state`);
			assert.equal(shape.title, heading, `${context} heading`);
			const expectedStatus = state === 'uncertain' ? 'Hasil penyimpanan belum dapat dipastikan.' : 'Permintaan belum berhasil disimpan.';
			const excludedStatus = state === 'uncertain' ? 'Permintaan belum berhasil disimpan.' : 'Hasil penyimpanan belum dapat dipastikan.';
			assert(shape.status.includes(expectedStatus) && !shape.status.includes(excludedStatus), `${context} distinct certainty message`);
			assert(shape.readOnly && shape.links.length === 2, `${context} read-only recovery must have exactly two native links and no inputs/scripts`);
			assert.deepEqual(shape.links.map(link => link.href), expectedLinks, `${context} recovery destinations`);
			assert.deepEqual(shape.links.map(link => link.text), ['Periksa keadaan terbaru', historyLabel], `${context} meaningful link names`);
			assert(shape.links.every(link => link.native && link.height >= 44 && link.width >= 44), `${context} native targets at least 44 by 44 CSS pixels`);
			assert(shape.viewport === width && shape.document <= width + 1 && shape.body <= width + 1, `${context} horizontal overflow`);
			const clipped = await evaluate(`(() => {
				const root = document.getElementById('practice-recovery-fixture'), walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT), issues = [];
				while (walker.nextNode()) {
					const node = walker.currentNode; if (!node.textContent.trim()) continue;
					const range = document.createRange(); range.selectNodeContents(node);
					for (const rect of range.getClientRects()) {
						if (!rect.width || !rect.height) continue;
						if (rect.left < -1 || rect.right > innerWidth + 1) issues.push('text outside viewport');
						for (let el = node.parentElement; el && el !== document.body; el = el.parentElement) {
							const style = getComputedStyle(el), bounds = el.getBoundingClientRect();
							if (['hidden','clip','auto','scroll'].includes(style.overflowX) && (rect.left < bounds.left - 1 || rect.right > bounds.right + 1)) issues.push('horizontal text clipping');
							if (['hidden','clip','auto','scroll'].includes(style.overflowY) && (rect.top < bounds.top - 1 || rect.bottom > bounds.bottom + 1)) issues.push('vertical text clipping');
						}
					}
				}
				return issues;
			})()`);
			assert.deepEqual(clipped, [], `${context} rendered text must not be clipped`);
			for (const destination of expectedLinks) {
				await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
				await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
				const focus = await evaluate(`(() => { const el = document.activeElement, style = getComputedStyle(el), r = el.getBoundingClientRect(); return {href:el.href,visible:el.matches(':focus-visible'),outline:style.outlineStyle,width:parseFloat(style.outlineWidth),offset:parseFloat(style.outlineOffset),color:style.outlineColor,left:r.left,right:r.right,top:r.top,bottom:r.bottom}; })()`);
				assert(focus.href === destination && focus.visible && focus.outline !== 'none' && focus.width >= 3 && focus.offset >= 3 && !['transparent','rgba(0, 0, 0, 0)'].includes(focus.color), `${context} native Tab order and visible focus`);
				assert(focus.left >= -1 && focus.right <= width + 1 && focus.top >= -1 && focus.bottom <= 901, `${context} focused link remains visible`);
			}
			const capture = (state === 'uncertain' && ((theme === 'theme-adult' && width === 1280) || (theme === 'theme-child-primary' && width === 360))) || (state === 'unavailable' && theme === 'theme-adult' && width === 360);
			if (captureDirectory && capture) {
				await evaluate('document.activeElement.blur(); window.scrollTo(0,0); true');
				const metrics = await send('Page.getLayoutMetrics');
				const png = await send('Page.captureScreenshot', { format:'png', captureBeyondViewport:true, clip:{x:0,y:0,width,height:Math.min(8000,Math.ceil(metrics.cssContentSize.height)),scale:1} });
				const target = path.join(captureDirectory, `${kind}-recovery-${state}-${theme}-${width}.png`);
				await writeFile(target, Buffer.from(png.data, 'base64'), { flag:'wx' }); screenshots.push(target);
			}
			summaries.push({ kind,state,theme,width,nativeLinks:2,nativeTabStops:2,readOnly:true,textClipping:false,horizontalOverflow:false });
		}
	} finally { await send('Emulation.setEmulatedMedia', { features: [] }); }
	return { summaries,screenshots,limitation:'Actual production-view PHP rendering in an anonymous synthetic DOM with actual CSS; not a real HTTP 503, real DB failure, link navigation, native zoom, full assistive-technology or WCAG audit.' };
}
