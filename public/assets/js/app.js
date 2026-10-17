/**
 * Darul Fatwa — script principal (module ES, sans étape de build).
 * Aucun script en ligne dans les pages : la politique CSP l'interdit.
 */

// Bouton « Réessayer » des pages d'erreur
document.querySelectorAll('[data-reload]').forEach((link) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    window.location.reload();
  });
});

// Jeton CSRF disponible pour les appels fetch() (en-tête X-CSRF-Token)
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// Méga-menu « Rubriques » (ouverture au clic, fermeture à l'extérieur et avec Échap)
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

// Bouton « retour en haut »
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

// Compteurs animés des chiffres clés (à l'apparition, sauf prefers-reduced-motion)
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

// Compteur de notifications (rafraîchi toutes les 60 s) — actif dès que l'API existe
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
