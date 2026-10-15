import assert from 'node:assert/strict';
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

// Called only for an owned synthetic learner/lesson by progress-check.ps1.
export async function runReadingSaveChecks({ send, client, navigateTo, waitForPageLoad, baseUrl, route, width }) {
	assert(/^materi\/qa-progress-lesson-[a-f0-9]{16}$/.test(route.path), 'Reading browser exercise requires the exact progress fixture route.');
	const url = new URL(route.path, baseUrl).href;
	const endpoint = `${url}/posisi`;
	const evaluate = async expression => {
		const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
		assert(!r.exceptionDetails, 'Reading browser evaluation failed.'); return r.result.value;
	};
	const until = async expression => { for (let i = 0; i < 80; i++) { if (await evaluate(expression)) return; await delay(100); } throw new Error('Reading save browser state timeout.'); };
	const snapshot = () => evaluate(`({saved:Number(document.getElementById('reading-material').dataset.savedPosition),token:document.querySelector('#save-reading-position [name="study_csrf_token"]').value})`);
	const scroll = () => evaluate(`(() => { const saved=Number(document.getElementById('reading-material').dataset.savedPosition); const fraction=saved<5000?0.75:0.25; window.scrollTo(0,(document.documentElement.scrollHeight-innerHeight)*fraction); window.dispatchEvent(new Event('scroll')); return true; })()`);
	await navigateTo(url, 'reading save fixture');
	assert(await evaluate(`!!document.querySelector('#save-reading-position button:not([hidden])') && !!document.getElementById('complete-reading-lesson')`), 'Owned active lesson controls must exist.');
	const before = await snapshot();
	let intercepted = 0, interceptionError;
	const intercept = event => {
		const message = JSON.parse(event.data);
		if (message.method !== 'Fetch.requestPaused') return;
		const p = message.params;
		const match = p.request.url === endpoint && p.request.method === 'POST';
		if (match) intercepted++;
		const operation = match ? send('Fetch.fulfillRequest', { requestId:p.requestId, responseCode:503, responseHeaders:[{ name:'Content-Type', value:'application/json' }], body:Buffer.from(JSON.stringify({ ok:false, failure:'uncertain' })).toString('base64') }) : send('Fetch.continueRequest', { requestId:p.requestId });
		operation.catch(error => { interceptionError = error; });
	};
	client.socket.addEventListener('message', intercept);
	await send('Fetch.enable', { patterns:[{ urlPattern:endpoint, requestStage:'Request' }] });
	try {
		await scroll();
		await until(`!document.getElementById('reading-position-recovery').hidden && document.querySelector('#save-reading-position button').disabled && document.querySelector('#complete-reading-lesson button').disabled`);
		assert.equal(intercepted, 1, 'Exactly one synthetic 503, before server write.');
		assert.equal((await snapshot()).token, before.token, 'Failed response cannot replace token.');
		await evaluate(`window.scrollTo(0,0); window.dispatchEvent(new Event('scroll')); true`);
		await delay(1600);
		assert.equal(intercepted, 1, 'No retry after further scrolling.');
		assert(await evaluate(`['save-reading-position','complete-reading-lesson'].every(id=>!document.getElementById(id).dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})))`), 'Manual save and completion are also blocked.');
		if (interceptionError) throw interceptionError;
	} finally {
		await send('Fetch.disable'); client.socket.removeEventListener('message', intercept);
	}
	const loaded = waitForPageLoad('reading recovery GET');
	try { await Promise.all([loaded, evaluate(`document.getElementById('reading-position-recovery').click(); true`)]); }
	finally { loaded.cancel(); }
	assert.equal((await snapshot()).saved, before.saved, 'Intercepted request must not change stored position.');
	await scroll();
	await until(`document.getElementById('reading-position-status').textContent === 'Progres tersimpan otomatis.'`);
	assert(await evaluate(`(() => { const values=[...document.querySelectorAll('input[name="study_csrf_token"]')].map(x=>x.value); return values.length>=3 && values.every(x=>x===values[0] && x.length>0); })()`), 'All native forms must share the newly returned CSRF token.');
	await navigateTo(url, 'confirmed reading save reload');
	const after = await snapshot();
	assert.notEqual(after.saved, before.saved, 'Confirmed save must survive a real GET reload.');
	return { width, synthetic503:1, retries:0, lockedNativeForms:2, recoveryUnchanged:true, confirmedSavePersisted:true, allFormTokensAligned:true, limitation:'503 intercepted before server; not actual database failure or timeout. No completion is submitted here.' };
}
