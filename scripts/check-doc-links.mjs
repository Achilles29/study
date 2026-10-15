import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const docsDirectory = path.resolve(scriptDirectory, '..', 'docs');

if (!fs.existsSync(docsDirectory) || !fs.statSync(docsDirectory).isDirectory()) {
	console.error(`Documentation directory was not found: ${docsDirectory}`);
	process.exit(1);
}

function markdownFiles(directory) {
	return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const entryPath = path.join(directory, entry.name);
		if (entry.isDirectory()) return markdownFiles(entryPath);
		return entry.isFile() && entry.name.toLowerCase().endsWith('.md') ? [entryPath] : [];
	});
}

function stripCodeFences(source) {
	return source.replace(/^\s{0,3}(`{3,}|~{3,})[^\n]*\n[\s\S]*?^\s{0,3}\1\s*$/gm, '');
}

function decodeEntities(value) {
	const named = { amp: '&', apos: "'", gt: '>', lt: '<', nbsp: ' ', quot: '"' };
	return value.replace(/&(#x[\da-f]+|#\d+|[a-z]+);/gi, (entity, token) => {
		if (token[0] !== '#') return named[token.toLowerCase()] ?? entity;
		const codePoint = token[1].toLowerCase() === 'x'
			? Number.parseInt(token.slice(2), 16)
			: Number.parseInt(token.slice(1), 10);
		return Number.isInteger(codePoint) && codePoint >= 0 && codePoint <= 0x10ffff
			? String.fromCodePoint(codePoint)
			: entity;
	});
}

function headingSlug(heading) {
	const text = decodeEntities(heading)
		.replace(/!??\[([^\]]*)\]\([^)]*\)/g, '$1')
		.replace(/`([^`]*)`/g, '$1')
		.replace(/<[^>]*>/g, '')
		.replace(/[\\*_~]/g, '')
		.toLocaleLowerCase('en-US')
		.normalize('NFC');
	return text
		.replace(/[^\p{L}\p{N}\p{M}\s_-]/gu, '')
		.trim()
		.replace(/\s+/g, '-');
}

function headingIds(source) {
	const ids = new Set();
	const usedSlugs = new Set();
	const lines = stripCodeFences(source).split(/\r?\n/);

	for (const line of lines) {
		const heading = line.match(/^\s{0,3}(?:>\s*){0,3}#{1,6}\s+(.+?)\s*#*\s*$/);
		if (!heading) continue;

		const baseSlug = headingSlug(heading[1]);
		if (!baseSlug) continue;

		let slug = baseSlug;
		let suffix = 0;
		while (usedSlugs.has(slug)) {
			suffix += 1;
			slug = `${baseSlug}-${suffix}`;
		}
		usedSlugs.add(slug);
		ids.add(slug);
	}

	for (const match of stripCodeFences(source).matchAll(/\bid\s*=\s*(["'])(.*?)\1/gi)) {
		ids.add(match[2]);
	}

	return ids;
}

function checkMarkdownLinks(markdownPath, source, docsRoot, sources) {
	const failures = [];
	const linkPattern = /!?\[[^\]]*\]\((?:<([^>]+)>|([^\s)]+))(?:\s+[^)]*)?\)/g;
	const strippedSource = stripCodeFences(source);
	let match;

	while ((match = linkPattern.exec(strippedSource)) !== null) {
		const destination = (match[1] || match[2] || '').replace(/\\([ ()])/g, '$1');
		if (!destination || /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(destination)) continue;

		const hashIndex = destination.indexOf('#');
		const fragment = hashIndex >= 0 ? destination.slice(hashIndex + 1).split('?')[0] : '';
		const pathAndQuery = hashIndex >= 0 ? destination.slice(0, hashIndex) : destination;
		const pathPart = pathAndQuery.split('?')[0];
		let decodedPath;
		let decodedFragment = fragment;
		try {
			decodedPath = decodeURIComponent(pathPart);
			if (fragment) decodedFragment = decodeURIComponent(fragment);
		} catch {
			failures.push(`${path.relative(docsRoot, markdownPath)}: malformed URL encoding in ${destination}`);
			continue;
		}

		const targetPath = path.resolve(path.dirname(markdownPath), decodedPath || path.basename(markdownPath));
		if (!targetPath.startsWith(`${path.resolve(docsRoot)}${path.sep}`) && targetPath !== path.resolve(docsRoot)) {
			failures.push(`${path.relative(docsRoot, markdownPath)}: link escapes docs directory (${destination})`);
			continue;
		}

		if (decodedPath && !fs.existsSync(targetPath) && !sources.has(targetPath)) {
			failures.push(`${path.relative(docsRoot, markdownPath)}: missing target ${destination}`);
			continue;
		}

		if (decodedFragment && path.extname(targetPath).toLowerCase() === '.md') {
			const targetSource = sources.get(targetPath);
			if (targetSource === undefined) continue;
			const ids = headingIds(targetSource);
			if (!ids.has(decodedFragment)) {
				failures.push(`${path.relative(docsRoot, markdownPath)}: missing fragment #${decodedFragment} in ${path.relative(docsRoot, targetPath)}`);
			}
		}
	}

	return failures;
}

function runSelfTests() {
	const sourcePath = path.resolve(docsDirectory, '__anchor_self_test__.md');
	const targetPath = path.resolve(docsDirectory, 'anchor-target.md');
	const sources = new Map([
		[sourcePath, '# Ringkasan &amp; Tujuan\n# Ringkasan &amp; Tujuan\n\n```md\n# bukan-heading\n```'],
		[targetPath, '## Hasil `Belajar`\n\n<a id="bagian-manual"></a>']
	]);

	assert.deepEqual([...headingIds(sources.get(sourcePath))], ['ringkasan-tujuan', 'ringkasan-tujuan-1']);
	assert.deepEqual([...headingIds(sources.get(targetPath))], ['hasil-belajar', 'bagian-manual']);
	assert.deepEqual(checkMarkdownLinks(sourcePath, '[duplikat](#ringkasan-tujuan-1) [lintas berkas](anchor-target.md#hasil-belajar) [manual](anchor-target.md#bagian-manual)', docsDirectory, sources), []);
	assert.equal(checkMarkdownLinks(sourcePath, '[rusak](#tidak-ada)', docsDirectory, sources).length, 1);
	assert.equal(checkMarkdownLinks(sourcePath, '[hilang](missing.md)', docsDirectory, sources).length, 1);
	assert.equal(checkMarkdownLinks(sourcePath, '[keluar](../outside.md)', docsDirectory, sources).length, 1);
	assert.equal(checkMarkdownLinks(sourcePath, '[encoding rusak](target.md#%E0%A4%A)', docsDirectory, sources).length, 1);
	assert.deepEqual(checkMarkdownLinks(sourcePath, '```md\n[diabaikan](missing.md#nope)\n```', docsDirectory, sources), []);
}

runSelfTests();

const brokenLinks = [];
const files = markdownFiles(docsDirectory);
const sources = new Map(files.map((file) => [path.resolve(file), fs.readFileSync(file, 'utf8')]));

for (const [markdownPath, source] of sources) {
	brokenLinks.push(...checkMarkdownLinks(markdownPath, source, docsDirectory, sources));
}

if (brokenLinks.length > 0) {
	console.error(`Found ${brokenLinks.length} broken relative Markdown link(s):`);
	for (const brokenLink of brokenLinks) console.error(`- ${brokenLink}`);
	process.exit(1);
}

console.log(`PASS relative Markdown links and heading fragments resolve within docs (${files.length} files checked; self-tests passed).`);

