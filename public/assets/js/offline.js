// Page hors-ligne : bouton « Réessayer » (aucun script en ligne, CSP)
document.querySelectorAll('[data-reload]').forEach((button) => button.addEventListener('click', () => window.location.reload()));
