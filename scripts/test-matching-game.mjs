import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../assets/js/matching-game.js', import.meta.url), 'utf8');
const { makeRounds, createRound, shuffled } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
for (const size of [4, 6]) {
	for (let length = 2; length <= 100; length++) {
		const cards = Array.from({ length }, (_, id) => ({ front: `F${id}`, back: `B${id}` }));
		const rounds = makeRounds(cards, size, () => .25);
		assert.equal(rounds.flat().length, length);
		assert.equal(new Set(rounds.flat().map((card) => card.id)).size, length);
		assert.ok(rounds.every((round) => round.length >= 2 && round.length <= size));
		assert.equal(cards[0].front, 'F0');
	}
}
assert.throws(() => makeRounds([], 6));
assert.throws(() => makeRounds([{}, {}], 0));
assert.throws(() => makeRounds(Array(101).fill({}), 6));
assert.deepEqual(shuffled([1, 2], () => 0), [2, 1]);
const cards = [{ id: 0 }, { id: 1 }];
const round = createRound(cards);
assert.equal(round.choose('left', 99), 'ignored');
assert.equal(round.choose('bad', 0), 'ignored');
assert.equal(round.choose('left', 0), 'selected');
assert.equal(round.choose('left', 0), 'selected');
assert.equal(round.state().selected.left, null);
round.choose('left', 0);
assert.equal(round.choose('right', 1), 'mismatch');
assert.deepEqual(round.state().matched, []);
assert.equal(round.state().selected.left, null);
round.choose('right', 0);
assert.equal(round.choose('left', 0), 'matched');
assert.equal(round.choose('left', 0), 'ignored');
assert.deepEqual(round.state().matched, [0]);
round.choose('left', 1);
round.choose('right', 1);
assert.equal(round.state().complete, true);
const snapshot = round.state(); snapshot.matched.length = 0;
assert.equal(round.state().matched.length, 2);
assert.equal(createRound(cards).state().complete, false);
assert.doesNotMatch(source, /fetch\s*\(|XMLHttpRequest|localStorage|sessionStorage|innerHTML\s*=/);
console.log('PASS matching game: 2–100 cards in 4/6-pair rounds, no singleton/loss, selection/mismatch/match/replay/reset, and no network/storage/HTML writes.');
