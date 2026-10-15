import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceRoots = ['application', 'system', 'scripts', 'assets', 'docs'];
const textExtensions = new Set(['.conf', '.css', '.html', '.htaccess', '.js', '.json', '.md', '.mjs', '.php', '.ps1', '.rst', '.sql', '.txt', '.xml', '.yml', '.yaml']);

function stripMarkdownCodeFences(source) {
	return source.replace(/^\s{0,3}(`{3,}|~{3,})[^\n]*\n[\s\S]*?^\s{0,3}\1\s*$/gm, (block) => block.replace(/[^\n]/g, ''));
}

function findConflictMarkers(source, extension) {
	const searchableSource = extension === '.md' ? stripMarkdownCodeFences(source) : source;
	const markers = [];
	const lines = searchableSource.split(/\r?\n/);
	const openingLines = [];
	const closingLines = [];
	for (let index = 0; index < lines.length; index += 1) {
		if (/^\s*<{7}(?:\s|$)/.test(lines[index])) {
			openingLines.push(index + 1);
			markers.push(index + 1);
		}
		if (/^\s*>{7}(?:\s|$)/.test(lines[index])) {
			closingLines.push(index + 1);
			markers.push(index + 1);
		}
		if (/^\s*={7}\s*$/.test(lines[index]) && extension !== '.md') markers.push(index + 1);
	}
	if (extension === '.md') {
		for (let index = 0; index < lines.length; index += 1) {
			if (!/^\s*={7}\s*$/.test(lines[index])) continue;
			if (openingLines.some((line) => line < index + 1) && closingLines.some((line) => line > index + 1)) {
				markers.push(index + 1);
			}
		}
	}
	return [...new Set(markers)].sort((left, right) => left - right);
}

function walk(directory) {
	return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const entryPath = path.join(directory, entry.name);
		if (entry.isDirectory()) {
			if (entry.name === '.git' || entry.name === 'node_modules' || entryPath === path.join(projectRoot, 'assets', 'vendor')) return [];
			return walk(entryPath);
		}
		return entry.isFile() && textExtensions.has(path.extname(entry.name).toLowerCase()) ? [entryPath] : [];
	});
}

function runSelfTests() {
	assert.deepEqual(findConflictMarkers('const value = 1;\n', '.js'), []);
	assert.deepEqual(findConflictMarkers('<<<<<<< HEAD\nleft\n=======\nright\n>>>>>>> branch\n', '.js'), [1, 3, 5]);
	assert.deepEqual(findConflictMarkers('# Catatan\n\n```text\n<<<<<<< contoh\n=======\n>>>>>>> contoh\n```\n', '.md'), []);
	assert.deepEqual(findConflictMarkers('Judul dokumen\n=======\n', '.md'), []);
	assert.deepEqual(findConflictMarkers('<<<<<<< HEAD\nversi A\n=======\nversi B\n>>>>>>> cabang\n', '.md'), [1, 3, 5]);
}

runSelfTests();

const files = sourceRoots.flatMap((root) => walk(path.join(projectRoot, root)));
files.push(...['.htaccess', 'composer.json', 'index.php', 'package.json'].map((file) => path.join(projectRoot, file)).filter((file) => fs.existsSync(file)));
const conflicts = [];

for (const file of files) {
	const extension = path.extname(file).toLowerCase() || (path.basename(file) === '.htaccess' ? '.htaccess' : '');
	const lines = findConflictMarkers(fs.readFileSync(file, 'utf8'), extension);
	for (const line of lines) conflicts.push(`${path.relative(projectRoot, file)}:${line}`);
}

if (conflicts.length > 0) {
	console.error(`Found ${conflicts.length} possible merge-conflict marker(s):`);
	for (const conflict of conflicts) console.error(`- ${conflict}`);
	process.exit(1);
}

console.log(`PASS no merge-conflict markers in ${files.length} first-party text files (self-tests passed).`);
