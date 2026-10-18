'use strict';
(() => {
	const button = document.querySelector('[data-english-listen]');
	const text = document.querySelector('[data-english-speech]');
	const status = document.querySelector('[data-english-audio-status]');
	if (!button || !text || !status) return;
	const synth = window.speechSynthesis;
	if (!synth || !window.SpeechSynthesisUtterance) return;
	let voice = null;
	const refresh = () => {
		voice = synth.getVoices().find(item => item.localService === true && /^en(?:-|$)/i.test(item.lang));
		button.hidden = !voice;
		status.textContent = voice ? 'Suara lokal perangkat, hanya setelah tombol ditekan. Tidak merekam suara dan tidak menilai pengucapan.' : 'Suara Inggris lokal belum tersedia pada perangkat ini. Pelajari teks dan artinya; audio bukan syarat menyelesaikan pelajaran.';
	};
	refresh(); synth.addEventListener('voiceschanged', refresh);
	button.addEventListener('click', () => {
		refresh(); if (!voice) return;
		if (synth.speaking) { synth.cancel(); button.textContent = 'Dengarkan contoh'; return; }
		const utterance = new SpeechSynthesisUtterance(text.textContent.trim());
		utterance.voice = voice; utterance.lang = voice.lang; utterance.rate = 0.85;
		utterance.onend = () => { button.textContent = 'Dengarkan contoh'; };
		utterance.onerror = () => { button.textContent = 'Dengarkan contoh'; status.textContent = 'Audio belum dapat diputar. Teks tetap tersedia untuk dipelajari.'; };
		button.textContent = 'Hentikan suara'; synth.speak(utterance);
	});
	window.addEventListener('pagehide', () => synth.cancel());
})();
