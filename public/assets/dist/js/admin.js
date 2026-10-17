import { csrfToken } from './app.js';
document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});
(() => {
  const table = document.querySelector('[data-bulk-table]');
  const bar = document.querySelector('[data-bulk-bar]');
  if (!table || !bar) return;
  const all = table.querySelector('[data-bulk-all]');
  const boxes = () => Array.from(table.querySelectorAll('[data-bulk-item]'));
  const counter = bar.querySelector('[data-bulk-count]');
  const form = bar.closest('form') || bar.querySelector('form');
  const update = () => {
    const checked = boxes().filter((b) => b.checked);
    bar.classList.toggle('is-visible', checked.length > 0);
    if (counter) counter.textContent = String(checked.length);
    boxes().forEach((b) => b.closest('tr')?.classList.toggle('is-selected', b.checked));
    if (all) all.indeterminate = checked.length > 0 && checked.length < boxes().length;
    if (form) {
      form.querySelectorAll('input[name="ids[]"]').forEach((i) => i.remove());
      checked.forEach((b) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = b.value;
        form.appendChild(input);
      });
    }
  };
  all?.addEventListener('change', () => { boxes().forEach((b) => { b.checked = all.checked; }); update(); });
  boxes().forEach((b) => b.addEventListener('change', update));
  const action = bar.querySelector('[data-bulk-action]');
  const panels = bar.querySelectorAll('[data-bulk-panel]');
  const togglePanels = () => panels.forEach((p) => { p.hidden = p.dataset.bulkPanel !== action.value; });
  action?.addEventListener('change', togglePanels);
  if (action) togglePanels();
  update();
})();
document.querySelectorAll('[data-period-select]').forEach((select) => {
  const custom = select.closest('form')?.querySelector('[data-period-custom]');
  const toggle = () => { if (custom) custom.hidden = select.value !== 'custom'; };
  select.addEventListener('change', toggle);
  toggle();
});
const whenLoaded = (fn) => (document.readyState === 'complete' ? fn() : window.addEventListener('load', fn, { once: true }));
whenLoaded(async () => {
  const root = document.querySelector('[data-stats-url]');
  if (!root || typeof window.Chart !== 'function') return;
  const palette = ['#0B5D45', '#C9A24B', '#1D6FA3', '#B7791F', '#2E7D32', '#B42318', '#0E7356', '#E3C77E', '#4A5A54', '#7A5C2E'];
  const rtl = document.documentElement.dir === 'rtl';
  window.Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  try {
    const response = await fetch(root.dataset.statsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) return;
    const { charts } = await response.json();
    const make = (key, type, data, options = {}) => {
      const canvas = root.querySelector(`[data-chart="${key}"]`);
      if (!canvas || !data) return;
      new window.Chart(canvas, {
        type,
        data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: type === 'bar' || type === 'line' ? palette[0] : palette, borderColor: palette[0], borderWidth: type === 'line' ? 2 : 0, tension: 0.3, fill: false }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type !== 'bar' && type !== 'line', position: 'bottom', rtl } }, scales: type === 'bar' || type === 'line' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {}, ...options },
      });
    };
    make('rubrique', 'bar', charts.rubrique);
    make('status', 'doughnut', charts.status);
    make('language', 'pie', charts.language);
    make('channel', 'doughnut', charts.channel);
    make('type', 'pie', charts.type);
    make('country', 'bar', charts.country, { indexAxis: 'y' });
    const monthly = root.querySelector('[data-chart="monthly"]');
    if (monthly && charts.monthly) {
      new window.Chart(monthly, {
        type: 'line',
        data: { labels: charts.monthly.labels, datasets: [
          { label: root.dataset.labelQuestions, data: charts.monthly.values, borderColor: palette[0], backgroundColor: 'rgba(11,93,69,.12)', tension: 0.3, fill: true },
          { label: root.dataset.labelAnswered, data: charts.monthly.answered, borderColor: palette[1], backgroundColor: 'rgba(201,162,75,.15)', tension: 0.3, fill: true },
        ] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', rtl } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
      });
    }
    const table = root.querySelector('[data-scholars-table] tbody');
    if (table) {
      table.innerHTML = '';
      (charts.scholars || []).forEach((s) => {
        const tr = document.createElement('tr');
        [s.name, s.assigned, s.open_count, s.answered, s.avg_hours ?? '—'].forEach((v, i) => {
          const td = document.createElement('td');
          td.textContent = String(v);
          if (i > 0) td.className = 'text-end';
          tr.appendChild(td);
        });
        table.appendChild(tr);
      });
    }
  } catch (error) {
    console.error(error);
  }
});
whenLoaded(() => {
  if (typeof window.Quill !== 'function') return;
  document.querySelectorAll('textarea[data-rich-editor]').forEach((textarea) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'df-editor';
    textarea.insertAdjacentElement('afterend', wrapper);
    textarea.hidden = true;
    const quill = new window.Quill(wrapper, {
      theme: 'snow',
      modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], [{ direction: 'rtl' }, { align: [] }], ['clean']] },
    });
    if (textarea.value.trim() !== '') quill.clipboard.dangerouslyPasteHTML(textarea.value);
    const sync = () => { textarea.value = quill.getText().trim() === '' ? '' : quill.getSemanticHTML(); };
    quill.on('text-change', sync);
    textarea.form?.addEventListener('submit', sync);
    const pane = textarea.closest('.tab-pane');
    if (pane) {
      document.querySelector(`[data-bs-target="#${pane.id}"]`)?.addEventListener('shown.bs.tab', () => quill.update());
    }
  });
});
document.querySelectorAll('[data-sortable]').forEach((list) => {
  const refresh = () => list.querySelectorAll('li').forEach((li, index) => {
    const order = li.querySelector('[data-order-input]');
    if (order) order.value = String(index + 1);
  });
  list.addEventListener('click', (event) => {
    const button = event.target.closest('[data-move]');
    if (!button) return;
    const li = button.closest('li');
    if (button.dataset.move === 'up' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (button.dataset.move === 'down' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
    refresh();
    button.focus();
  });
  refresh();
});
document.querySelectorAll('[data-member-pick]').forEach((radio) => {
  radio.addEventListener('change', () => {
    const target = document.querySelector('[data-member-id]');
    if (target) target.value = radio.value;
  });
});
document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
  input.addEventListener('change', () => {
    const img = document.querySelector(input.dataset.preview);
    const file = input.files && input.files[0];
    if (img && file && file.type.startsWith('image/')) {
      img.src = URL.createObjectURL(file);
      img.hidden = false;
    }
  });
});
export { csrfToken };
