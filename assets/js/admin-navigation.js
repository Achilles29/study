(() => {
 'use strict';
 const sidebar = document.getElementById('admin-sidebar');
 const toggle = document.querySelector('.admin-menu-toggle');
 if (!sidebar || !toggle || document.body.classList.contains('admin-nav-ready')) return;
 const close = sidebar.querySelector('.admin-drawer-close');
 const backdrop = document.querySelector('.admin-drawer-backdrop');
 const workspace = document.querySelector('.admin-workspace');
 const mobile = matchMedia('(max-width: 991.98px)');
 let collapsed = false;
 const shut = (restore = true) => {
  document.body.classList.remove('admin-drawer-open');
  backdrop.hidden = true;
  workspace.inert = false;
  sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal');
  toggle.setAttribute('aria-expanded', String(!mobile.matches && !collapsed));
  if (restore) toggle.focus();
 };
 const sync = () => {
  shut(false); close.hidden = !mobile.matches;
  document.body.classList.toggle('admin-menu-collapsed', !mobile.matches && collapsed);
 };
 toggle.addEventListener('click', () => {
  if (!mobile.matches) { collapsed = !collapsed; sync(); return; }
  document.body.classList.add('admin-drawer-open'); backdrop.hidden = false;
  sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true');
  toggle.setAttribute('aria-expanded', 'true'); close.focus(); workspace.inert = true;
 });
 close.addEventListener('click', () => shut());
 backdrop.addEventListener('click', () => shut());
 document.addEventListener('keydown', event => {
  if (!document.body.classList.contains('admin-drawer-open')) return;
  if (event.key === 'Escape') { event.preventDefault(); shut(); }
  if (event.key === 'Tab') {
   const focusable = [...sidebar.querySelectorAll('a[href], button:not([hidden])')];
   const first = focusable[0], last = focusable.at(-1);
   if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
   else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }
 });
 mobile.addEventListener('change', sync);
 document.body.classList.add('admin-nav-ready'); toggle.hidden = false; sync();
})();
