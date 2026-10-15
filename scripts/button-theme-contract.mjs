// Pure helpers for resolved sRGB button colors; no DOM, browser or I/O access.
// This deliberately strict subset rejects unresolved colors (var/currentColor,
// named colors except transparent, hsl/lab/color(), calc() and out-of-range
// values). Callers must obtain computed colors and resolve the actual backdrop.

function requireRgb(rgb) {
	if (!Array.isArray(rgb) || rgb.length !== 3) throw new TypeError('Expected exactly three sRGB channels.');
	for (const channel of rgb) {
		if (typeof channel !== 'number' || !Number.isFinite(channel)) throw new TypeError('sRGB channels must be finite numbers.');
		if (channel < 0 || channel > 255) throw new RangeError('sRGB channels must be between 0 and 255.');
	}
	return rgb;
}

function parseComponent(token, maximum) {
	const match = /^([+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:e[+-]?\d+)?)(%)?$/i.exec(token);
	if (!match) throw new TypeError('Unsupported CSS color component.');
	const value = Number(match[1]);
	const limit = match[2] ? 100 : maximum;
	if (!Number.isFinite(value) || value < 0 || value > limit) throw new RangeError('CSS color component is outside its allowed range.');
	return match[2] ? value * maximum / 100 : value;
}

/**
 * Parse #RGB/#RGBA/#RRGGBB/#RRGGBBAA, transparent, or rgb()/rgba() using
 * comma syntax or space-separated channels with an optional / alpha.
 * Returns { rgb: [r, g, b], alpha }; channels stay unrounded in 0..255.
 * Percent channels/alpha are supported; legacy comma RGB units cannot mix.
 */
export function parseCssColor(value) {
	if (typeof value !== 'string' || value.length > 256) throw new TypeError('Expected a short resolved CSS color string.');
	if (/[^\t\n\f\r\x20-\x7e]/.test(value)) throw new TypeError('Unsupported character in resolved CSS color.');
	const color = value.trim();
	if (/^transparent$/i.test(color)) return { rgb: [0, 0, 0], alpha: 0 };
	if (/^#(?:[\da-f]{3}|[\da-f]{4}|[\da-f]{6}|[\da-f]{8})$/i.test(color)) {
		const hex = color.slice(1);
		const expanded = hex.length <= 4 ? [...hex].map(digit => digit + digit).join('') : hex;
		const rgb = [0, 2, 4].map(index => parseInt(expanded.slice(index, index + 2), 16));
		return { rgb, alpha: expanded.length === 8 ? parseInt(expanded.slice(6, 8), 16) / 255 : 1 };
	}
	const match = /^rgba?\(([^()]*)\)$/i.exec(color);
	if (!match) throw new TypeError('Unsupported resolved CSS color format.');
	const body = match[1].trim();
	let channels;
	let alpha = '1';
	if (body.includes(',')) {
		const components = body.split(',').map(component => component.trim());
		if (body.includes('/') || ![3, 4].includes(components.length) || components.some(component => component === '')) throw new TypeError('Invalid comma-separated CSS color.');
		channels = components.slice(0, 3);
		if (new Set(channels.map(channel => channel.endsWith('%'))).size !== 1) throw new TypeError('Legacy CSS color channel units cannot mix.');
		if (components.length === 4) alpha = components[3];
	} else {
		const components = body.split('/').map(component => component.trim());
		if (components.length > 2 || components.some(component => component === '')) throw new TypeError('Invalid space-separated CSS color.');
		channels = components[0].split(/\s+/);
		if (channels.length !== 3) throw new TypeError('CSS color requires three channels.');
		if (components.length === 2) alpha = components[1];
	}
	return { rgb: channels.map(channel => parseComponent(channel, 255)), alpha: parseComponent(alpha, 1) };
}

/** Source-over compositing in encoded sRGB onto an explicitly opaque backdrop. */
export function compositeColor(foreground, backgroundRgb) {
	if (!foreground || typeof foreground !== 'object' || Array.isArray(foreground)) throw new TypeError('Expected an RGB/alpha foreground object.');
	const rgb = requireRgb(foreground.rgb);
	const background = requireRgb(backgroundRgb);
	const alpha = foreground.alpha;
	if (typeof alpha !== 'number' || !Number.isFinite(alpha)) throw new TypeError('Alpha must be a finite number.');
	if (alpha < 0 || alpha > 1) throw new RangeError('Alpha must be between 0 and 1.');
	return rgb.map((channel, index) => channel * alpha + background[index] * (1 - alpha));
}

/** WCAG 2.2 sRGB luminance: https://www.w3.org/TR/WCAG22/#dfn-relative-luminance */
export function relativeLuminance(rgb) {
	const linear = requireRgb(rgb).map(channel => {
		const value = channel / 255;
		return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
	});
	return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}

/**
 * Contrast of two opaque sRGB triples, unrounded for threshold comparisons.
 * Composite any transparency first. This does not infer a backdrop, opacity,
 * gradient, font size, button state or WCAG conformance for the whole control.
 * Formula: https://www.w3.org/TR/WCAG22/#dfn-contrast-ratio
 */
export function contrastRatio(foregroundRgb, backgroundRgb) {
	const foreground = relativeLuminance(foregroundRgb);
	const background = relativeLuminance(backgroundRgb);
	return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
}
