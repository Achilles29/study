'use strict';
(() => {
  const fragment = new URLSearchParams(location.hash.slice(1));
  const token = fragment.get('token');
  if (location.hash) history.replaceState(null, '', location.pathname);
  const input = document.querySelector('[data-account-token]');
  if (input && token && /^[a-f0-9]{64}$/.test(token)) input.value = token;
})();
