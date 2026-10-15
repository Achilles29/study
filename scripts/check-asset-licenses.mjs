import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const manifest = JSON.parse(fs.readFileSync(path.join(projectRoot, 'package.json'), 'utf8'));
const lockfile = JSON.parse(fs.readFileSync(path.join(projectRoot, 'package-lock.json'), 'utf8'));
const runtimePackages = {
	'@coreui/coreui': 'coreui',
	'bootstrap': 'bootstrap',
	'bootstrap-icons': 'bootstrap-icons'
};

function verifyRuntimePackage(name, expectedVersion, vendorDirectory, installed, locked) {
	assert.equal(locked.version, expectedVersion, `${name} lockfile version must match the exact manifest pin`);
	assert.equal(installed.version, expectedVersion, `${name} installed version must match the exact manifest pin`);
	assert.equal(typeof locked.license, 'string', `${name} lockfile must declare a license`);
	assert.equal(installed.license, locked.license, `${name} installed and locked license metadata must agree`);
	assert.ok(installed.license.trim().length > 0, `${name} license metadata must not be empty`);

	const bundledLicensePath = path.join(projectRoot, 'assets', 'vendor', vendorDirectory, 'LICENSE');
	assert.ok(fs.existsSync(bundledLicensePath), `${name} license file must be copied with runtime assets`);
	assert.ok(fs.readFileSync(bundledLicensePath, 'utf8').trim().length > 0, `${name} bundled license file must not be empty`);
	return installed.license;
}

const verified = [];
for (const [name, vendorDirectory] of Object.entries(runtimePackages)) {
	const expectedVersion = manifest.dependencies?.[name];
	const lockKey = `node_modules/${name}`;
	const locked = lockfile.packages?.[lockKey];
	const installedPath = path.join(projectRoot, 'node_modules', name, 'package.json');
	assert.ok(expectedVersion && /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/.test(expectedVersion), `${name} must be pinned to an exact version`);
	assert.ok(locked, `${name} must exist in package-lock.json`);
	assert.ok(fs.existsSync(installedPath), `${name} must be installed before license checks run`);
	const installed = JSON.parse(fs.readFileSync(installedPath, 'utf8'));
	const license = verifyRuntimePackage(name, expectedVersion, vendorDirectory, installed, locked);
	verified.push(`${name}@${expectedVersion} (${license})`);
}

console.log(`PASS runtime asset versions, lockfile metadata, and bundled license files: ${verified.join(', ')}.`);
