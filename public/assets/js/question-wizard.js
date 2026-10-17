/**
 * Darul Fatwa — assistant « Poser une question » en 3 étapes (§4.2.1) : navigation, validation par étape,
 * onglets vocal/écrit, brouillon texte en localStorage, récapitulatif, numéro WhatsApp conditionnel.
 */
const form = document.querySelector('[data-question-wizard]');

if (form) {
  const labels = JSON.parse(form.dataset.labels || '{}');
  const panels = [...form.querySelectorAll('[data-wizard-panel]')];
  const indicators = [...document.querySelectorAll('[data-step-indicator]')];
  const bar = document.querySelector('[data-wizard-bar]');
  const progress = bar?.closest('.progress');
  const modeField = form.querySelector('[data-mode-field]');
  const body = form.querySelector('[data-body]');
  const mirror = form.querySelector('[data-body-mirror]');
  const title = form.querySelector('#title');
  const recorderRoot = form.querySelector('[data-voice-recorder]');
  const mediaField = form.querySelector('input[name="media_uuid"]');
  const fileInput = form.querySelector('[data-audio-file]');
  const draftKey = `df:question-draft:${location.pathname}`;
  let step = 1;
  let mode = form.dataset.mode === 'text' ? 'text' : 'voice';

  // ---- Brouillon texte (localStorage, try/catch) ----
  const saveDraft = () => {
    try {
      localStorage.setItem(draftKey, JSON.stringify({
        title: title?.value || '',
        body: body?.value || '',
        rubrique: form.querySelector('input[name="rubrique_id"]:checked')?.value || '',
        mode,
      }));
    } catch { /* stockage indisponible */ }
  };
  const loadDraft = () => {
    try {
      const raw = localStorage.getItem(draftKey);
      if (!raw) return;
      const draft = JSON.parse(raw);
      if (title && !title.value && draft.title) title.value = draft.title;
      if (body && !body.value && draft.body) body.value = draft.body;
      if (mirror && !mirror.value && draft.body) mirror.value = draft.body;
      if (draft.rubrique && !form.querySelector('input[name="rubrique_id"]:checked')) {
        const radio = form.querySelector(`input[name="rubrique_id"][value="${draft.rubrique}"]`);
        if (radio) radio.checked = true;
      }
      if (draft.mode && !new URLSearchParams(location.search).has('mode')) mode = draft.mode;
    } catch { /* ignoré */ }
  };
  const clearDraft = () => { try { localStorage.removeItem(draftKey); } catch { /* ignoré */ } };
  loadDraft();

  // Le champ texte de l'onglet vocal (précision) et celui de l'onglet écrit sont le même « body »
  if (mirror && body) {
    mirror.addEventListener('input', () => { body.value = mirror.value; saveDraft(); });
    body.addEventListener('input', () => { mirror.value = body.value; saveDraft(); });
  }
  title?.addEventListener('input', saveDraft);
  form.querySelectorAll('input[name="rubrique_id"]').forEach((r) => r.addEventListener('change', saveDraft));

  // ---- Onglets vocal / écrit ----
  const setMode = (next) => {
    mode = next;
    modeField.value = next;
    form.querySelectorAll('[data-mode-tab]').forEach((tab) => {
      const active = tab.dataset.modeTab === next;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
    });
    form.querySelectorAll('[data-mode-panel]').forEach((panel) => { panel.hidden = panel.dataset.modePanel !== next; });
    saveDraft();
  };
  form.querySelectorAll('[data-mode-tab]').forEach((tab) => tab.addEventListener('click', () => setMode(tab.dataset.modeTab)));
  setMode(mode);

  // ---- Navigation ----
  const show = (n) => {
    step = n;
    panels.forEach((panel) => { panel.hidden = Number(panel.dataset.wizardPanel) !== n; });
    indicators.forEach((el) => {
      const i = Number(el.dataset.stepIndicator);
      el.classList.toggle('is-active', i === n);
      el.classList.toggle('is-done', i < n);
      el.setAttribute('aria-current', i === n ? 'step' : 'false');
    });
    if (bar) bar.style.width = `${(n / 3) * 100}%`;
    progress?.setAttribute('aria-valuenow', String(n));
    progress?.setAttribute('aria-label', (labels.step || '').replace('{n}', String(n)));
    if (n === 3) updateSummary();
    panels[n - 1].querySelector('legend')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    panels[n - 1].querySelector('legend')?.setAttribute('tabindex', '-1');
    panels[n - 1].querySelector('legend')?.focus({ preventScroll: true });
  };

  const hasContent = () => {
    const text = (body?.value || '').trim().length > 0;
    const audio = !!(mediaField?.value) || !!(fileInput?.files?.length);
    return mode === 'text' ? text : (audio || text);
  };

  const validate = (n) => {
    if (n === 1) {
      const ok = !!form.querySelector('input[name="rubrique_id"]:checked');
      form.querySelector('.df-rubrique-choices')?.classList.toggle('is-invalid', !ok);
      return ok;
    }
    if (n === 2) {
      if (!hasContent()) {
        const recorder = recorderRoot?.recorder;
        if (mode === 'voice' && recorder && recorder.state === 'ready') {
          // Enregistrement prêt mais pas encore envoyé : on l'envoie
          recorder.upload();
        }
        alertStep2(labels.empty);
        return false;
      }
      return true;
    }
    return true;
  };

  const alertStep2 = (text) => {
    let box = form.querySelector('[data-step2-alert]');
    if (!box) {
      box = document.createElement('div');
      box.className = 'alert alert-danger mt-3';
      box.setAttribute('role', 'alert');
      box.dataset.step2Alert = '';
      panels[1].querySelector('.df-wizard__nav').before(box);
    }
    box.textContent = text;
  };

  form.querySelectorAll('[data-wizard-next]').forEach((btn) => btn.addEventListener('click', () => { if (validate(step)) show(step + 1); }));
  form.querySelectorAll('[data-wizard-prev]').forEach((btn) => btn.addEventListener('click', () => show(Math.max(1, step - 1))));

  // ---- WhatsApp conditionnel ----
  const whatsappWrapper = form.querySelector('[data-whatsapp-wrapper]');
  const updateChannel = () => {
    const value = form.querySelector('input[name="answer_channel"]:checked')?.value || 'platform';
    if (whatsappWrapper) whatsappWrapper.hidden = value === 'platform';
  };
  form.querySelectorAll('[data-channel]').forEach((r) => r.addEventListener('change', () => { updateChannel(); updateSummary(); }));
  updateChannel();

  // ---- Récapitulatif ----
  const formatTime = (s) => `${Math.floor(s / 60)}:${String(Math.round(s) % 60).padStart(2, '0')}`;
  let lastDuration = 0;
  recorderRoot?.addEventListener('recorder:uploaded', (e) => { lastDuration = e.detail.duration || 0; form.querySelector('[data-step2-alert]')?.remove(); updateSummary(); });
  recorderRoot?.addEventListener('recorder:deleted', updateSummary);
  fileInput?.addEventListener('change', () => {
    if (fileInput.files.length && recorderRoot?.recorder && recorderRoot.recorder.state === 'idle') {
      recorderRoot.recorder.setMessage((labels.chosen || '').replace('{name}', fileInput.files[0].name));
    }
    updateSummary();
  });

  function updateSummary() {
    const rubrique = form.querySelector('input[name="rubrique_id"]:checked');
    const text = (body?.value || '').trim().length > 0;
    const audio = !!(mediaField?.value) || !!(fileInput?.files?.length);
    let type = labels.none;
    if (audio && text) type = labels.mixed;
    else if (audio) type = (labels.voice || '').replace('{d}', lastDuration ? formatTime(lastDuration) : '…');
    else if (text) type = labels.text;
    form.querySelector('[data-summary-rubrique]').textContent = rubrique?.dataset.rubriqueName || '—';
    form.querySelector('[data-summary-type]').textContent = type;
    const lang = form.querySelector('#answer_language');
    form.querySelector('[data-summary-answer]').textContent = lang?.options[lang.selectedIndex]?.text || '—';
    const channel = form.querySelector('input[name="answer_channel"]:checked');
    form.querySelector('[data-summary-channel]').textContent = channel ? channel.nextElementSibling.querySelector('strong').textContent : '—';
  }
  form.querySelector('#answer_language')?.addEventListener('change', updateSummary);

  // ---- Envoi ----
  form.addEventListener('submit', (event) => {
    if (!validate(1)) { event.preventDefault(); show(1); return; }
    if (!validate(2)) { event.preventDefault(); show(2); return; }
    const recorder = recorderRoot?.recorder;
    if (mode === 'voice' && recorder && recorder.state === 'uploading') {
      event.preventDefault();
      recorderRoot.addEventListener('recorder:uploaded', () => form.requestSubmit(), { once: true });
      return;
    }
    const submit = form.querySelector('[data-wizard-submit]');
    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    clearDraft();
  });

  // Erreurs serveur : revenir à l'étape concernée
  if (form.querySelector('.is-invalid, .invalid-feedback')) {
    const invalid = form.querySelector('.is-invalid, .invalid-feedback');
    const panel = invalid.closest('[data-wizard-panel]');
    show(panel ? Number(panel.dataset.wizardPanel) : 1);
  } else {
    show(form.querySelector('input[name="rubrique_id"]:checked') && new URLSearchParams(location.search).has('mode') ? 2 : 1);
  }
}
