import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

function configuredRoutes(source) {
	const reserved = new Set(['404_override', 'translate_uri_dashes']);
	const routes = new Set();
	const declarations = [...source.matchAll(/^\s*\$route\[/gm)];
	const matches = [...source.matchAll(/^\s*\$route\[(["'])([^"']+)\1\](?:\[(["'])[^"']+\3\])?\s*=/gm)];
	assert.equal(matches.length, declarations.length, 'Unsupported route declaration; update the coverage parser rather than silently omitting it');
	for (const match of matches) {
		if (!reserved.has(match[2])) routes.add(match[2] === 'default_controller' ? '/' : match[2]);
	}
	assert.ok(routes.has('/'), 'Default controller route was not parsed');
	return routes;
}

function documentedRoutes(markdown) {
	const section = markdown.split('## Kontrak halaman dan aksi aktif\n')[1]?.split('\n### Keadaan lintas halaman')[0];
	assert.ok(section, 'Active page-contract section is missing');
	const routes = new Set();
	let rows = 0;
	for (const line of section.split('\n')) {
		if (!/^\|\s*`/.test(line)) continue;
		const cells = line.split('|').slice(1, -1).map(cell => cell.trim());
		assert.equal(cells.length, 5, 'Each contract needs route, purpose, data, permissions/actions, and states');
		assert.ok(cells.every(Boolean), 'A contract field is empty');
		const keys = [...cells[0].matchAll(/`([^`]+)`/g)].map(match => match[1]);
		assert.ok(keys.length, 'Contract row has no route');
		for (const key of keys) {
			assert.ok(!routes.has(key), `Duplicate page contract: ${key}`);
			routes.add(key);
		}
		rows++;
	}
	assert.ok(rows, 'No complete contract rows found');
	return { routes, rows };
}

function checkContracts(source, markdown) {
	const expected = configuredRoutes(source);
	const documented = documentedRoutes(markdown.replaceAll('\r\n', '\n'));
	const missing = [...expected].filter(route => !documented.routes.has(route));
	const obsolete = [...documented.routes].filter(route => !expected.has(route));
	assert.deepEqual(missing, [], 'Configured routes missing a page contract');
	assert.deepEqual(obsolete, [], 'Page contracts refer to removed/unconfigured routes');
	return { routes: expected.size, rows: documented.rows };
}

// Coverage checks deliberately do not pretend to prove prose or access control.
const fixtureSource = "$route['default_controller'] = 'home';\n$route['masuk'] = 'auth/sign_in';\n$route['404_override'] = '';\n$route['translate_uri_dashes'] = FALSE;";
const fixtureRow = '| `/`, `masuk` | Tujuan | Data | Izin/aksi | Keadaan |';
const fixtureDoc = `## Kontrak halaman dan aksi aktif\n${fixtureRow}\n### Keadaan lintas halaman\n`;
assert.deepEqual(checkContracts(fixtureSource, fixtureDoc), { routes: 2, rows: 1 });
assert.deepEqual(checkContracts(fixtureSource.replace("$route['masuk']", '$route["masuk"]["get"]') + '\n$route["masuk"]["post"] = "auth/sign_in";', fixtureDoc), { routes: 2, rows: 1 });
assert.throws(() => checkContracts(fixtureSource + '\n$route[$dynamic] = "example";', fixtureDoc), /Unsupported route/);
assert.throws(() => checkContracts(fixtureSource + "\n$route['baru'] = 'new/index';", fixtureDoc), /missing a page contract/);
assert.throws(() => checkContracts(fixtureSource, fixtureDoc.replace('`masuk`', '`hapus`')), /missing a page contract/);
assert.throws(() => checkContracts(fixtureSource, fixtureDoc.replace('`masuk`', '`masuk`, `hapus`')), /removed\/unconfigured/);
assert.throws(() => checkContracts(fixtureSource, fixtureDoc.replace(fixtureRow, fixtureRow + '\n' + fixtureRow)), /Duplicate/);
assert.throws(() => checkContracts(fixtureSource, fixtureDoc.replace(' Izin/aksi ', ' ')), /empty/);
assert.throws(() => checkContracts(fixtureSource, fixtureDoc.replace(' | Keadaan', ' Keadaan')), /Each contract/);
assert.throws(() => checkContracts(fixtureSource, fixtureDoc.replace('## Kontrak', '## Bukan kontrak')), /missing/);

const project = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const result = checkContracts(
	await readFile(path.join(project, 'application/config/routes.php'), 'utf8'),
	await readFile(path.join(project, 'docs/SITEMAP_DAN_ALUR_PENGGUNA.md'), 'utf8')
);
console.log(`PASS ${result.routes} explicit routes covered by ${result.rows} page/action contracts; missing/obsolete/duplicate/incomplete contract self-tests passed. Coverage is not a behavioral or permission test.`);
