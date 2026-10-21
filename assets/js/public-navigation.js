(() => {
  'use strict';
  const menu = document.getElementById('navigasi-utama');
  const toggle = document.querySelector('.site-header .navbar-toggler');
  if (!menu || !toggle) return;
  const close = () => { menu.classList.remove('show'); toggle.setAttribute('aria-expanded', 'false'); };
  toggle.hidden = false;
  toggle.setAttribute('aria-expanded', 'false');
  toggle.addEventListener('click', () => {
    const open = menu.classList.toggle('show');
    toggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu.classList.contains('show')) { close(); toggle.focus(); }
  });
  menu.addEventListener('click', event => { if (event.target.closest('a')) close(); });
  window.matchMedia('(min-width:1200px)').addEventListener('change', close);
  document.addEventListener('click', event => {
    const dismiss = event.target.closest('[data-bs-dismiss="alert"]');
    if (dismiss) dismiss.closest('.alert')?.remove();
  });
})();
