import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadPracticeRecoveryFixtures } from './browser-practice-recovery.mjs';

const phpPath = process.argv[process.argv.indexOf('--php-path') + 1];
assert(process.argv.length === 4 && process.argv[2] === '--php-path' && path.isAbsolute(phpPath), 'Provide --php-path with one absolute PHP CLI path.');
const renderer = fileURLToPath(new URL('./render-practice-recovery.php', import.meta.url));
const baseUrl = 'http://localhost/study/index.php/';
let assertions = 0;
const check = (condition, message) => { assert(condition, message); assertions++; };
for (const kind of ['practice', 'matching', 'reading', 'flashcard']) {
	const fixtures = loadPracticeRecoveryFixtures({ phpPath, baseUrl, kind });
	for (const state of ['unavailable', 'uncertain']) {
		const markup = fixtures[state];
		check(markup.includes(`data-${kind}-recovery="${state}"`), 'Renderer must use the requested production wrapper.');
		check(!markup.includes(`data-${kind === 'matching' ? 'practice' : 'matching'}-recovery`), 'No cross-context recovery wrapper.');
		check(!/<(?:form|input|button|script|iframe)\b/i.test(markup), 'Component must contain no mutation controls or scripts.');
		check((markup.match(/<a\b/g) || []).length === 2 && markup.includes(['reading', 'flashcard'].includes(kind) ? '/beranda"' : kind === 'matching' ? 'permainan/riwayat' : '/latihan"'), 'Only two context-specific read-only links.');
	}
}
for (const invalid of ['https://localhost/study/', 'http://outside.example/study/', 'http://user:password@localhost/study/', 'http://localhost/study/?q=1', 'http://localhost/study/#part', 'http://localhost/study/\n']) {
	assert.throws(() => loadPracticeRecoveryFixtures({ phpPath, baseUrl:invalid })); assertions++;
	let status = 0;
	try { execFileSync(phpPath, [renderer, invalid], { shell:false, windowsHide:true, stdio:'pipe', timeout:10000 }); }
	catch (error) { status = error.status; }
	check(status === 1, 'PHP renderer must independently reject invalid URL input.');
}
for (const kind of ['unknown', '../games', '', null]) {
	assert.throws(() => loadPracticeRecoveryFixtures({ phpPath, baseUrl, kind })); assertions++;
}
for (const invalidPath of ['php', './php.exe', path.join(path.dirname(phpPath), 'other.exe')]) {
	assert.throws(() => loadPracticeRecoveryFixtures({ phpPath:invalidPath, baseUrl })); assertions++;
}
for (const args of [[baseUrl, '../games'], [baseUrl, ''], [baseUrl, 'matching', 'extra']]) {
	let status = 0;
	try { execFileSync(phpPath, [renderer, ...args], { shell:false, windowsHide:true, stdio:'pipe', timeout:10000 }); }
	catch (error) { status = error.status; }
	check(status === 1, 'PHP renderer must reject unknown kind or excess arguments.');
}
const defaultFixtures = JSON.parse(execFileSync(phpPath, [renderer, baseUrl], { encoding:'utf8', shell:false, windowsHide:true, stdio:['ignore','pipe','pipe'], timeout:10000 }));
check(defaultFixtures.uncertain.includes('data-practice-recovery="uncertain"'), 'Existing single-URL renderer usage must retain practice context.');
console.log(`PASS ${assertions} transaction recovery fixture checks: actual production wrappers, four contexts, backward-compatible default, URL/kind/executable guards. No HTTP, database or browser used.`);
