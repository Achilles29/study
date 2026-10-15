export function enhanceMatchingForm(form, status, recovery, environment = window) {
	if (!form || !status || !recovery || !environment.fetch || !environment.FormData || !environment.AbortController) return false;
	const target = new URL(form.action, environment.location.href);
	if (target.origin !== environment.location.origin) return false;
	const refresh = () => {
		// A same-document fragment navigation does not fetch fresh server state.
		environment.history.replaceState(null, '', target.href);
		environment.location.reload();
	};
	recovery.addEventListener('click', event => { event.preventDefault(); refresh(); });
	environment.addEventListener('pageshow', event => {
		// Restored DOM can contain a disabled form and a revision from before navigation.
		if (event.persisted) refresh();
	});
	let busy = false;
	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (busy || !form.reportValidity()) return;
		// Capture successful controls before disabling the fieldsets.
		const body = new environment.FormData(form);
		busy = true;
		for (const fieldset of form.querySelectorAll('fieldset')) fieldset.disabled = true;
		const button = form.querySelector('button[type="submit"]');
		button.disabled = true;
		form.setAttribute('aria-busy', 'true');
		status.textContent = 'Mengirim pilihan… Tunggu sampai progres terbaru tampil.';
		const controller = new environment.AbortController();
		const timeout = environment.setTimeout(() => controller.abort(), 15000);
		try {
			const response = await environment.fetch(target.href, { method: 'POST', body, credentials: 'same-origin', redirect: 'manual', signal: controller.signal });
			if (response.type === 'opaqueredirect') {
				// Navigate with a fresh GET; do not consume the server flash message in a background redirect.
				refresh();
				return;
			}
			if (response.status === 409) status.textContent = 'Progres permainan sudah berubah. Buka progres terbaru sebelum memilih kembali.';
			else if (response.status === 403) {
				status.textContent = 'Akses atau formulir sudah berubah. Buka progres terbaru untuk memeriksa akses sebelum melanjutkan.';
				form.querySelector('.matching-columns').hidden = true;
			}
			else status.textContent = 'Belum dapat memastikan pilihan tersimpan. Jangan kirim ulang dulu; buka progres terbaru untuk memeriksanya.';
		} catch {
			status.textContent = 'Koneksi terganggu atau respons terlalu lama. Pilihan mungkin sudah tersimpan. Setelah terhubung, buka progres terbaru sebelum memilih lagi.';
		} finally {
			environment.clearTimeout(timeout);
			form.setAttribute('aria-busy', 'false');
		}
		// Keep this revision locked. Only a server GET can give a fresh state, action key and CSRF token.
		recovery.hidden = false;
		recovery.focus();
	});
	return true;
}

if (typeof document !== 'undefined') {
	enhanceMatchingForm(document.getElementById('saved-matching-form'), document.getElementById('matching-save-state'), document.getElementById('matching-save-recovery'));
}
