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
