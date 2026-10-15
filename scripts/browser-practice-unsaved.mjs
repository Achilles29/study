import assert from 'node:assert/strict';
import { realpath, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const cleanText = 'Belum ada perubahan baru.';
const dirtyText = 'Ada perubahan yang belum disimpan.';
const formSelector = '#practice-answer-form[data-unsaved-form]';
const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

// Run inside the caller's already-authenticated, isolated CDP browser. The only
// POST below uses a deliberately stale revision and the save (never finish) action.
export async function runPracticeUnsavedChecks({ send, client, navigateTo, waitForPageLoad, baseUrl, route, width, screenshotDir }) {
  const base = new URL(baseUrl);
  assert.ok(base.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(base.hostname) &&
    !base.username && !base.password && !base.search && !base.hash && base.pathname.endsWith('/'), 'Practice unsaved checks require an HTTP loopback application base.');
  assert.match(route.path, /^latihan\/percobaan\/[1-9][0-9]{0,9}$/);
  assert.match(process.env.STUDY_QA_LOGIN_IDENTITY || '', /^qa_(?:practice|delivery)_child_[a-f0-9]{12}$/);
  assert.ok([360, 768, 1280].includes(width), 'Practice unsaved checks use the three ordinary viewport widths.');
  const attemptUrl = new URL(route.path, base).href;
  const historyUrl = new URL('latihan', base).href;
  const formExpression = `document.querySelector(${JSON.stringify(formSelector)})`;
  const snapshotExpression = `Array.from(form.querySelectorAll('input[type="radio"][name^="answers["]')).map(field => [field.name, field.value, field.checked])`;
  const guardExpression = `const event = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(event);`;
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture: true });
    assert.ok(!result.exceptionDetails, 'Practice unsaved browser evaluation failed.');
    return result.result?.value;
  };
  let phase = 'initial state';
  let scriptsDisabled = false;
  let nativeDialogCount = 0;
  let dialogError = null;
  let expectedDialog = null;
  let postResponse = null;
  let observePost = false;
  const dialogTasks = new Set();
  const listener = event => {
    let message;
    try { message = JSON.parse(event.data); } catch { return; }
    if (message.method === 'Network.responseReceived' && observePost) {
      const response = message.params?.response;
      if (message.params?.type === 'Document' && response?.url === attemptUrl) postResponse = response.status;
    }
    if (message.method !== 'Page.javascriptDialogOpening') return;
    nativeDialogCount += 1;
    const waiting = expectedDialog;
    if (message.params?.type !== 'beforeunload' || phase !== 'cancel dirty native navigation') {
      dialogError = new Error('Unexpected native dialog during practice unsaved checks.');
    }
    // Never approve leaving a dirty page, including an unexpected dialog.
    const task = send('Page.handleJavaScriptDialog', { accept: false }).then(() => {
      waiting?.resolve(message.params?.type);
    }).catch(() => {
      dialogError = new Error('Practice unsaved dialog could not be cancelled.');
      waiting?.reject(dialogError);
    }).finally(() => { dialogTasks.delete(task); });
    dialogTasks.add(task);
  };
  const waitForDialog = () => {
    let timer;
    let done = false;
    let settleResolve;
    let settleReject;
    const promise = new Promise((resolve, reject) => { settleResolve = resolve; settleReject = reject; });
    promise.catch(() => {});
    const settle = (error, value) => {
      if (done) return;
      done = true;
      clearTimeout(timer);
      expectedDialog = null;
      if (error) settleReject(error); else settleResolve(value);
    };
    expectedDialog = { resolve: value => settle(null, value), reject: error => settle(error) };
    timer = setTimeout(() => settle(new Error('Native dirty-leave dialog was not observed; no native-dialog success is claimed.')), 10000);
    promise.cancel = () => settle(new Error('Cancelled practice native-dialog wait.'));
    return promise;
  };
  const navigateWithLoad = async (url, label) => {
    const loaded = waitForPageLoad(label);
    try { await Promise.all([send('Page.navigate', { url }), loaded]); }
    finally { loaded.cancel(); }
  };
  const readState = async () => evaluate(`(() => {
    const form = ${formExpression}; const status = form?.querySelector('[data-unsaved-status]');
    if (!form || !status) return null;
    ${guardExpression}
    return { url: location.href, enhanced: form.dataset.unsavedEnhanced, initial: form.dataset.unsavedInitial,
      status: status.textContent, hidden: status.hidden, role: status.getAttribute('role'), live: status.getAttribute('aria-live'),
      warned: event.defaultPrevented, choices: ${snapshotExpression}, revision: form.elements.revision.value };
  })()`);
  const assertClean = (state, baseline, label) => {
    assert.ok(state && state.url === attemptUrl && state.enhanced === 'true' && state.initial === 'false' &&
      state.status === cleanText && state.hidden === false && state.warned === false, label);
    if (baseline) {
      assert.deepEqual(state.choices, baseline.choices, 'The authoritative saved answers must remain unchanged.');
      assert.equal(state.revision, baseline.revision, 'Rejected navigation/submission must not advance the answer revision.');
    }
  };
  const revert = async () => evaluate(`(() => {
    const form = ${formExpression}; const radios = Array.from(form.querySelectorAll('input[type="radio"][name^="answers["]'));
    radios.forEach(field => { field.checked = field.defaultChecked; });
    radios[0].dispatchEvent(new Event('change', { bubbles: true })); return true;
  })()`);

  client.socket.addEventListener('message', listener);
  try {
    await send('Network.enable');
    await navigateTo(attemptUrl, 'practice unsaved clean start');
    const baseline = await readState();
    assertClean(baseline, null, 'Practice answer form must start from the clean saved state.');
    assert.ok(baseline.choices.length >= 2 && baseline.revision !== '999999999', 'Synthetic attempt must have editable choices and a non-sentinel revision.');
    assert.equal(baseline.role, 'status');
    assert.equal(baseline.live, 'polite');
    assert.equal(await evaluate(`(() => { const form = ${formExpression}; return form.getAttribute('method')?.toLowerCase() === 'post' && new URL(form.getAttribute('action'), location.href).href === ${JSON.stringify(attemptUrl)} && document.querySelector('a.back-link')?.href === ${JSON.stringify(historyUrl)}; })()`), true, 'Only the current attempt POST and own practice history link are accepted.');

    phase = 'radio change and revert';
    const candidateIndex = await evaluate(`(() => {
      const form = ${formExpression}; const radios = Array.from(form.querySelectorAll('input[type="radio"][name^="answers["]'));
      const index = radios.findIndex(field => field.value !== '' && !field.checked && !field.disabled);
      if (index < 0) return -1;
      radios[index].checked = true;
      radios[index].dispatchEvent(new Event('input', { bubbles: true }));
      radios[index].dispatchEvent(new Event('change', { bubbles: true })); return index;
    })()`);
    assert.ok(Number.isInteger(candidateIndex) && candidateIndex >= 0, 'Synthetic practice needs an unselected answer to exercise edits.');
    let state = await readState();
    assert.ok(state.status === dirtyText && state.warned, 'A radio edit must mark the answer form dirty and enable the synthetic leave guard.');
    await revert();
    assertClean(await readState(), baseline, 'Reverting radio choices must return to the clean saved state.');

    phase = 'hidden revision ignored';
    assert.equal(await evaluate(`(() => {
      const form = ${formExpression}; const revision = form.elements.revision; const original = revision.value;
      revision.value = '999999999'; revision.dispatchEvent(new Event('input', { bubbles: true }));
      const status = form.querySelector('[data-unsaved-status]'); ${guardExpression}
      const ignored = status.textContent === ${JSON.stringify(cleanText)} && !event.defaultPrevented;
      revision.value = original; revision.dispatchEvent(new Event('change', { bubbles: true })); return ignored;
    })()`), true, 'Hidden revision changes alone must not mark answers dirty.');
    assertClean(await readState(), baseline, 'Restoring the hidden revision must leave the form clean.');

    phase = 'native keyboard answer';
    assert.equal(await evaluate(`(() => {
      const form = ${formExpression}; const field = form.querySelectorAll('input[type="radio"][name^="answers["]')[${candidateIndex}];
      field.scrollIntoView({ block: 'center', behavior: 'instant' }); field.focus(); return document.activeElement === field && !field.checked;
    })()`), true, 'An unselected native answer must receive focus.');
    await send('Input.dispatchKeyEvent', { type: 'keyDown', key: ' ', code: 'Space', windowsVirtualKeyCode: 32, text: ' ', unmodifiedText: ' ' });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', key: ' ', code: 'Space', windowsVirtualKeyCode: 32 });
    state = await readState();
    assert.ok(state.choices[candidateIndex][2] && state.status === dirtyText && state.warned, 'Native Space must select the answer and activate the dirty guard.');
    const dirtyChoices = state.choices;

    let screenshot;
    if (screenshotDir && [360, 1280].includes(width)) {
      phase = 'dirty screenshot';
      const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
      const baselineRoot = await realpath(path.join(projectRoot, 'docs', 'visual-baseline'));
      const captureRoot = await realpath(screenshotDir);
      assert.equal(path.resolve(screenshotDir), captureRoot, 'Supplied screenshot directory must be canonical, not a redirected path.');
      const relative = path.relative(baselineRoot, captureRoot);
      const insideBaseline = relative && !relative.startsWith('..') && !path.isAbsolute(relative);
      const temporaryRoot = await realpath(os.tmpdir());
      const ownedTemporaryDirectory = path.dirname(captureRoot) === temporaryRoot && /^study-visual-review-[A-Za-z0-9]+$/.test(path.basename(captureRoot));
      assert.ok(insideBaseline || ownedTemporaryDirectory, 'Practice screenshots require a repository baseline subdirectory or the supplied direct study-visual-review temporary directory.');
      await evaluate('document.fonts.ready.then(() => { window.scrollTo(0, 0); return true; })');
      await delay(200);
      const metrics = await send('Page.getLayoutMetrics');
      const size = metrics.cssContentSize || metrics.contentSize;
      const capture = await send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: true,
        clip: { x: 0, y: 0, width, height: Math.min(8000, Math.max(1, Math.ceil(size.height))), scale: 1 } });
      screenshot = path.join(captureRoot, `practice-unsaved-dirty-${width}.png`);
      await writeFile(screenshot, Buffer.from(capture.data, 'base64'), { flag: 'wx' });
    }

    phase = 'cancel dirty native navigation';
    const beforeDirtyDialogs = nativeDialogCount;
    const dialog = waitForDialog();
    try {
      await Promise.all([evaluate(`(() => { document.querySelector('a.back-link').click(); return true; })()`), dialog]);
    } finally { dialog.cancel(); }
    assert.equal(nativeDialogCount, beforeDirtyDialogs + 1, 'Dirty history navigation must produce exactly one native leave dialog.');
    assert.equal(dialogError, null);
    state = await readState();
    assert.ok(state.url === attemptUrl && state.status === dirtyText && state.warned, 'Cancelling native leave must preserve the dirty attempt page.');
    assert.deepEqual(state.choices, dirtyChoices, 'Cancelling leave must preserve the changed radio answer.');

    phase = 'clean native link and return';
    await revert();
    assertClean(await readState(), baseline, 'The page must be clean before the unguarded history link.');
    const beforeCleanDialogs = nativeDialogCount;
    let loaded = waitForPageLoad('clean practice history link');
    try { await Promise.all([evaluate(`(() => { document.querySelector('a.back-link').click(); return true; })()`), loaded]); }
    finally { loaded.cancel(); }
    assert.equal(await evaluate('location.href'), historyUrl, 'Clean native link must reach only the current learner practice history.');
    assert.equal(nativeDialogCount, beforeCleanDialogs, 'Clean navigation must not display a leave warning.');
    await navigateTo(attemptUrl, 'practice explicit GET return');
    assertClean(await readState(), baseline, 'Explicit GET return must reconstruct the saved clean state; this is not a BFCache test.');

    phase = 'native rejected stale POST';
    const beforePostDialogs = nativeDialogCount;
    postResponse = null;
    observePost = true;
    loaded = waitForPageLoad('practice stale revision response');
    try {
      const submitted = evaluate(`(() => {
        const form = ${formExpression}; const field = form.querySelectorAll('input[type="radio"][name^="answers["]')[${candidateIndex}];
        const save = form.querySelector('button[type="submit"][name="action"][value="save"]');
        if (!save || form.elements.revision.value === '999999999') return false;
        field.checked = true; field.dispatchEvent(new Event('change', { bubbles: true }));
        form.elements.revision.value = '999999999';
        if (!form.checkValidity()) return false;
        form.requestSubmit(save); return true;
      })()`);
      const [didSubmit] = await Promise.all([submitted, loaded]);
      assert.equal(didSubmit, true, 'Only the deliberately stale save POST may be submitted.');
    } finally { loaded.cancel(); observePost = false; }
    assert.equal(nativeDialogCount, beforePostDialogs, 'Native save submission must not trigger a leave warning.');
    assert.equal(dialogError, null);
    assert.equal(postResponse, 409, 'The native POST must actually return HTTP 409, not merely show matching text.');
    assertClean(await readState(), baseline, 'Rejected stale POST must restore authoritative choices as a clean state.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('[role="alert"]')).some(element => element.textContent.includes('Latihan telah diperbarui dari tab lain.') && element.textContent.includes('tidak menimpa simpanan terbaru'))`), true, 'Stale save must explain that the stored answers were not overwritten.');

    phase = 'JavaScript-disabled native fallback';
    await send('Emulation.setScriptExecutionDisabled', { value: true });
    scriptsDisabled = true;
    await navigateWithLoad(attemptUrl, 'practice no-JavaScript GET');
    const document = await send('DOM.getDocument');
    const formNode = await send('DOM.querySelector', { nodeId: document.root.nodeId, selector: formSelector });
    assert.ok(formNode.nodeId, 'Native practice form must remain present without page JavaScript.');
    const attributes = (await send('DOM.getAttributes', { nodeId: formNode.nodeId })).attributes;
    const pairs = new Map();
    for (let index = 0; index < attributes.length; index += 2) pairs.set(attributes[index], attributes[index + 1]);
    assert.equal(pairs.get('method')?.toLowerCase(), 'post');
    assert.equal(pairs.get('data-unsaved-initial'), 'false');
    assert.equal(pairs.has('data-unsaved-enhanced'), false, 'No-JS fallback must not claim script enhancement.');
    const hiddenStatus = await send('DOM.querySelector', { nodeId: formNode.nodeId, selector: '[data-unsaved-status][hidden]' });
    assert.ok(hiddenStatus.nodeId, 'No-JS unsaved status must stay hidden.');
    const csrf = await send('DOM.querySelector', { nodeId: formNode.nodeId, selector: 'input[type="hidden"][name="study_csrf_token"]' });
    const save = await send('DOM.querySelector', { nodeId: formNode.nodeId, selector: 'button[type="submit"][name="action"][value="save"]' });
    const finish = await send('DOM.querySelector', { nodeId: formNode.nodeId, selector: 'button[type="submit"][name="action"][value="finish"]' });
    assert.ok(csrf.nodeId && save.nodeId && finish.nodeId, 'No-JS fallback must retain CSRF and both native save/finalize controls; neither is submitted in this check.');
    const html = (await send('DOM.getOuterHTML', { nodeId: formNode.nodeId })).outerHTML;
    assert.ok(html.includes('Pilihan jawaban tidak tersimpan otomatis.') && html.includes('Gunakan Simpan sementara'), 'No-JS practice must retain the manual-save guidance.');
    await send('Emulation.setScriptExecutionDisabled', { value: false });
    scriptsDisabled = false;
    phase = 'final clean GET';
    await navigateTo(attemptUrl, 'practice unsaved final clean GET');
    assertClean(await readState(), baseline, 'Practice helper must return with the clean authoritative page restored.');
    assert.equal(dialogError, null);
    return { passed: true, width, nativeDirtyLeaveCancelled: true, nativeStalePostStatus: postResponse,
      javascriptDisabledFallback: true, screenshot, limits: 'One stale save POST; no successful save/finalization, BFCache, other browsers, or offline guarantee.' };
  } catch (error) {
    // Never include expressions, answer values, tokens, or rendered form HTML.
    throw new Error(`Practice unsaved check failed during ${phase}: ${error instanceof assert.AssertionError ? error.message : 'browser, navigation or artifact operation failed'}`);
  } finally {
    expectedDialog?.reject(new Error('Practice unsaved check ended.'));
    if (scriptsDisabled) await send('Emulation.setScriptExecutionDisabled', { value: false }).catch(() => {});
    await Promise.allSettled([...dialogTasks]);
    client.socket.removeEventListener('message', listener);
  }
}
