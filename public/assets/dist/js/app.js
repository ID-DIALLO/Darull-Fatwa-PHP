document.querySelectorAll('[data-reload]').forEach((link) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    window.location.reload();
  });
});
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
(() => {
  const toggle = document.querySelector('[data-mega-toggle]');
  const panel = document.getElementById('df-mega');
  if (!toggle || !panel) {
    return;
  }
  const close = () => {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  };
  toggle.addEventListener('click', () => {
    const open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', (event) => {
    if (!panel.hidden && !panel.contains(event.target) && !toggle.contains(event.target)) {
      close();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      close();
      toggle.focus();
    }
  });
})();
(() => {
  const button = document.querySelector('[data-back-to-top]');
  if (!button) {
    return;
  }
  const update = () => {
    button.hidden = window.scrollY < 600;
  };
  window.addEventListener('scroll', update, { passive: true });
  button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  update();
})();
(() => {
  const counters = document.querySelectorAll('[data-counter]');
  if (counters.length === 0) {
    return;
  }
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const format = (n) => new Intl.NumberFormat(document.documentElement.lang + '-u-nu-latn').format(n);
  const animate = (el) => {
    const target = parseInt(el.dataset.counter, 10) || 0;
    if (reduced || target === 0) {
      el.textContent = format(target);
      return;
    }
    const duration = 1200;
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      el.textContent = format(Math.round(target * (1 - Math.pow(1 - progress, 3))));
      if (progress < 1) {
        requestAnimationFrame(step);
      }
    };
    requestAnimationFrame(step);
  };
  if (!('IntersectionObserver' in window)) {
    counters.forEach(animate);
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animate(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.4 });
  counters.forEach((el) => observer.observe(el));
})();
(() => {
  const badge = document.querySelector('[data-notification-count]');
  if (!badge) {
    return;
  }
  const refresh = async () => {
    try {
      const response = await fetch('/api/notifications/count', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) {
        return;
      }
      const data = await response.json();
      const count = Number(data.count || 0);
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = count === 0;
    } catch {
      /* hors ligne : on garde l'affichage précédent */
    }
  };
  refresh();
  setInterval(refresh, 60000);
})();
document.querySelectorAll('[data-copy-link]').forEach((button) => {
  button.addEventListener('click', async () => {
    const status = button.parentElement?.querySelector('.df-share__status');
    try {
      await navigator.clipboard.writeText(button.dataset.copyLink);
      if (status) { status.textContent = button.dataset.copiedLabel; setTimeout(() => { status.textContent = ''; }, 2500); }
    } catch {
      window.prompt(button.dataset.copiedLabel, button.dataset.copyLink);
    }
  });
});
document.querySelectorAll('[data-favorite-form]').forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button');
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error(String(response.status));
      const data = await response.json();
      button.classList.toggle('btn-gold', data.favorite);
      button.classList.toggle('btn-outline-primary', !data.favorite);
      button.setAttribute('aria-pressed', String(data.favorite));
      button.querySelector('i').className = 'bi bi-bookmark' + (data.favorite ? '-fill' : '');
      button.querySelector('[data-favorite-label]').textContent = data.favorite ? button.dataset.labelRemove : button.dataset.labelAdd;
      button.querySelector('[data-favorite-count]').textContent = String(data.count);
    } catch {
      form.submit();
    }
  });
});
(() => {
  const form = document.getElementById('comment-form');
  if (!form) return;
  const parent = form.querySelector('[data-reply-parent]');
  const indicator = form.querySelector('[data-reply-indicator]');
  document.querySelectorAll('[data-reply-to]').forEach((btn) => btn.addEventListener('click', () => {
    parent.value = btn.dataset.replyTo;
    indicator.hidden = false;
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.querySelector('textarea').focus();
  }));
  form.querySelector('[data-reply-cancel]')?.addEventListener('click', () => { parent.value = ''; indicator.hidden = true; });
})();
const toggleFetch = (selector, key) => document.querySelectorAll(selector).forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button');
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error(String(response.status));
      const data = await response.json();
      const active = Boolean(data[key]);
      button.classList.toggle('btn-primary', active);
      button.classList.toggle('btn-outline-primary', !active);
      button.setAttribute('aria-pressed', String(active));
      const icon = button.querySelector('i');
      if (icon) icon.className = 'bi bi-hand-thumbs-up' + (active ? '-fill' : '');
      const count = button.querySelector('[data-like-count], [data-vote-count]');
      if (count) count.textContent = String(data.count);
    } catch {
      form.submit();
    }
  });
});
toggleFetch('[data-like-form]', 'liked');
toggleFetch('[data-vote-form]', 'voted');
(() => {
  const form = document.getElementById('reply-form');
  if (!form) return;
  const field = form.querySelector('[data-quote-field]');
  const indicator = form.querySelector('[data-quote-indicator]');
  const name = form.querySelector('[data-quote-name]');
  document.querySelectorAll('[data-quote-post]').forEach((btn) => btn.addEventListener('click', () => {
    field.value = btn.dataset.quotePost;
    name.textContent = btn.dataset.quoteAuthor;
    indicator.hidden = false;
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.querySelector('textarea').focus();
  }));
  form.querySelector('[data-quote-cancel]')?.addEventListener('click', () => { field.value = ''; indicator.hidden = true; });
})();
if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { /* navigateur ou contexte non pris en charge */ });
  });
}
