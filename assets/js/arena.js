'use strict';
// Countdown is advisory only. PHP validates the deadline on every action.
const timer = document.querySelector('[data-arena-deadline]');
if (timer) {
	const remaining = Number(timer.dataset.arenaDeadline) - Number(timer.dataset.serverNow);
	const started = performance.now();
	const update = () => {
		const seconds = Math.max(0, remaining - Math.floor((performance.now() - started) / 1000));
		timer.querySelector('[data-arena-clock]').textContent = seconds > 0 ? `Sisa sekitar ${seconds} detik.` : 'Waktu habis. Kirim formulir untuk menyimpan hasil akhir.';
	};
	update(); setInterval(update, 1000);
}
