const ALLOWED = ['ml', 'bf', 'ci'];
function initPhoneInputs() {
  if (typeof window.intlTelInput !== 'function') {
    return;
  }
  document.querySelectorAll('input[data-phone]').forEach((input) => {
    const hiddenName = input.dataset.phone;
    const iti = window.intlTelInput(input, {
      initialCountry: ALLOWED.includes(input.dataset.country || '') ? input.dataset.country : 'ml',
      onlyCountries: ALLOWED,
      countryOrder: ALLOWED,
      separateDialCode: true,
      strictMode: true,
      nationalMode: false,
      formatOnDisplay: true,
      hiddenInput: () => ({ phone: hiddenName }),
      i18n: window.DF_ITI_I18N || {},
    });
    const sync = () => {
      const hidden = input.form?.querySelector(`input[name="${hiddenName}"]`);
      if (hidden) {
        hidden.value = input.value.trim() === '' ? '' : iti.getNumber();
      }
    };
    input.addEventListener('input', sync);
    input.addEventListener('countrychange', sync);
    input.form?.addEventListener('submit', sync);
    sync();
  });
}
function initPasswordToggles() {
  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = document.getElementById(button.dataset.togglePassword);
      if (!field) {
        return;
      }
      const show = field.type === 'password';
      field.type = show ? 'text' : 'password';
      button.setAttribute('aria-label', show ? button.dataset.labelHide : button.dataset.labelShow);
      button.querySelector('i')?.classList.toggle('bi-eye', !show);
      button.querySelector('i')?.classList.toggle('bi-eye-slash', show);
    });
  });
}
function passwordScore(value) {
  if (value.length < 8) {
    return value.length === 0 ? -1 : 0;
  }
  let score = 1;
  if (value.length >= 12) score++;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
  if (/\d/.test(value)) score++;
  if (/[^A-Za-z0-9]/.test(value)) score++;
  return Math.min(4, score);
}
function initStrengthMeters() {
  document.querySelectorAll('input[data-strength]').forEach((input) => {
    const meter = document.getElementById(input.dataset.strength);
    if (!meter) {
      return;
    }
    const labels = JSON.parse(meter.dataset.labels || '[]');
    const bar = meter.querySelector('.progress-bar');
    const progress = meter.querySelector('.progress');
    const label = meter.querySelector('.df-strength__label');
    const classes = ['bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
    input.addEventListener('input', () => {
      const score = passwordScore(input.value);
      meter.hidden = score < 0;
      if (score < 0) {
        return;
      }
      const level = Math.max(1, score);
      bar.style.width = `${level * 25}%`;
      bar.className = `progress-bar ${classes[level - 1]}`;
      progress.setAttribute('aria-valuenow', String(level));
      label.textContent = labels[level - 1] || '';
    });
  });
}
function initConditionalBlocks() {
  document.querySelectorAll('[data-toggle-hidden]').forEach((box) => {
    const target = document.getElementById(box.dataset.toggleHidden);
    if (!target) {
      return;
    }
    const inverse = box.hasAttribute('data-toggle-inverse');
    const apply = () => {
      target.hidden = inverse ? box.checked : !box.checked;
    };
    box.addEventListener('change', apply);
    apply();
  });
}
function boot() {
  initPhoneInputs();
  initPasswordToggles();
  initStrengthMeters();
  initConditionalBlocks();
}
if (document.readyState === 'complete') {
  boot();
} else {
  window.addEventListener('load', boot, { once: true });
}
