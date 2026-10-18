(() => {
  'use strict';
  document.querySelectorAll('input[type="password"]').forEach(input => {
    if (!input.id || input.dataset.visibilityReady || !input.classList.contains('form-control')) return;
    input.dataset.visibilityReady = '1';
    const wrapper = document.createElement('div');
    wrapper.className = 'password-control';
    input.before(wrapper);
    wrapper.append(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id);
    button.setAttribute('aria-pressed', 'false');
    const label = Array.from(document.querySelectorAll('label')).find(el => el.htmlFor === input.id)?.textContent.trim() || 'sandi';
    button.setAttribute('aria-label', 'Tampilkan ' + label);
    const icon = document.createElement('i');
    icon.className = 'bi bi-eye';
    icon.setAttribute('aria-hidden', 'true');
    button.append(icon);
    wrapper.append(button);
    button.addEventListener('click', () => {
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', (visible ? 'Sembunyikan ' : 'Tampilkan ') + label);
      icon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
    // Return to the private state when the page is restored or a form is submitted.
    const hide = () => { input.type = 'password'; button.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-label', 'Tampilkan ' + label); icon.className = 'bi bi-eye'; };
    input.form?.addEventListener('submit', hide);
    window.addEventListener('pagehide', hide);
  });
})();
