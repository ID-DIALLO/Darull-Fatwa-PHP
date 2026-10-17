/**
 * Darul Fatwa — lecteur audio maison (§4.2.2) : lecture/pause, progression, vitesse 0,75× à 2×.
 * Améliore progressivement <div data-audio-player> (voir partials/audio-player.php) ; sans JS, <audio controls> reste.
 */
function formatTime(seconds) {
  if (!Number.isFinite(seconds)) return '--:--';
  const s = Math.max(0, Math.round(seconds));
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

export function enhancePlayer(root) {
  const audio = root.querySelector('audio');
  const toggle = root.querySelector('.df-player__toggle');
  const body = root.querySelector('.df-player__body');
  const seek = root.querySelector('.df-player__seek');
  const current = root.querySelector('.df-player__current');
  const durationEl = root.querySelector('.df-player__duration');
  const speed = root.querySelector('.df-player__speed');
  if (!audio || !toggle) return;

  audio.removeAttribute('controls');
  audio.classList.add('visually-hidden');
  toggle.hidden = false;
  body.hidden = false;
  if (speed) speed.hidden = false;
  let seeking = false;

  const setIcon = (playing) => {
    toggle.querySelector('i').className = playing ? 'bi bi-pause-fill' : 'bi bi-play-fill';
    toggle.setAttribute('aria-label', playing ? root.dataset.labelPause : root.dataset.labelPlay);
    root.classList.toggle('is-playing', playing);
  };

  toggle.addEventListener('click', () => {
    if (audio.paused) {
      document.querySelectorAll('[data-audio-player] audio').forEach((other) => { if (other !== audio) other.pause(); });
      audio.play().catch(() => {});
    } else {
      audio.pause();
    }
  });
  audio.addEventListener('play', () => setIcon(true));
  audio.addEventListener('pause', () => setIcon(false));
  audio.addEventListener('ended', () => { setIcon(false); seek.value = '0'; current.textContent = '0:00'; });
  audio.addEventListener('loadedmetadata', () => {
    if (Number.isFinite(audio.duration)) {
      seek.max = String(Math.round(audio.duration));
      durationEl.textContent = formatTime(audio.duration);
    }
  });
  audio.addEventListener('timeupdate', () => {
    if (!seeking) {
      seek.value = String(Math.floor(audio.currentTime));
      current.textContent = formatTime(audio.currentTime);
    }
  });
  seek.addEventListener('input', () => { seeking = true; current.textContent = formatTime(Number(seek.value)); });
  seek.addEventListener('change', () => { audio.currentTime = Number(seek.value); seeking = false; });

  if (speed) {
    const button = speed.querySelector('button');
    speed.querySelectorAll('[data-rate]').forEach((item) => {
      item.addEventListener('click', () => {
        audio.playbackRate = Number(item.dataset.rate);
        button.textContent = `${item.dataset.rate}×`;
      });
    });
  }
}

document.querySelectorAll('[data-audio-player]').forEach(enhancePlayer);

// Confirmation simple des formulaires sensibles (ex. « Ma question est résolue »)
document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
