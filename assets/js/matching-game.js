// Practice only: no network, storage, rewards, or authoritative assessment.
export function shuffled(items, random = Math.random) {
	const copy = [...items];
	for (let i = copy.length - 1; i > 0; i--) {
		const j = Math.floor(random() * (i + 1));
		[copy[i], copy[j]] = [copy[j], copy[i]];
	}
	return copy;
}

export function makeRounds(cards, size, random = Math.random) {
	if (![4, 6].includes(size) || !Array.isArray(cards) || cards.length < 2 || cards.length > 100) throw new Error('Invalid game cards or round size.');
	const queue = shuffled(cards.map((card, id) => ({ ...card, id })), random);
	const rounds = [];
	while (queue.length) {
		const count = queue.length === size + 1 ? size - 1 : Math.min(size, queue.length);
		rounds.push(queue.splice(0, count));
	}
	return rounds;
}

export function createRound(cards) {
	const valid = new Set(cards.map((card) => card.id));
	const matched = new Set();
	let selected = { left: null, right: null };
	return {
		choose(side, id) {
			if (!['left', 'right'].includes(side) || !valid.has(id) || matched.has(id)) return 'ignored';
			selected[side] = selected[side] === id ? null : id;
			if (selected.left === null || selected.right === null) return 'selected';
			const correct = selected.left === selected.right;
			if (correct) matched.add(id);
			selected = { left: null, right: null };
			return correct ? 'matched' : 'mismatch';
		},
		state() { return { selected: { ...selected }, matched: [...matched], complete: matched.size === valid.size }; }
	};
}

export function mountGame(root, cards, documentRef = document) {
	const size = Number(root.dataset.roundSize);
	let rounds;
	let index;
	let round;
	let buttons;
	const heading = documentRef.getElementById('matching-heading');
	const progress = documentRef.getElementById('matching-progress');
	const count = documentRef.getElementById('matching-count');
	const message = documentRef.getElementById('matching-message');
	const next = documentRef.getElementById('matching-next');
	const restart = documentRef.getElementById('matching-restart');
	function sync() {
		const state = round.state();
		for (const button of buttons) {
			const matched = state.matched.includes(Number(button.dataset.cardId));
			button.disabled = matched;
			button.classList.toggle('is-matched', matched);
			button.setAttribute('aria-pressed', String(state.selected[button.dataset.side] === Number(button.dataset.cardId)));
			button.textContent = (matched ? '✓ Cocok: ' : '') + button.dataset.label;
		}
		progress.max = rounds[index].length;
		progress.value = state.matched.length;
		count.textContent = `${state.matched.length} dari ${rounds[index].length} pasangan ditemukan`;
		next.hidden = !state.complete || index === rounds.length - 1;
	}
	function play(side, id) {
		const result = round.choose(side, id);
		if (result === 'ignored') return;
		sync();
		const state = round.state();
		message.textContent = result === 'mismatch' ? 'Belum cocok. Coba lagi; kamu juga boleh membaca daftar pasangan di bawah.' : result === 'matched' ? 'Cocok! Lanjutkan pasangan berikutnya.' : 'Pilih satu kartu dan satu pasangan. Klik pilihan yang sama untuk membatalkan.';
		if (state.complete) {
			message.textContent = index === rounds.length - 1 ? 'Semua babak selesai! Terima kasih sudah berlatih. Kamu bisa mengulang atau kembali ke materi.' : 'Babak selesai! Siap mencoba pasangan berikutnya?';
			(next.hidden ? restart : next).focus();
		} else if (result === 'matched') {
			buttons.find((button) => button.dataset.side === 'left' && !button.disabled)?.focus();
		}
	}
	function render() {
		round = createRound(rounds[index]);
		buttons = [];
		heading.textContent = `Babak ${index + 1} dari ${rounds.length}`;
		message.textContent = 'Pilih satu kartu dari kiri dan pasangannya di kanan.';
		for (const side of ['left', 'right']) {
			const container = documentRef.getElementById(`matching-${side}`);
			container.replaceChildren();
			for (const card of shuffled(rounds[index])) {
				const button = documentRef.createElement('button');
				button.type = 'button';
				button.className = 'matching-choice';
				button.dataset.cardId = String(card.id);
				button.dataset.side = side;
				button.dataset.label = side === 'left' ? card.front : card.back;
				button.setAttribute('aria-describedby', side === 'left' ? 'matching-left-label' : 'matching-right-label');
				button.addEventListener('click', () => play(side, card.id));
				container.append(button);
				buttons.push(button);
			}
		}
		sync();
	}
	function reset() { rounds = makeRounds(cards, size); index = 0; render(); }
	reset();
	next.addEventListener('click', () => { if (!round.state().complete || index >= rounds.length - 1) return; index++; render(); heading.focus(); });
	restart.addEventListener('click', () => { reset(); heading.focus(); });
	root.hidden = false;
}

if (typeof document !== 'undefined') {
	const root = document.getElementById('matching-game');
	if (root) {
		try { mountGame(root, JSON.parse(document.getElementById('matching-data').textContent)); }
		catch { root.hidden = true; /* The readable pairs remain available if enhancement fails. */ }
	}
}
