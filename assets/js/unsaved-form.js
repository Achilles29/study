(() => {
  'use strict';

  const forms = Array.from(document.querySelectorAll('form[data-unsaved-form]'));
  const states = [];
  let listening = false;

  // Values stay in this page's memory only. Hidden security fields are not edits.
  function snapshot(form, defaults = false) {
    return JSON.stringify(Array.from(form.elements).filter((field) => {
      return field.name && !field.matches(':disabled') &&
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(field.tagName) &&
        !['hidden', 'submit', 'button', 'reset', 'image'].includes(field.type);
    }).map((field) => {
      if (field.type === 'checkbox' || field.type === 'radio') {
        return [field.name, field.type, field.value, defaults ? field.defaultChecked : field.checked];
      }
      if (field.tagName === 'SELECT') {
        const options = Array.from(field.options);
        let selected = options.filter((option) => defaults ? option.defaultSelected : option.selected);
        if (defaults && !field.multiple) {
          if (selected.length) selected = [selected[selected.length - 1]];
          else if (field.size <= 1) {
            const first = options.find((option) => !option.matches(':disabled'));
            selected = first ? [first] : [];
          }
        }
        return [field.name, selected.map((option) => option.value)];
      }
      return [field.name, field.type, defaults ? field.defaultValue : field.value];
    }));
  }

  function beforeUnload(event) {
    let warn = false;
    states.forEach((state) => {
      // Read the event here too: a later submit listener may have cancelled it.
      const submitting = state.pendingSubmit && !state.pendingSubmit.defaultPrevented;
      state.pendingSubmit = null;
      if (state.dirty && !submitting) warn = true;
    });
    if (!warn) return;
    event.preventDefault();
    event.returnValue = true;
  }

  function syncListener() {
    const needed = states.some((state) => state.dirty);
    if (needed === listening) return;
    window[needed ? 'addEventListener' : 'removeEventListener']('beforeunload', beforeUnload);
    listening = needed;
  }

  function refresh(state) {
    state.pendingSubmit = null;
    state.dirty = state.initialDirty || snapshot(state.form) !== state.initial;
    const text = state.dirty ? 'Ada perubahan yang belum disimpan.' : 'Belum ada perubahan baru.';
    if (state.status.textContent !== text) state.status.textContent = text;
    syncListener();
  }

  forms.forEach((form) => {
    const status = form.querySelector('[data-unsaved-status]');
    if (!status || form.dataset.unsavedEnhanced === 'true') return;
    const state = {
      // Defaults reflect server markup even if history/autofill restored values
      // before this script ran. Restored user values are not a saved baseline.
      form, status, initial: snapshot(form, true), initialDirty: form.dataset.unsavedInitial === 'true',
      dirty: false, pendingSubmit: null
    };
    states.push(state);
    form.dataset.unsavedEnhanced = 'true';
    status.hidden = false;
    form.addEventListener('input', () => refresh(state));
    form.addEventListener('change', () => refresh(state));
    // A reset event precedes native value restoration.
    form.addEventListener('reset', () => {
      state.pendingSubmit = null;
      window.setTimeout(() => refresh(state), 0);
    });
    form.addEventListener('submit', (event) => {
      // Native form navigation is queued; a timer could run before beforeunload.
      // Keep the event until that check, and never infer that saving succeeded.
      state.pendingSubmit = event;
    });
    refresh(state);
  });

  if (states.length) {
    window.addEventListener('pageshow', () => {
      states.forEach(refresh);
      // Some history restoration happens after pageshow without input/change.
      // Do not cancel a new native submission started in the meantime.
      window.setTimeout(() => states.forEach((state) => {
        if (!state.pendingSubmit) refresh(state);
      }), 0);
    });
    // Any subsequent user action ends an earlier navigation intent. Capture runs
    // before a new submit, which can then register its own event. AJAX/targeted
    // forms must not opt in without defining and testing their own lifecycle.
    const rearm = () => states.forEach((state) => { state.pendingSubmit = null; });
    window.addEventListener('pointerdown', rearm, true);
    window.addEventListener('keydown', rearm, true);
  }
})();
