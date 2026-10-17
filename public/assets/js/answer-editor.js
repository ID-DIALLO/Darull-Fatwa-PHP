/**
 * Darul Fatwa — formulaire de réponse du professeur (§4.2.4) : éditeur riche Quill (gras, italique, listes,
 * citation/bloc verset, lien, direction) synchronisé dans un <textarea>, et références structurées
 * (Coran, Hadith, ouvrage) sérialisées en JSON.
 */
function initEditor(form) {
  const container = form.querySelector('[data-editor]');
  const target = form.querySelector('[data-editor-target]');
  if (!container || !target || typeof window.Quill !== 'function') {
    return;
  }
  target.hidden = true;
  const quill = new window.Quill(container, {
    theme: 'snow',
    placeholder: target.getAttribute('placeholder') || '',
    modules: {
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'link'],
        [{ direction: 'rtl' }, { align: [] }],
        ['clean'],
      ],
    },
  });
  if (target.value.trim() !== '') {
    quill.clipboard.dangerouslyPasteHTML(target.value);
  }
  const sync = () => {
    const html = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
    target.value = quill.getText().trim() === '' && !html.includes('<img') ? '' : html;
  };
  quill.on('text-change', sync);
  form.addEventListener('submit', sync);
}

function initReferences(form) {
  const list = form.querySelector('[data-references-list]');
  const json = form.querySelector('[data-references-json]');
  if (!list || !json) {
    return;
  }
  const serialize = () => {
    const refs = [];
    list.querySelectorAll('[data-reference-row]').forEach((row) => {
      const ref = { type: row.dataset.type };
      row.querySelectorAll('[data-field]').forEach((input) => { ref[input.dataset.field] = input.value; });
      refs.push(ref);
    });
    json.value = JSON.stringify(refs);
  };
  const add = (type, values = {}) => {
    const template = form.querySelector(`[data-reference-template="${type}"]`);
    if (!template) return;
    const row = template.content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[data-field]').forEach((input) => {
      if (values[input.dataset.field] !== undefined) input.value = values[input.dataset.field];
      input.addEventListener('input', serialize);
      input.addEventListener('change', serialize);
    });
    const collection = row.querySelector('[data-field="collection"]');
    const other = row.querySelector('[data-other-collection]');
    if (collection && other) {
      const toggle = () => { other.hidden = collection.value !== 'other'; };
      collection.addEventListener('change', toggle);
      toggle();
    }
    row.querySelector('[data-remove-reference]').addEventListener('click', () => { row.remove(); serialize(); });
    list.appendChild(row);
    row.querySelector('select, input')?.focus();
    serialize();
  };
  form.querySelectorAll('[data-add-reference]').forEach((btn) => btn.addEventListener('click', () => add(btn.dataset.addReference)));
  try {
    const existing = JSON.parse(json.value || '[]');
    existing.forEach((ref) => add(ref.type, ref));
  } catch { /* valeur vide */ }
  form.addEventListener('submit', serialize);
}

function boot() {
  document.querySelectorAll('[data-answer-form]').forEach((form) => {
    initEditor(form);
    initReferences(form);
  });
}

if (document.readyState === 'complete') {
  boot();
} else {
  window.addEventListener('load', boot, { once: true });
}
