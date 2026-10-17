/**
 * Darul Fatwa — graphiques des statistiques du professeur (Chart.js, données dans un bloc JSON ; aucun script en ligne).
 */
const node = document.getElementById('scholar-stats-data');
if (node) {
  const data = JSON.parse(node.textContent);
  const start = () => {
    if (typeof window.Chart !== 'function') { return; }
    const green = getComputedStyle(document.documentElement).getPropertyValue('--df-green').trim() || '#0B5D45';
    const gold = getComputedStyle(document.documentElement).getPropertyValue('--df-gold').trim() || '#C9A227';
    const monthly = document.querySelector('canvas[data-chart="monthly"]');
    if (monthly) {
      new window.Chart(monthly, {
        type: 'bar',
        data: { labels: data.labels, datasets: [
          { label: data.legend.received, data: data.received, backgroundColor: gold, borderRadius: 4 },
          { label: data.legend.answered, data: data.answered, backgroundColor: green, borderRadius: 4 },
        ] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
      });
    }
    const rubriques = document.querySelector('canvas[data-chart="rubriques"]');
    if (rubriques && data.rubriques.labels.length > 0) {
      const palette = [green, gold, '#1F7A5C', '#E0B84F', '#4A5A54', '#8FB8A8', '#B7791F', '#2E9C78'];
      new window.Chart(rubriques, {
        type: 'doughnut',
        data: { labels: data.rubriques.labels, datasets: [{ data: data.rubriques.values, backgroundColor: data.rubriques.labels.map((_, i) => palette[i % palette.length]) }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
      });
    }
  };
  if (document.readyState === 'complete') { start(); } else { window.addEventListener('load', start); }
}
