import { copyFile, mkdir, rm } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const vendor = join(root, 'assets', 'vendor');
const copies = [
  ['node_modules/bootstrap/dist/css/bootstrap.min.css', 'bootstrap/css/bootstrap.min.css'],
  ['node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap/js/bootstrap.bundle.min.js'],
  ['node_modules/bootstrap-icons/font/bootstrap-icons.min.css', 'bootstrap-icons/font/bootstrap-icons.min.css'],
  ['node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff2', 'bootstrap-icons/font/fonts/bootstrap-icons.woff2'],
  ['node_modules/bootstrap-icons/LICENSE', 'bootstrap-icons/LICENSE'],
  ['node_modules/bootstrap/LICENSE', 'bootstrap/LICENSE'],
  ['node_modules/@coreui/coreui/dist/css/coreui.min.css', 'coreui/css/coreui.min.css'],
  ['node_modules/@coreui/coreui/dist/js/coreui.bundle.min.js', 'coreui/js/coreui.bundle.min.js'],
  ['node_modules/@coreui/coreui/LICENSE', 'coreui/LICENSE']
];

for (const [source, target] of copies) {
  const output = join(vendor, target);
  await mkdir(dirname(output), { recursive: true });
  await copyFile(join(root, source), output);
}

await rm(join(root, 'assets', 'vendor', '.build-placeholder'), { force: true });
console.log(`Copied ${copies.length} pinned vendor files into assets/vendor.`);
