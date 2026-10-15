import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../assets/js/navigation.js', import.meta.url), 'utf8');
function fixture(bootstrap, missing = '') {
	const classes = new Set(['navbar-collapse']);
	let mutations = 0;
	const menu = { dataset: {}, classList: { add(value) { mutations++; classes.add(value); } } };
	const toggle = { hidden: true };
	const context = vm.createContext({ window: { bootstrap }, document: {
		getElementById(id) { assert.equal(id, 'navigasi-utama'); return missing === 'menu' ? null : menu; },
		querySelector(selector) { assert.equal(selector, '.site-header .navbar-toggler[aria-controls="navigasi-utama"]'); return missing === 'toggle' ? null : toggle; }
	} });
	return { menu, toggle, classes, run() { vm.runInContext(source, context); }, mutations() { return mutations; } };
}
for (const dependency of [undefined, null, {}, { Collapse: true }]) {
	const test = fixture(dependency); test.run();
	assert.equal(test.toggle.hidden, true);
	assert.equal(test.classes.has('collapse'), false);
	assert.equal(test.mutations(), 0);
}
for (const missing of ['menu', 'toggle']) {
	const test = fixture({ Collapse() {} }, missing); test.run();
	assert.equal(test.mutations(), 0);
	assert.equal(test.toggle.hidden, true);
}
const enhanced = fixture({ Collapse() {} }); enhanced.run(); enhanced.run();
assert.equal(enhanced.toggle.hidden, false);
assert.equal(enhanced.classes.has('collapse'), true);
assert.equal(enhanced.menu.dataset.navigationEnhanced, 'true');
assert.equal(enhanced.mutations(), 1);
console.log('PASS navigation enhancement: absent/invalid Bootstrap and missing elements preserve visible fallback; ready dependency enables collapse exactly once.');
