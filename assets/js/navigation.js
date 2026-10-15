(() => {
  'use strict';
  const menu = document.getElementById('navigasi-utama');
  const toggle = document.querySelector('.site-header .navbar-toggler[aria-controls="navigasi-utama"]');
  if (!menu || !toggle || menu.dataset.navigationEnhanced === 'true' || typeof window.bootstrap?.Collapse !== 'function') return;

  // Keep the server-rendered menu visible until its collapse dependency exists.
  menu.classList.add('collapse');
  menu.dataset.navigationEnhanced = 'true';
  toggle.hidden = false;
})();
