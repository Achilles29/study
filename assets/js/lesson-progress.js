(function () {
	'use strict';

	var readingMaterial = document.getElementById('reading-material');
	var saveForm = document.getElementById('save-reading-position');
	if (!readingMaterial || !saveForm) {
		return;
	}

	function maxScroll() {
		return Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
	}

	function scrollToPosition(position) {
		var distance = maxScroll();
		var top = distance * Math.min(10000, Math.max(0, position)) / 10000;
		var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		window.scrollTo({ top: top, behavior: reducedMotion ? 'auto' : 'smooth' });
	}

	if (new URLSearchParams(window.location.search).get('lanjut') === '1') {
		scrollToPosition(parseInt(readingMaterial.getAttribute('data-saved-position') || '0', 10));
	}

	var saveButton = saveForm.querySelector('button[type="submit"]');
	if (saveButton) {
		saveButton.hidden = false;
	}
	var status = document.getElementById('reading-position-status');
	var csrfInput = saveForm.querySelector('input[name="study_csrf_token"]');
	var lastSaved = parseInt(readingMaterial.getAttribute('data-saved-position') || '0', 10);
	var timer = null;
	var inFlight = false;
	var queuedPosition = null;
	var halted = false;
	var recoveryLink = document.getElementById('reading-position-recovery');
	var completeForm = document.getElementById('complete-reading-lesson');

	function haltSaving(message) {
		halted = true;
		queuedPosition = null;
		if (timer) { window.clearTimeout(timer); }
		if (saveButton) { saveButton.disabled = true; }
		if (completeForm) { completeForm.querySelector('button[type="submit"]').disabled = true; }
		if (recoveryLink) { recoveryLink.hidden = false; }
		setStatus(message || 'Status simpan belum dapat dipastikan. Pilih Periksa keadaan terbaru sebelum menyimpan lagi.');
	}

	function currentPosition() {
		var distance = maxScroll();
		return distance > 0 ? Math.min(10000, Math.max(0, Math.round((window.scrollY / distance) * 10000))) : 0;
	}

	function setStatus(message) {
		if (status) {
			status.textContent = message;
		}
	}

	function sendPosition(position) {
		if (halted) { return; }
		if (!window.fetch || !window.URLSearchParams || !csrfInput || !csrfInput.value) {
			return;
		}
		if (inFlight) {
			queuedPosition = position;
			return;
		}
		inFlight = true;
		var aborter = window.AbortController ? new window.AbortController() : null;
		var watchdog = window.setTimeout(function () {
			haltSaving();
			if (aborter) { aborter.abort(); }
		}, 20000);
		var body = new URLSearchParams();
		body.set('position', String(position));
		body.set(csrfInput.name, csrfInput.value);
		setStatus('Menyimpan progres…');
		window.fetch(saveForm.action, {
			method: 'POST',
			credentials: 'same-origin',
			signal: aborter ? aborter.signal : undefined,
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			if (halted) { return; }
			if (!response.ok) { throw new Error('Unconfirmed reading save.'); }
			return response.json().then(function (data) {
				if (halted) { return; }
				if (!data || data.ok !== true || typeof data.csrf_hash !== 'string' || !data.csrf_hash) { throw new Error('Unconfirmed reading save.'); }
				document.querySelectorAll('input[name="study_csrf_token"]').forEach(function (input) { input.value = data.csrf_hash; });
				lastSaved = position;
				setStatus('Progres tersimpan otomatis.');
			});
		}).catch(function () {
			haltSaving();
		}).then(function () {
			window.clearTimeout(watchdog);
			inFlight = false;
			if (!halted && queuedPosition !== null) {
				var nextPosition = queuedPosition;
				queuedPosition = null;
				if (Math.abs(nextPosition - lastSaved) >= 25) {
					sendPosition(nextPosition);
				}
			}
		});
	}

	if (window.fetch && csrfInput && csrfInput.value && window.addEventListener) {
		window.addEventListener('pageshow', function (event) {
			if (event.persisted) { window.location.reload(); }
		});
		window.addEventListener('scroll', function () {
			if (halted) { return; }
			if (timer) {
				window.clearTimeout(timer);
			}
			timer = window.setTimeout(function () {
				var position = currentPosition();
				if (Math.abs(position - lastSaved) >= 25) {
					sendPosition(position);
				}
			}, 1200);
		}, { passive: true });
	}

	function preventUnsafeSubmit(event) {
		if (halted || inFlight) {
			event.preventDefault();
			if (!halted) { setStatus('Tunggu penyimpanan posisi baca selesai sebelum mengirim lagi.'); }
			return true;
		}
		if (timer) { window.clearTimeout(timer); }
		halted = true;
		return false;
	}
	if (completeForm) { completeForm.addEventListener('submit', preventUnsafeSubmit); }
	saveForm.addEventListener('submit', function (event) {
		if (preventUnsafeSubmit(event)) { return; }
		saveForm.querySelector('input[name="position"]').value = String(currentPosition());
	});
}());
