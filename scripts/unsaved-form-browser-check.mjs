import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, readFile, realpath, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const argument = (name, fallback = '') => {
  const index = process.argv.indexOf(name);
  return index >= 0 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
};
const baseUrl = argument('--base-url');
const chromePath = argument('--chrome');
const courseId = argument('--course-id');
const lessonId = argument('--lesson-id');
const suffix = argument('--fixture-suffix');
const captureArgument = argument('--capture-dir');
const identity = process.env.STUDY_QA_LOGIN_IDENTITY || '';
const password = process.env.STUDY_QA_LOGIN_PASSWORD || '';
const parsed = new URL(baseUrl);
assert.ok(parsed.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(parsed.hostname) &&
  !parsed.username && !parsed.password && !parsed.search && !parsed.hash && parsed.pathname.endsWith('/'), 'Only an HTTP loopback application base ending in / is supported.');
assert.match(courseId, /^[1-9][0-9]{0,9}$/);
assert.match(lessonId, /^[1-9][0-9]{0,9}$/);
assert.match(suffix, /^[a-f0-9]{16}$/);
if (identity !== `qa_unsaved_${suffix}`) throw new Error('Only the exact synthetic editor identity is accepted.');
assert.ok(chromePath && password, 'Explicit Chrome path and synthetic login environment are required.');
const health = await fetch(baseUrl, { redirect: 'manual', signal: AbortSignal.timeout(10000) });
assert.equal(health.status, 200, 'Existing loopback app must be healthy before Chrome starts.');
await health.body?.cancel();
let captureDir;
if (captureArgument) {
  const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
  const expectedBaseline = path.join(projectRoot, 'docs', 'visual-baseline');
  const baseline = await realpath(expectedBaseline);
  assert.equal(baseline, expectedBaseline, 'Screenshot baseline must not be redirected outside the repository.');
  captureDir = path.resolve(captureArgument);
  assert.equal(path.dirname(captureDir), baseline, 'Captures require one new direct subdirectory of docs/visual-baseline.');
  assert.match(path.basename(captureDir), new RegExp(`^\\d{4}-\\d{2}-\\d{2}-unsaved-forms-${suffix}$`));
  await mkdir(captureDir); // Exclusive directory creation; never overwrite an earlier run.
}
const temporaryRoot = await realpath(os.tmpdir());
const profile = await mkdtemp(path.join(temporaryRoot, 'study-unsaved-browser-'));
const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
let chrome;
let chromeStopped = false;
let socket;
let phase = 'browser setup';

async function cleanupProfile() {
  const resolved = await realpath(profile);
  assert.equal(resolved, profile, 'Temporary Chrome profile must remain its own canonical path.');
  const relative = path.relative(temporaryRoot, resolved);
  assert.ok(relative.startsWith('study-unsaved-browser-') && !relative.includes(path.sep) && !path.isAbsolute(relative), 'Only this single temporary Chrome profile may be removed.');
  for (let attempt = 0; attempt < 20; attempt += 1) {
    try { await rm(resolved, { recursive: true }); return; }
    catch (error) {
      if (!['EBUSY', 'EPERM', 'ENOTEMPTY'].includes(error.code) || attempt === 19) throw error;
      await delay(250);
    }
  }
}

try {
  // No PHP worker, proxy, or HTTP listener is launched. This profile is test-owned.
  chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--window-size=1280,900', 'about:blank'], { stdio: 'ignore', windowsHide: true });
  chrome.once('exit', () => { chromeStopped = true; });
  let launchError = false;
  chrome.on('error', () => { launchError = true; });
  let port;
  for (let attempt = 0; attempt < 100; attempt += 1) {
    assert.ok(!launchError && !chromeStopped && chrome.exitCode === null && chrome.signalCode === null, 'Chrome failed to start.');
    try {
      const number = Number((await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split(/\r?\n/)[0]);
      if (Number.isInteger(number) && number > 0 && number <= 65535) { port = number; break; }
    } catch { /* Fresh Chrome profile has not exposed DevTools yet. */ }
    await delay(100);
  }
  assert.ok(port, 'Chrome did not expose DevTools.');
  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`, { signal: AbortSignal.timeout(10000) })).json();
  const target = targets.find(item => item.type === 'page' && item.webSocketDebuggerUrl);
  assert.ok(target, 'Chrome did not expose a page target.');
  const socketUrl = new URL(target.webSocketDebuggerUrl);
  socketUrl.hostname = '127.0.0.1';
  socket = new WebSocket(socketUrl);
  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', () => reject(new Error('Chrome DevTools connection failed.')), { once: true });
  });
  const pending = new Map();
  const listeners = new Map();
  let commandId = 0;
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++commandId;
    const timer = setTimeout(() => { pending.delete(id); reject(new Error('Chrome DevTools command timed out.')); }, 15000);
    pending.set(id, { resolve, reject, timer });
    socket.send(JSON.stringify({ id, method, params }));
  });
  socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const request = pending.get(message.id);
      pending.delete(message.id); clearTimeout(request.timer);
      if (message.error) request.reject(new Error('Chrome DevTools rejected a command.'));
      else request.resolve(message.result || {});
    }
    if (message.method) for (const listener of listeners.get(message.method) || []) listener(message.params || {});
  });
  socket.addEventListener('close', () => {
    for (const request of pending.values()) { clearTimeout(request.timer); request.reject(new Error('Chrome DevTools closed.')); }
    pending.clear();
  });
  const on = (event, listener) => { if (!listeners.has(event)) listeners.set(event, new Set()); listeners.get(event).add(listener); };
  const waitForLoad = () => {
    const promise = new Promise((resolve, reject) => {
      const listener = () => { clearTimeout(timer); listeners.get('Page.loadEventFired').delete(listener); resolve(); };
      const timer = setTimeout(() => { listeners.get('Page.loadEventFired').delete(listener); reject(new Error('Page navigation timed out.')); }, 15000);
      on('Page.loadEventFired', listener);
    });
    // Keep a failed command from leaving an unhandled navigation rejection;
    // awaiting this same promise still propagates its failure normally.
    promise.catch(() => {});
    return promise;
  };
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture: true });
    assert.ok(!result.exceptionDetails, 'A browser assertion/evaluation failed.');
    return result.result?.value;
  };
  const navigate = async route => {
    const loaded = waitForLoad();
    await send('Page.navigate', { url: new URL(route, baseUrl).href });
    await loaded;
  };
  const capture = async (name, width) => {
    if (!captureDir) return;
    await evaluate('document.fonts.ready.then(() => { document.activeElement?.blur(); window.scrollTo(0, 0); return true; })');
    const metrics = await send('Page.getLayoutMetrics');
    const size = metrics.cssContentSize || metrics.contentSize;
    const result = await send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: Math.min(8000, Math.max(1, Math.ceil(size.height))), scale: 1 } });
    await writeFile(path.join(captureDir, name + '.png'), Buffer.from(result.data, 'base64'), { flag: 'wx' });
  };
  await send('Page.enable');
  await send('Runtime.enable');
  let dialogCount = 0;
  let dialogError = false;
  on('Page.javascriptDialogOpening', () => {
    dialogCount += 1;
    // Accept only to avoid stranding a test-owned browser. Unexpected submission
    // dialogs still fail assertions; their presence is never silently accepted.
    send('Page.handleJavaScriptDialog', { accept: true }).catch(() => { dialogError = true; });
  });
  phase = 'synthetic editor login';
  await navigate('masuk');
  let loaded = waitForLoad();
  assert.equal(await evaluate(`(() => {
    const form = document.querySelector('form');
    if (!form?.elements.identity || !form.elements.password) return false;
    form.elements.identity.value = ${JSON.stringify(identity)};
    form.elements.password.value = ${JSON.stringify(password)};
    form.requestSubmit(); return true;
  })()`), true, 'Synthetic login form was unavailable.');
  await loaded;
  const routes = ['kelola/kursus/tambah', `kelola/kursus/${courseId}/edit`, `kelola/kursus/${courseId}/pelajaran/tambah`, `kelola/kursus/${courseId}/pelajaran/${lessonId}/edit`];
  let cases = 0;
  for (const width of [360, 768, 1280]) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
    for (const route of routes) {
      phase = 'clean/edit/revert/native-validation state';
      await navigate(route);
      const result = await evaluate(`(async () => {
        const form = document.querySelector('form[data-unsaved-form]');
        const status = form?.querySelector('[data-unsaved-status]');
        if (!form || !status || form.dataset.unsavedEnhanced !== 'true') return { ready: false };
        const clean = 'Belum ada perubahan baru.';
        const dirty = 'Ada perubahan yang belum disimpan.';
        const guard = () => { const event = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(event); return event.defaultPrevented; };
        const title = form.elements.title;
        const checkbox = form.querySelector('input[type="checkbox"]');
        const original = title.value;
        const initial = status.textContent === clean && !status.hidden && !guard() && form.dataset.unsavedInitial === 'false';
        title.value = original + ' synthetic edit'; title.dispatchEvent(new Event('input', { bubbles: true }));
        const edited = status.textContent === dirty && guard();
        title.value = original; title.dispatchEvent(new Event('input', { bubbles: true }));
        const reverted = status.textContent === clean && !guard();
        checkbox.checked = !checkbox.checked; checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        const checked = status.textContent === dirty && guard();
        checkbox.checked = !checkbox.checked; checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        const checkboxReverted = status.textContent === clean && !guard();
        const hidden = form.querySelector('input[type="hidden"]');
        const hiddenValue = hidden?.value;
        if (hidden) { hidden.value = hiddenValue + 'test'; hidden.dispatchEvent(new Event('input', { bubbles: true })); }
        const ignoredHidden = status.textContent === clean && !guard();
        if (hidden) { hidden.value = hiddenValue; hidden.dispatchEvent(new Event('input', { bubbles: true })); }
        title.value = original + ' synthetic edit'; title.dispatchEvent(new Event('input', { bubbles: true }));
        const cancelled = event => event.preventDefault();
        form.addEventListener('submit', cancelled);
        const event = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(event);
        const immediateCancelled = event.defaultPrevented && guard();
        await new Promise(resolve => setTimeout(resolve, 0));
        const laterCancelled = guard() && status.textContent === dirty;
        form.removeEventListener('submit', cancelled);
        const duration = form.elements.estimated_minutes;
        const previousDuration = duration.value;
        duration.value = '0'; duration.dispatchEvent(new Event('input', { bubbles: true }));
        const invalid = !form.checkValidity();
        form.requestSubmit();
        await new Promise(resolve => setTimeout(resolve, 0));
        const nativeValidation = invalid && status.textContent === dirty && guard();
        duration.value = previousDuration; duration.dispatchEvent(new Event('input', { bubbles: true }));
        title.value = original; title.dispatchEvent(new Event('input', { bubbles: true }));
        const finalClean = status.textContent === clean && !guard();
        return { ready: true, initial, edited, reverted, checked, checkboxReverted, ignoredHidden, immediateCancelled, laterCancelled, nativeValidation, finalClean,
          withinViewport: document.documentElement.scrollWidth <= window.innerWidth, polite: status.getAttribute('role') === 'status' && status.getAttribute('aria-live') === 'polite' };
      })()`);
      for (const [name, passed] of Object.entries(result || {})) assert.equal(passed, true, `Unsaved browser state failed: ${name}.`);
      assert.equal(result?.ready, true, 'Opt-in script must enhance the real form.');
      if (route === routes[1] && [360, 1280].includes(width) && captureDir) {
        const original = await evaluate(`(() => { const field = document.querySelector('form[data-unsaved-form]').elements.title; const value = field.value; field.value += ' perubahan belum disimpan'; field.dispatchEvent(new Event('input', { bubbles: true })); return value; })()`);
        await capture(`course-edit-dirty-${width}`, width);
        await evaluate(`(() => { const field = document.querySelector('form[data-unsaved-form]').elements.title; field.value = ${JSON.stringify(original)}; field.dispatchEvent(new Event('input', { bubbles: true })); return true; })()`);
      }
      cases += 1;
    }
  }
  phase = 'JavaScript-disabled native form fallback';
  await send('Emulation.setScriptExecutionDisabled', { value: true });
  try {
    for (const route of [routes[1], routes[3]]) {
      await navigate(route);
      const document = await send('DOM.getDocument');
      const form = await send('DOM.querySelector', { nodeId: document.root.nodeId, selector: 'form[data-unsaved-form]' });
      assert.ok(form.nodeId, 'Content editor form must exist without page JavaScript.');
      const attributes = await send('DOM.getAttributes', { nodeId: form.nodeId });
      const pairs = new Map();
      for (let index = 0; index < attributes.attributes.length; index += 2) pairs.set(attributes.attributes[index], attributes.attributes[index + 1]);
      assert.equal(pairs.get('method'), 'post', 'No-JS editor must retain native POST.');
      assert.equal(pairs.has('data-unsaved-enhanced'), false, 'No-JS editor must not claim enhancement.');
      const status = await send('DOM.querySelector', { nodeId: form.nodeId, selector: '[data-unsaved-status][hidden]' });
      assert.ok(status.nodeId, 'No-JS unsaved state must remain hidden, not misleadingly clean.');
      const html = (await send('DOM.getOuterHTML', { nodeId: form.nodeId })).outerHTML;
      assert.ok(html.includes('Belum ada simpan otomatis.') && html.includes('name="study_csrf_token"') && html.includes('name="title"') && html.includes('type="submit"'), 'No-JS editor must retain manual-save guidance, CSRF, inputs and native submission control.');
    }
  } finally { await send('Emulation.setScriptExecutionDisabled', { value: false }); }
  // Both existing edit forms are valid in the fixture. A one-character title
  // satisfies native required/maxlength but fails the server minlength rule.
  // This exercises an actual native POST rather than a synthetic submit event.
  for (const route of [routes[1], routes[3]]) {
    phase = 'native server-rejected POST without a navigation warning';
    await navigate(route);
    const beforeDialogs = dialogCount;
    loaded = waitForLoad();
    assert.equal(await evaluate(`(() => {
      const form = document.querySelector('form[data-unsaved-form]');
      form.elements.title.value = 'x';
      form.elements.title.dispatchEvent(new Event('input', { bubbles: true }));
      if (!form.checkValidity()) return false;
      form.requestSubmit(); return true;
    })()`), true, 'Server-invalid title must still satisfy native browser validation.');
    await loaded;
    assert.equal(dialogCount, beforeDialogs, 'Native form submission must not trigger an unsaved-leave dialog.');
    const failed = await evaluate(`(() => {
      const form = document.querySelector('form[data-unsaved-form]');
      const status = form?.querySelector('[data-unsaved-status]');
      const event = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(event);
      return Boolean(form && status && form.dataset.unsavedInitial === 'true' && form.dataset.unsavedEnhanced === 'true' && form.elements.title.value === 'x' &&
        form.elements.title.getAttribute('aria-invalid') === 'true' && !status.hidden && status.textContent === 'Ada perubahan yang belum disimpan.' && event.defaultPrevented);
    })()`);
    assert.equal(failed, true, 'Server-rejected native submission must retain its input and start dirty.');
    if (route === routes[3]) await capture('lesson-failed-post-1280', 1280);
  }
  assert.equal(dialogError, false, 'A browser dialog could not be handled safely.');
  console.log(`PASS ${cases} opt-in route/viewport checks: clean, input/revert, checkbox, hidden controls, cancelled/native-invalid submissions and polite state. Two JavaScript-disabled editors retain native forms. Two native server-rejected POSTs preserve input without a leave warning. Conditional beforeunload was also tested with synthetic events; native leave-dialog availability is not guaranteed on every browser/device.`);
  if (captureDir) console.log(`PASS three new synthetic screenshots saved in ${captureDir}; visual review is still required.`);
} catch (error) {
  // Do not print evaluated expressions, credentials or retained form content.
  console.error(`FAIL unsaved-form browser check during ${phase}: ${error instanceof assert.AssertionError ? error.message : 'browser/transport operation failed'}`);
  process.exitCode = 1;
} finally {
  socket?.close();
  const chromeRunning = () => Boolean(chrome?.pid && !chromeStopped && chrome.exitCode === null && chrome.signalCode === null);
  if (chromeRunning()) {
    const stopped = new Promise(resolve => {
      chrome.once('exit', resolve);
      // An exit may have happened between the first state check and listener.
      if (!chromeRunning()) resolve();
    });
    chrome.kill();
    await Promise.race([stopped, delay(5000)]);
    if (chromeRunning()) { chrome.kill('SIGKILL'); await Promise.race([stopped, delay(5000)]); }
    if (chromeRunning()) throw new Error('Chrome did not stop; its temporary profile was retained.');
  }
  await cleanupProfile();
}
