import assert from 'node:assert/strict';
import { parseCssColor, compositeColor, relativeLuminance, contrastRatio } from './button-theme-contract.mjs';

let passed = 0;
function test(name, run) {
	run();
	passed++;
	console.log(`PASS ${name}`);
}
function near(actual, expected, tolerance = 1e-12) {
	assert.ok(Math.abs(actual - expected) <= tolerance, `${actual} differs from ${expected}`);
}
const black = Object.freeze([0, 0, 0]);
const white = Object.freeze([255, 255, 255]);

test('hex RGB and RGBA formats preserve exact channels and alpha', () => {
	assert.deepEqual(parseCssColor('#369'), { rgb: [51, 102, 153], alpha: 1 });
	assert.deepEqual(parseCssColor('#336699'), { rgb: [51, 102, 153], alpha: 1 });
	assert.deepEqual(parseCssColor(' #aBcD '), { rgb: [170, 187, 204], alpha: 221 / 255 });
	assert.deepEqual(parseCssColor('#AABBCC80'), { rgb: [170, 187, 204], alpha: 128 / 255 });
	assert.deepEqual(parseCssColor('#0000'), { rgb: [0, 0, 0], alpha: 0 });
	assert.deepEqual(parseCssColor('#ffffffff'), { rgb: [255, 255, 255], alpha: 1 });
});

test('computed rgb and rgba comma formats accept explicit fractional channels', () => {
	assert.deepEqual(parseCssColor('rgb(10, 20, 30)'), { rgb: [10, 20, 30], alpha: 1 });
	assert.deepEqual(parseCssColor('RGBA(10.5, 20.25, 30.75, .5)'), { rgb: [10.5, 20.25, 30.75], alpha: 0.5 });
	assert.deepEqual(parseCssColor('rgb(0,0,0,1)'), { rgb: [0, 0, 0], alpha: 1 });
	assert.deepEqual(parseCssColor('rgba(255,255,255)'), { rgb: [255, 255, 255], alpha: 1 });
});

test('percentages, modern slash alpha and scientific notation remain unrounded', () => {
	assert.deepEqual(parseCssColor('rgb(100%, 50%, 0%)'), { rgb: [255, 127.5, 0], alpha: 1 });
	assert.deepEqual(parseCssColor('rgba(100%, 50%, 0%, 25%)'), { rgb: [255, 127.5, 0], alpha: 0.25 });
	assert.deepEqual(parseCssColor('rgb(255 50% 0 / 50%)'), { rgb: [255, 127.5, 0], alpha: 0.5 });
	assert.deepEqual(parseCssColor('rgba(1e2\t+2e1\n.5 / 5e-1)'), { rgb: [100, 20, 0.5], alpha: 0.5 });
});

test('transparent is an explicit zero-alpha color, not an inferred background', () => {
	assert.deepEqual(parseCssColor(' TRANSPARENT '), { rgb: [0, 0, 0], alpha: 0 });
	assert.deepEqual(compositeColor(parseCssColor('transparent'), [12, 34, 56]), [12, 34, 56]);
});

test('unsupported or malformed CSS formats fail closed', () => {
	for (const value of ['', ' ', '#12', '#12345', '#1234567', '#123456789', '#ggg', 'fff', 'white', 'currentColor', 'inherit', 'var(--brand)', 'hsl(0 0% 0%)', 'color(display-p3 1 0 0)', 'rgb(from white r g b)', 'rgb(calc(1 + 2) 0 0)', 'rgb(1 2)', 'rgb(1 2 3 0.5)', 'rgb(1,2)', 'rgb(1,,3)', 'rgb(1,2,3,)', 'rgb(1,2,3,1,0)', 'rgb(1,2,3 / .5)', 'rgb(1 2 3 /)', 'rgb(/ .5)', 'rgb(1 2 3 / .5 / .5)', 'rgb(1%,2,3)', 'rgb(1px 2 3)', 'rgb(none 0 0)', 'rgb(NaN 0 0)', 'rgb(Infinity 0 0)', 'rgb(0x10 0 0)', 'rgb(1_0 0 0)', 'rgb(1. 0 0)', 'rgb(1 2 3) trailing', 'rgb(1 2 3); color:red', 'x'.repeat(257)]) {
		assert.throws(() => parseCssColor(value), TypeError);
	}
	for (const value of [null, undefined, 0, [], {}, new String('#fff')]) assert.throws(() => parseCssColor(value), TypeError);
	for (const value of ['rgb(1\u00a02\u00a03)', '\ufeff#fff', 'rgb(1\v2\v3)', '#fff\0']) assert.throws(() => parseCssColor(value), TypeError);
});

test('out-of-range CSS values are rejected instead of silently clamped', () => {
	for (const value of ['rgb(-1 0 0)', 'rgb(256 0 0)', 'rgb(101% 0% 0%)', 'rgb(0 0 0 / -0.01)', 'rgba(0,0,0,1.01)', 'rgb(0 0 0 / 101%)', 'rgb(1e309 0 0)']) assert.throws(() => parseCssColor(value), RangeError);
});

test('opaque and transparent composition return fresh arrays without mutation', () => {
	const rgb = Object.freeze([11, 33, 55]);
	const opaque = Object.freeze({ rgb, alpha: 1 });
	const clear = Object.freeze({ rgb, alpha: 0 });
	assert.deepEqual(compositeColor(opaque, white), rgb);
	assert.notEqual(compositeColor(opaque, white), rgb);
	assert.deepEqual(compositeColor(clear, white), white);
	assert.notEqual(compositeColor(clear, white), white);
});

test('alpha compositing preserves fractions and layer order', () => {
	assert.deepEqual(compositeColor({ rgb: black, alpha: 0.5 }, white), [127.5, 127.5, 127.5]);
	assert.deepEqual(compositeColor({ rgb: [255, 0, 0], alpha: 0.25 }, [0, 0, 255]), [63.75, 0, 191.25]);
	const first = compositeColor({ rgb: [255, 0, 0], alpha: 0.5 }, white);
	assert.deepEqual(compositeColor({ rgb: [0, 0, 255], alpha: 0.5 }, first), [127.5, 63.75, 191.25]);
});

test('invalid RGB triples are rejected by every numeric helper', () => {
	for (const rgb of [null, undefined, '#fff', {}, [], [0, 0], [0, 0, 0, 1], Array(3), [0, 0, '1'], [0, 0, NaN], [0, 0, Infinity], [0, 0, null], new Uint8Array([0, 0, 0])]) {
		assert.throws(() => relativeLuminance(rgb), TypeError);
		assert.throws(() => contrastRatio(rgb, white), TypeError);
		assert.throws(() => contrastRatio(black, rgb), TypeError);
		assert.throws(() => compositeColor({ rgb, alpha: 1 }, white), TypeError);
		assert.throws(() => compositeColor({ rgb: black, alpha: 1 }, rgb), TypeError);
	}
	for (const rgb of [[-0.01, 0, 0], [0, 255.01, 0]]) {
		assert.throws(() => relativeLuminance(rgb), RangeError);
		assert.throws(() => contrastRatio(rgb, white), RangeError);
		assert.throws(() => compositeColor({ rgb, alpha: 1 }, white), RangeError);
	}
});

test('invalid alpha and foreground objects cannot be mistaken for opaque', () => {
	for (const foreground of [null, undefined, [], '#fff', {}, { rgb: black }, { rgb: black, alpha: '1' }, { rgb: black, alpha: NaN }, { rgb: black, alpha: Infinity }]) assert.throws(() => compositeColor(foreground, white), TypeError);
	for (const alpha of [-0.01, 1.01]) assert.throws(() => compositeColor({ rgb: black, alpha }, white), RangeError);
});

test('WCAG reference luminances and black/white contrast are exact', () => {
	assert.equal(relativeLuminance(black), 0);
	assert.equal(relativeLuminance(white), 1);
	assert.equal(relativeLuminance([255, 0, 0]), 0.2126);
	assert.equal(relativeLuminance([0, 255, 0]), 0.7152);
	assert.equal(relativeLuminance([0, 0, 255]), 0.0722);
	assert.equal(contrastRatio(black, white), 21);
	assert.equal(contrastRatio(white, black), 21);
	assert.equal(contrastRatio(white, white), 1);
	assert.equal(contrastRatio(black, black), 1);
});

test('the WCAG sRGB transfer threshold uses 0.04045, including fractional channels', () => {
	const atThreshold = Array(3).fill(0.04045 * 255);
	const aboveThreshold = Array(3).fill(0.04046 * 255);
	near(relativeLuminance(atThreshold), 0.04045 / 12.92, 1e-15);
	near(relativeLuminance(aboveThreshold), ((0.04046 + 0.055) / 1.055) ** 2.4, 1e-15);
});

test('known gray contrast is not rounded into an incorrect threshold pass', () => {
	const ratio = contrastRatio(parseCssColor('#777').rgb, white);
	near(ratio, 4.478089453577214);
	assert.ok(ratio < 4.5);
	assert.equal(contrastRatio([117, 117, 117], white) > 4.5, true);
});

test('values around 3 and 4.5 thresholds retain their actual pass/fail side', () => {
	const grayForContrastWithBlack = ratio => {
		const luminance = ratio * 0.05 - 0.05;
		return Array(3).fill((1.055 * luminance ** (1 / 2.4) - 0.055) * 255);
	};
	for (const threshold of [3, 4.5]) {
		near(contrastRatio(grayForContrastWithBlack(threshold), black), threshold);
		const below = contrastRatio(grayForContrastWithBlack(threshold - 0.0001), black);
		const above = contrastRatio(grayForContrastWithBlack(threshold + 0.0001), black);
		assert.ok(below < threshold);
		assert.ok(above > threshold);
		assert.equal(below.toFixed(2), threshold.toFixed(2));
	}
});

test('contrast is symmetric, bounded and unchanged by color parsing notation', () => {
	const colors = [black, white, [5, 30, 200], [10.5, 45.25, 200.75], [255, 128, 0]];
	for (const foreground of colors) {
		for (const background of colors) {
			const ratio = contrastRatio(foreground, background);
			assert.ok(ratio >= 1 && ratio <= 21);
			assert.equal(ratio, contrastRatio(background, foreground));
		}
	}
	assert.equal(contrastRatio(parseCssColor('#336699').rgb, white), contrastRatio(parseCssColor('rgb(51, 102, 153)').rgb, white));
});

console.log(`PASS ${passed} pure button color/contrast tests; no filesystem, browser or database access.`);
