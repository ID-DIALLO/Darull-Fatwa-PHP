function init() {
  const el = document.querySelector('[data-hero-slider]');
  if (!el || typeof window.Swiper !== 'function') {
    return;
  }
  const slides = el.querySelectorAll('.swiper-slide').length;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const toggle = el.querySelector('[data-hero-toggle]');
  const swiper = new window.Swiper(el, {
    loop: slides > 1,
    effect: 'fade',
    fadeEffect: { crossFade: true },
    speed: reduced ? 0 : 700,
    autoplay: slides > 1 && !reduced ? { delay: 6000, disableOnInteraction: false, pauseOnMouseEnter: true } : false,
    pagination: slides > 1 ? {
      el: el.querySelector('.swiper-pagination'),
      clickable: true,
      renderBullet: (index, className) => `<button type="button" class="${className}" aria-label="${(el.dataset.goTo || 'Slide') + ' ' + (index + 1)}"></button>`,
    } : false,
    navigation: slides > 1 ? { nextEl: el.querySelector('.df-hero__next'), prevEl: el.querySelector('.df-hero__prev') } : false,
    a11y: { enabled: true },
    keyboard: { enabled: true },
  });
  if (toggle && swiper.autoplay) {
    let running = !reduced && slides > 1;
    const render = () => {
      toggle.setAttribute('aria-pressed', String(!running));
      toggle.setAttribute('aria-label', running ? toggle.dataset.labelPause : toggle.dataset.labelPlay);
      toggle.querySelector('i').className = running ? 'bi bi-pause-fill' : 'bi bi-play-fill';
    };
    toggle.addEventListener('click', () => {
      running = !running;
      if (running) {
        swiper.autoplay.start();
      } else {
        swiper.autoplay.stop();
      }
      render();
    });
    render();
  }
}
if (document.readyState === 'complete') {
  init();
} else {
  window.addEventListener('load', init, { once: true });
}
