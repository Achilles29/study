import { writeFile } from 'node:fs/promises';
import path from 'node:path';
import { parseCssColor, contrastRatio, compositeColor } from './button-theme-contract.mjs';

const themes = ['theme-public', 'theme-adult', 'theme-parent', 'theme-admin', 'theme-child-early', 'theme-child-primary', 'theme-child-secondary'];
const variants = ['primary', 'outline-primary', 'link'];
const approvedPalettes = {
	default: ['#5854d8','#403bb3','#403bb3'],
	'theme-child-early': ['#bd531b','#8d3b12','#8a4b00'],
	'theme-child-primary': ['#247d65','#175b49','#8a4b00'],
	'theme-child-secondary': ['#3f5ab2','#2c428a','#8a4b00']
};
const rgb = value => parseCssColor(value).rgb;
const equalColor = (actual, expected) => {
	const a = parseCssColor(actual), b = parseCssColor(expected);
	return Math.abs(a.alpha - b.alpha) < .001 && (a.alpha === 0 || a.rgb.every((value, index) => Math.abs(value - b.rgb[index]) < .01));
};

// An opt-in, anonymous component fixture in the fresh browser DOM. No server
// route, user/theme setting, form, database, storage or application file is changed.
export async function runButtonThemeChecks({ send, navigateTo, baseUrl, screenshotDir }) {
	const evaluate = async expression => {
		const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
		if (result.exceptionDetails) throw new Error('Button component browser evaluation failed.');
		return result.result.value;
	};
	const assert = (condition, label) => { if (!condition) throw new Error('Button theme contract: ' + label); };
	const key = async (name, code, keyCode) => {
		await send('Input.dispatchKeyEvent', { type: 'keyDown', key: name, code, windowsVirtualKeyCode: keyCode });
		await send('Input.dispatchKeyEvent', { type: 'keyUp', key: name, code, windowsVirtualKeyCode: keyCode });
	};
	const summaries = [], screenshots = [];
	let minTextContrast = Infinity, minBorderContrast = Infinity, minFocusContrast = Infinity;
	await send('DOM.enable');
	await send('CSS.enable');
	await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
	try {
		for (const engine of ['bootstrap', 'bootstrap-coreui']) {
			await navigateTo(baseUrl, 'anonymous button component fixture');
			assert(await evaluate(`(() => {
				const styles = [...document.querySelectorAll('link[rel="stylesheet"]')];
				return styles.some(link => link.href.includes('/assets/vendor/bootstrap/')) && styles.some(link => link.href.includes('/assets/css/app.css')) && !document.querySelector('form[action$="/keluar"]');
			})()`), 'fixture must begin on anonymous local page with actual assets');
			if (engine === 'bootstrap-coreui') {
				assert(await evaluate(`new Promise(resolve => {
					const bootstrap = [...document.querySelectorAll('link[rel="stylesheet"]')].find(link => link.href.includes('/assets/vendor/bootstrap/'));
					const link = document.createElement('link'); link.rel = 'stylesheet';
					link.href = bootstrap.href.replace('/bootstrap/css/bootstrap.min.css', '/coreui/css/coreui.min.css');
					if (new URL(link.href).origin !== location.origin) return resolve(false);
					const timer = setTimeout(() => resolve(false), 5000);
					link.onload = () => { clearTimeout(timer); resolve(true); };
					link.onerror = () => { clearTimeout(timer); resolve(false); };
					bootstrap.after(link);
				})`), 'CoreUI asset must load after Bootstrap and before application CSS');
			}
			for (const theme of themes) {
				for (const width of [360, 768, 1280]) {
					await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
					const context = `${engine}/${theme}/${width}`;
					const tokens = await evaluate(`(() => {
						document.body.className = ${JSON.stringify(theme)};
						const main = document.createElement('main'); main.id = 'button-theme-fixture'; main.className = 'container py-4'; main.tabIndex = -1;
						const title = document.createElement('h1'); title.className = 'h3'; title.textContent = 'Pemeriksaan komponen tombol'; main.append(title);
						const note = document.createElement('p'); note.textContent = ${JSON.stringify(engine + ' · ' + theme + ' · fixture lokal, bukan halaman pengguna')}; main.append(note);
						const grid = document.createElement('div'); grid.className = 'row g-3'; main.append(grid);
						for (const variant of ${JSON.stringify(variants)}) {
							const col = document.createElement('div'); col.className = 'col-md-6 col-xl-4'; grid.append(col);
							const card = document.createElement('section'); card.className = 'feature-card h-100'; card.style.background = 'var(--lavender)'; col.append(card);
							const heading = document.createElement('h2'); heading.className = 'h5 mb-3'; heading.textContent = variant; card.append(heading);
							for (const kind of ['button', 'anchor', 'active', 'disabled', 'disabled-anchor']) {
								const row = document.createElement('p'); row.className = 'mb-3'; card.append(row);
								const el = document.createElement(kind.includes('anchor') ? 'a' : 'button'); el.id = variant + '-' + kind;
								el.className = 'btn btn-' + variant + ' rounded-pill'; el.textContent = {button:'Lanjutkan belajar',anchor:'Jelajahi materi',active:'Pilihan aktif',disabled:'Belum tersedia','disabled-anchor':'Tautan nonaktif'}[kind];
								if (el.tagName === 'BUTTON') el.type = 'button'; else { el.href = '#button-theme-fixture'; el.addEventListener('click', event => event.preventDefault()); }
								if (kind === 'active') el.classList.add('active');
								if (kind === 'disabled') el.disabled = true;
								if (kind === 'disabled-anchor') { el.classList.add('disabled'); el.setAttribute('aria-disabled','true'); el.tabIndex = -1; }
								row.append(el);
							}
							if (variant !== 'link') {
								const input = document.createElement('input'); input.type = 'checkbox'; input.className = 'btn-check'; input.id = variant + '-check'; input.autocomplete = 'off';
								const label = document.createElement('label'); label.id = variant + '-label'; label.htmlFor = input.id; label.className = 'btn btn-' + variant + ' rounded-pill'; label.textContent = 'Pilih latihan'; card.append(input, label);
							}
						}
						document.body.replaceChildren(main); main.focus(); window.scrollTo(0,0);
						const style = getComputedStyle(document.body);
						return Object.fromEntries(['primary','primary-dark','focus-ring','surface','wash','lavender','mint'].map(name => [name,style.getPropertyValue('--'+name).trim()]));
					})()`);
					const palette = approvedPalettes[theme] || approvedPalettes.default;
					assert(['primary','primary-dark','focus-ring'].every((name,index) => equalColor(tokens[name],palette[index])), `${context} approved age palette was lost or changed`);
					const surfaces = ['surface', 'wash', 'lavender', 'mint'].map(name => rgb(tokens[name]));
					const sample = async id => evaluate(`(() => {
						const el = document.getElementById(${JSON.stringify(id)}), s = getComputedStyle(el), r = el.getBoundingClientRect();
						return { color:s.color,bg:s.backgroundColor,border:s.borderTopColor,opacity:Number(s.opacity),outline:s.outlineColor,outlineWidth:parseFloat(s.outlineWidth),outlineStyle:s.outlineStyle,offset:parseFloat(s.outlineOffset),shadow:s.boxShadow,disabled:el.disabled === true,pointer:s.pointerEvents,tabIndex:el.tabIndex,ariaDisabled:el.getAttribute('aria-disabled'),rect:{left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height},focusVisible:el.matches(':focus-visible') };
					})()`);
					const checkStyle = (actual, variant, state) => {
						const filled = variant === 'primary' || (variant === 'outline-primary' && ['hover','pressed','active','focus','checked'].includes(state));
						const darkFill = ['hover','pressed','active','focus','checked'].includes(state);
						const expectedBg = filled ? tokens[darkFill ? 'primary-dark' : 'primary'] : 'transparent';
						const expectedColor = filled ? '#ffffff' : tokens['primary-dark'];
						const expectedBorder = variant === 'link' ? 'transparent' : tokens[filled && darkFill ? 'primary-dark' : 'primary'];
						assert(equalColor(actual.color, expectedColor) && equalColor(actual.bg, expectedBg) && equalColor(actual.border, expectedBorder), `${context}/${variant}/${state} colors do not follow tokens`);
						assert(actual.rect.height >= 44 && actual.rect.left >= -1 && actual.rect.right <= width + 1, `${context}/${variant}/${state} touch target or horizontal bounds`);
						if (state === 'disabled') {
							assert(actual.opacity > 0 && actual.opacity < 1 && actual.pointer === 'none', `${context}/${variant} disabled visual state`);
							return;
						}
						assert(actual.opacity === 1, `${context}/${variant}/${state} enabled opacity must remain opaque`);
						for (const surface of surfaces) {
							const bg = compositeColor(parseCssColor(actual.bg), surface);
							const ratio = contrastRatio(rgb(actual.color), bg); minTextContrast = Math.min(minTextContrast, ratio);
							assert(ratio >= 4.5, `${context}/${variant}/${state} text contrast`);
							if (variant !== 'link') {
								const boundary = contrastRatio(rgb(actual.border), surface); minBorderContrast = Math.min(minBorderContrast, boundary);
								assert(boundary >= 3, `${context}/${variant}/${state} boundary contrast`);
							}
						}
					};
					const focusOrder = variants.flatMap(variant => [variant+'-button',variant+'-anchor',variant+'-active',...(variant === 'link' ? [] : [variant+'-check'])]);
					for (const id of focusOrder) {
						await key('Tab', 'Tab', 9);
						assert(await evaluate(`document.activeElement.id === ${JSON.stringify(id)} && document.activeElement.matches(':focus-visible')`), `${context} native Tab order ${id}`);
						const actual = await sample(id.endsWith('-check') ? id.replace('-check','-label') : id);
						assert(actual.outlineStyle !== 'none' && actual.outlineWidth >= 3 && actual.offset >= 3 && equalColor(actual.outline,tokens['focus-ring']) && actual.shadow === 'none', `${context}/${id} themed visible focus without vendor halo`);
						assert(actual.rect.top >= -1 && actual.rect.bottom <= 901, `${context}/${id} native keyboard focus must stay in the viewport`);
						for (const surface of surfaces) {
							const ratio = contrastRatio(rgb(actual.outline), surface); minFocusContrast = Math.min(minFocusContrast, ratio);
							assert(ratio >= 3, `${context}/${id} focus contrast`);
						}
						if (!id.endsWith('-check') && !id.endsWith('-active')) checkStyle(actual, variants.find(variant => id.startsWith(variant+'-')), 'focus');
					}
					await evaluate('document.activeElement.blur(); true');
					const doc = await send('DOM.getDocument');
					for (const variant of variants) {
						for (const kind of ['button','anchor','active','disabled','disabled-anchor']) {
							const actual = await sample(variant+'-'+kind);
							checkStyle(actual, variant, kind.includes('disabled') ? 'disabled' : kind === 'active' ? 'active' : 'normal');
							if (kind === 'disabled') assert(actual.disabled, `${context} native disabled required`);
							if (kind === 'disabled-anchor') assert(actual.ariaDisabled === 'true' && actual.tabIndex === -1, `${context} disabled anchor semantics`);
						}
						for (const kind of ['button','anchor']) {
							const id = variant+'-'+kind;
							const { nodeId } = await send('DOM.querySelector', { nodeId: doc.root.nodeId, selector: '#'+id });
							for (const [state,pseudos] of [['hover',['hover']],['pressed',['active']],['focus',['focus','focus-visible']],['focus',['focus','focus-visible','hover']]]) {
								await send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: pseudos });
								await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
								checkStyle(await sample(id), variant, state);
							}
							await send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] });
						}
						if (variant !== 'link') {
							const { nodeId } = await send('DOM.querySelector', { nodeId: doc.root.nodeId, selector: '#'+variant+'-label' });
							await send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: ['hover'] });
							checkStyle(await sample(variant+'-label'), variant, 'normal');
							await send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] });
							await evaluate(`document.getElementById(${JSON.stringify(variant+'-check')}).focus(); true`);
							await key(' ', 'Space', 32);
							assert(await evaluate(`document.getElementById(${JSON.stringify(variant+'-check')}).checked`), `${context} checkbox Space toggles`);
							checkStyle(await sample(variant+'-label'), variant, 'checked');
							await evaluate(`(() => { const el=document.getElementById(${JSON.stringify(variant+'-check')}); el.blur(); el.disabled=true; el.click(); return true; })()`);
							assert(await evaluate(`document.getElementById(${JSON.stringify(variant+'-check')}).checked`), `${context} disabled checkbox cannot toggle`);
							const disabledChecked = await sample(variant+'-label');
							assert(disabledChecked.opacity > 0 && disabledChecked.opacity < 1 && disabledChecked.pointer === 'none', `${context} checked disabled label`);
							assert(equalColor(disabledChecked.color,'#ffffff') && equalColor(disabledChecked.bg,tokens['primary-dark']) && equalColor(disabledChecked.border,tokens['primary-dark']), `${context} checked disabled palette must retain active colors before opacity`);
							await evaluate(`(() => { const el=document.getElementById(${JSON.stringify(variant+'-check')}); el.disabled=false; el.checked=false; return true; })()`);
						}
					}
					const geometry = await evaluate('({viewport:innerWidth, document:document.documentElement.scrollWidth, body:document.body.scrollWidth})');
					assert(geometry.document <= geometry.viewport + 1 && geometry.body <= geometry.viewport + 1, `${context} component overflow`);
					if (screenshotDir && [360,1280].includes(width) && ((engine === 'bootstrap' && ['theme-public','theme-child-early','theme-child-primary','theme-child-secondary'].includes(theme)) || (engine === 'bootstrap-coreui' && theme === 'theme-admin'))) {
						await evaluate('window.scrollTo(0,0); document.fonts.ready');
						const metrics = await send('Page.getLayoutMetrics');
						const capture = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x:0,y:0,width,height:Math.min(8000,Math.ceil(metrics.cssContentSize.height)),scale:1 } });
						const target = path.join(screenshotDir, `buttons-${engine}-${theme}-${width}.png`);
						await writeFile(target, Buffer.from(capture.data,'base64'), { flag:'wx' }); screenshots.push(target);
					}
					summaries.push({ engine,theme,width,nativeTabStops:focusOrder.length,states:true,contrast:true,overflow:false });
				}
			}
		}
	} finally { await send('Emulation.setEmulatedMedia', { features: [] }); }
	return { summaries, screenshots, minTextContrast, minBorderContrast, minFocusContrast, limitation:'Synthetic DOM component matrix, reduced motion; not a page/theme-assignment, full keyboard, forced-colors, zoom or WCAG audit.' };
}
