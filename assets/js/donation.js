(() => {
  'use strict';
  const input = document.querySelector('#donation-amount');
  const presets = document.querySelector('[data-donation-presets]');
  if (!input || !presets) return;
  presets.hidden = false;
  presets.querySelectorAll('button[data-donation-amount]').forEach(button => {
    button.addEventListener('click', () => { input.value = button.dataset.donationAmount; input.focus(); });
  });
})();
