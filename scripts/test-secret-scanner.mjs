import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const scannerPath = path.join(projectRoot, 'node_modules', 'secretlint', 'bin', 'secretlint.js');
const configPath = path.join(projectRoot, '.secretlintrc.json');
const temporaryDirectory = await mkdtemp(path.join(os.tmpdir(), 'study-secretlint-check-'));
const positiveFile = path.join(temporaryDirectory, 'database-connection-example.txt');
const negativeFile = path.join(temporaryDirectory, 'ordinary-placeholder.txt');
const examplePassword = ['Study', 'Only', 'Not', 'A', 'Credential', '8472'].join('-');
const exampleCredential = `postgres://study_user:${examplePassword}@example.invalid/study`;

function runScanner(filePath) {
	return spawnSync(process.execPath, [scannerPath, '--no-color', '--no-gitignore', '--secretlintrc', configPath, '--no-glob', filePath], {
		cwd: projectRoot,
		encoding: 'utf8',
		maxBuffer: 1024 * 1024
	});
}

try {
	await writeFile(positiveFile, `${exampleCredential}\n`, 'utf8');
	await writeFile(negativeFile, 'placeholder=not-a-real-secret\n', 'utf8');

	const positive = runScanner(positiveFile);
	assert.equal(positive.error, undefined, 'Secretlint process should start successfully');
	assert.equal(positive.status, 1, 'Secretlint should fail when the known dummy credential format is present');
	assert.ok(!`${positive.stdout}${positive.stderr}`.includes(exampleCredential), 'Secretlint output must not reveal the dummy credential value');
	assert.ok(!`${positive.stdout}${positive.stderr}`.includes(examplePassword), 'Secretlint output must mask the dummy password');

	const negative = runScanner(negativeFile);
	assert.equal(negative.error, undefined, 'Secretlint process should start successfully for the negative control');
	assert.equal(negative.status, 0, 'Secretlint should pass a harmless placeholder');

	console.log('PASS secret scanner detects a known dummy credential, passes a harmless placeholder, and never echoes the credential value.');
}
finally {
	await rm(temporaryDirectory, { recursive: true, force: true });
}
