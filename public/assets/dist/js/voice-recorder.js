const DB_NAME = 'darul-fatwa';
const STORE = 'drafts';
function openDb() {
  return new Promise((resolve) => {
    if (!('indexedDB' in window)) return resolve(null);
    try {
      const request = indexedDB.open(DB_NAME, 1);
      request.onupgradeneeded = () => request.result.createObjectStore(STORE);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
}
async function draftSave(key, value) {
  const db = await openDb();
  if (!db) return;
  try {
    db.transaction(STORE, 'readwrite').objectStore(STORE).put(value, key);
  } catch { /* stockage indisponible : on garde la version en mémoire */ }
}
async function draftLoad(key) {
  const db = await openDb();
  if (!db) return null;
  return new Promise((resolve) => {
    try {
      const req = db.transaction(STORE, 'readonly').objectStore(STORE).get(key);
      req.onsuccess = () => resolve(req.result || null);
      req.onerror = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
}
async function draftClear(key) {
  const db = await openDb();
  if (!db) return;
  try {
    db.transaction(STORE, 'readwrite').objectStore(STORE).delete(key);
  } catch { /* ignoré */ }
}
function formatTime(seconds) {
  const s = Math.max(0, Math.round(seconds));
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
export class VoiceRecorder {
  constructor(root) {
    this.root = root;
    this.labels = JSON.parse(root.dataset.labels || '{}');
    this.uploadUrl = root.dataset.uploadUrl;
    this.deleteUrl = root.dataset.deleteUrl;
    this.maxSeconds = parseInt(root.dataset.maxSeconds || '300', 10);
    this.minSeconds = parseInt(root.dataset.minSeconds || '2', 10);
    this.draftKey = `recorder:${root.dataset.field}:${location.pathname}`;
    this.field = root.querySelector('[data-recorder-field]');
    this.mainButton = root.querySelector('[data-recorder-main]');
    this.timer = root.querySelector('[data-recorder-timer]');
    this.wave = root.querySelector('[data-recorder-wave]');
    this.message = root.querySelector('[data-recorder-message]');
    this.controls = root.querySelector('[data-recorder-controls]');
    this.pauseButton = root.querySelector('[data-recorder-pause]');
    this.stopButton = root.querySelector('[data-recorder-stop]');
    this.review = root.querySelector('[data-recorder-review]');
    this.preview = root.querySelector('[data-recorder-preview]');
    this.sendButton = root.querySelector('[data-recorder-send]');
    this.deleteButton = root.querySelector('[data-recorder-delete]');
    this.progress = root.querySelector('[data-recorder-progress]');
    this.help = root.querySelector('[data-recorder-help]');
    this.state = 'idle'; // idle | recording | paused | ready | uploading | uploaded
    this.elapsed = 0;
    this.blob = null;
    this.uploadedUuid = this.field.value || null;
    this.secure = window.isSecureContext;
    this.supported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && (window.AudioContext || window.webkitAudioContext));
    this.bind();
    this.restoreExisting();
  }
  bind() {
    this.mainButton.addEventListener('click', () => this.onMain());
    this.pauseButton.addEventListener('click', () => this.togglePause());
    this.stopButton.addEventListener('click', () => this.stop());
    this.sendButton.addEventListener('click', () => this.upload());
    this.deleteButton.addEventListener('click', () => this.reset(true));
    window.addEventListener('online', () => {
      if (this.state === 'ready' && this.blob) this.setMessage(this.labels.ready.replace(':duration', formatTime(this.elapsed)));
    });
  }
  async restoreExisting() {
    const { existingUuid, existingUrl, existingDuration } = this.root.dataset;
    if (existingUuid && existingUrl) {
      this.uploadedUuid = existingUuid;
      this.elapsed = parseInt(existingDuration || '0', 10);
      this.preview.src = existingUrl;
      this.showReview(true);
      return;
    }
    const draft = await draftLoad(this.draftKey);
    if (draft && draft.blob && !this.uploadedUuid) {
      this.blob = draft.blob;
      this.elapsed = draft.duration || 0;
      this.mime = draft.blob.type;
      this.preview.src = URL.createObjectURL(this.blob);
      this.state = 'ready';
      this.showReview(false);
      this.setMessage(`${this.labels.draft_found} ${this.labels.ready.replace(':duration', formatTime(this.elapsed))}`);
    }
  }
  setMessage(text, help = null) {
    this.message.textContent = text;
    if (help !== null) {
      this.help.innerHTML = help;
      this.help.hidden = help === '';
    }
  }
  setHelp(html) {
    this.help.innerHTML = html;
    this.help.hidden = false;
  }
  onMain() {
    if (this.state === 'idle') {
      this.start();
    } else if (this.state === 'recording' || this.state === 'paused') {
      this.stop();
    }
  }
  async start() {
    if (!this.supported) {
      this.setMessage(this.labels.unsupported);
      return;
    }
    if (!this.secure) {
      this.setMessage(this.labels.https_required);
      return;
    }
    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, channelCount: 1 } });
    } catch (error) {
      const denied = error && (error.name === 'NotAllowedError' || error.name === 'SecurityError');
      const ua = navigator.userAgent;
      const tip = /iPhone|iPad/.test(ua) ? this.labels.denied_ios : /Android/.test(ua) ? this.labels.denied_android : '';
      this.setMessage(denied ? this.labels.denied : this.labels.unsupported);
      this.setHelp(`<strong>${this.labels.denied_title}</strong><br>${this.labels.denied}${tip ? '<br>' + tip : ''}`);
      return;
    }
    this.help.hidden = true;
    this.stream = stream;
    this.elapsed = 0;
    this.blob = null;
    this.alerted = false;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    this.context = new Ctx();
    await this.context.resume().catch(() => {});
    this.source = this.context.createMediaStreamSource(stream);
    this.analyser = this.context.createAnalyser();
    this.analyser.fftSize = 512;
    this.source.connect(this.analyser);
    const mp3Ready = await this.startMp3Pipeline().catch(() => false);
    if (!mp3Ready) {
      this.startMediaRecorderFallback();
    }
    this.state = 'recording';
    this.mainButton.classList.add('is-recording');
    this.mainButton.setAttribute('aria-label', this.labels.stop);
    this.mainButton.querySelector('i').className = 'bi bi-stop-fill';
    this.controls.hidden = false;
    this.review.hidden = true;
    this.setMessage(this.labels.recording);
    this.lastTick = performance.now();
    this.tick();
    this.drawWave();
  }
  async startMp3Pipeline() {
    if (!window.Worker) return false;
    this.worker = new Worker(this.root.dataset.worker);
    const ready = new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('worker timeout')), 4000);
      this.worker.onmessage = (event) => {
        if (event.data.cmd === 'ready') {
          clearTimeout(timeout);
          resolve(true);
        } else if (event.data.cmd === 'error') {
          clearTimeout(timeout);
          reject(new Error(event.data.message));
        }
      };
      this.worker.onerror = (e) => {
        clearTimeout(timeout);
        reject(e);
      };
    });
    const lameUrl = new URL(this.root.dataset.lame, location.href).href;
    this.worker.postMessage({ cmd: 'init', sampleRate: this.context.sampleRate, bitRate: 64, lame: lameUrl });
    await ready;
    const onSamples = (samples) => {
      if (this.state === 'recording') {
        this.worker.postMessage({ cmd: 'encode', samples }, [samples.buffer]);
      }
    };
    if (this.context.audioWorklet) {
      try {
        await this.context.audioWorklet.addModule(this.root.dataset.processor);
        this.node = new AudioWorkletNode(this.context, 'df-pcm-processor');
        this.node.port.onmessage = (event) => onSamples(event.data);
      } catch {
        this.node = null;
      }
    }
    if (!this.node) {
      this.node = this.context.createScriptProcessor(4096, 1, 1);
      this.node.onaudioprocess = (event) => onSamples(new Float32Array(event.inputBuffer.getChannelData(0)));
    }
    this.source.connect(this.node);
    this.silence = this.context.createGain();
    this.silence.gain.value = 0;
    this.node.connect(this.silence);
    this.silence.connect(this.context.destination);
    this.mode = 'mp3';
    return true;
  }
  startMediaRecorderFallback() {
    if (!window.MediaRecorder) {
      throw new Error('unsupported');
    }
    const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].find((t) => MediaRecorder.isTypeSupported(t)) || '';
    this.chunks = [];
    this.recorder = new MediaRecorder(this.stream, type ? { mimeType: type, audioBitsPerSecond: 64000 } : undefined);
    this.recorder.ondataavailable = (e) => { if (e.data.size > 0) this.chunks.push(e.data); };
    this.recorder.start(1000);
    this.mode = 'native';
    this.setHelp(this.labels.fallback);
  }
  tick() {
    if (this.state !== 'recording') return;
    const now = performance.now();
    this.elapsed += (now - this.lastTick) / 1000;
    this.lastTick = now;
    this.timer.textContent = `${formatTime(this.elapsed)} / ${formatTime(this.maxSeconds)}`;
    const remaining = this.maxSeconds - this.elapsed;
    if (remaining <= 30 && !this.alerted) {
      this.alerted = true;
      this.setMessage(this.labels.almost_max);
    }
    if (remaining <= 0) {
      this.stop(true);
      return;
    }
    this.rafTick = requestAnimationFrame(() => this.tick());
  }
  drawWave() {
    if (!this.wave || !this.analyser) return;
    const ctx = this.wave.getContext('2d');
    const data = new Uint8Array(this.analyser.frequencyBinCount);
    const draw = () => {
      if (this.state !== 'recording' && this.state !== 'paused') {
        ctx.clearRect(0, 0, this.wave.width, this.wave.height);
        return;
      }
      this.analyser.getByteTimeDomainData(data);
      ctx.clearRect(0, 0, this.wave.width, this.wave.height);
      ctx.lineWidth = 2;
      ctx.strokeStyle = this.state === 'paused' ? '#B7791F' : '#0B5D45';
      ctx.beginPath();
      const step = this.wave.width / data.length;
      for (let i = 0; i < data.length; i++) {
        const y = (data[i] / 255) * this.wave.height;
        if (i === 0) ctx.moveTo(0, y); else ctx.lineTo(i * step, y);
      }
      ctx.stroke();
      this.rafWave = requestAnimationFrame(draw);
    };
    draw();
  }
  togglePause() {
    if (this.state === 'recording') {
      this.state = 'paused';
      if (this.recorder && this.recorder.state === 'recording') this.recorder.pause();
      cancelAnimationFrame(this.rafTick);
      this.pauseButton.querySelector('i').className = 'bi bi-record-fill';
      this.pauseButton.querySelector('span').textContent = this.labels.resume;
      this.mainButton.classList.remove('is-recording');
      this.setMessage(this.labels.paused);
    } else if (this.state === 'paused') {
      this.state = 'recording';
      if (this.recorder && this.recorder.state === 'paused') this.recorder.resume();
      this.lastTick = performance.now();
      this.pauseButton.querySelector('i').className = 'bi bi-pause-fill';
      this.pauseButton.querySelector('span').textContent = this.labels.pause;
      this.mainButton.classList.add('is-recording');
      this.setMessage(this.labels.recording);
      this.tick();
      this.drawWave();
    }
  }
  async stop(auto = false) {
    if (this.state !== 'recording' && this.state !== 'paused') return;
    cancelAnimationFrame(this.rafTick);
    cancelAnimationFrame(this.rafWave);
    const duration = this.elapsed;
    this.state = 'stopping';
    let blob = null;
    if (this.mode === 'mp3') {
      blob = await new Promise((resolve) => {
        const timeout = setTimeout(() => resolve(null), 15000);
        this.worker.onmessage = (event) => {
          if (event.data.cmd === 'done') {
            clearTimeout(timeout);
            resolve(event.data.blob);
          }
        };
        this.worker.postMessage({ cmd: 'finish' });
      });
      this.worker.terminate();
      this.worker = null;
    } else if (this.recorder) {
      blob = await new Promise((resolve) => {
        this.recorder.onstop = () => resolve(new Blob(this.chunks, { type: this.recorder.mimeType || 'audio/webm' }));
        this.recorder.stop();
      });
      this.recorder = null;
    }
    this.releaseMedia();
    this.mainButton.classList.remove('is-recording');
    this.mainButton.setAttribute('aria-label', this.labels.record);
    this.mainButton.querySelector('i').className = 'bi bi-mic-fill';
    this.controls.hidden = true;
    this.pauseButton.querySelector('i').className = 'bi bi-pause-fill';
    this.pauseButton.querySelector('span').textContent = this.labels.pause;
    if (!blob || duration < this.minSeconds) {
      this.state = 'idle';
      this.setMessage(this.labels.too_short);
      return;
    }
    this.blob = blob;
    this.elapsed = duration;
    this.preview.src = URL.createObjectURL(blob);
    this.state = 'ready';
    this.showReview(false);
    this.setMessage(auto ? `${this.labels.max_reached} ${this.labels.ready.replace(':duration', formatTime(duration))}` : this.labels.ready.replace(':duration', formatTime(duration)));
    draftSave(this.draftKey, { blob, duration, savedAt: Date.now() });
    this.root.dispatchEvent(new CustomEvent('recorder:recorded', { detail: { duration }, bubbles: true }));
  }
  releaseMedia() {
    try { this.node && this.node.disconnect(); } catch { /* ignoré */ }
    try { this.source && this.source.disconnect(); } catch { /* ignoré */ }
    if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
    if (this.context) this.context.close().catch(() => {});
    this.stream = null;
    this.context = null;
    this.node = null;
  }
  showReview(uploaded) {
    this.review.hidden = false;
    this.sendButton.hidden = uploaded;
    this.mainButton.disabled = true;
    this.mainButton.classList.toggle('is-done', uploaded);
    this.timer.textContent = formatTime(this.elapsed);
    if (uploaded) {
      this.state = 'uploaded';
      this.setMessage(this.labels.uploaded);
    }
  }
  async reset(remote) {
    if (remote && this.uploadedUuid && this.deleteUrl) {
      try {
        await fetch(this.deleteUrl.replace('__UUID__', this.uploadedUuid), { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken(), Accept: 'application/json' }, credentials: 'same-origin' });
      } catch { /* l'enregistrement non rattaché sera ignoré */ }
    }
    this.uploadedUuid = null;
    this.field.value = '';
    this.blob = null;
    this.elapsed = 0;
    this.state = 'idle';
    this.preview.removeAttribute('src');
    this.review.hidden = true;
    this.sendButton.hidden = false;
    this.progress.hidden = true;
    this.mainButton.disabled = false;
    this.mainButton.classList.remove('is-done');
    this.timer.textContent = '0:00';
    this.help.hidden = true;
    this.setMessage(this.labels.record);
    draftClear(this.draftKey);
    this.root.dispatchEvent(new CustomEvent('recorder:deleted', { bubbles: true }));
  }
  upload(attempt = 1) {
    if (!this.blob || this.state === 'uploading') return;
    if (!navigator.onLine) {
      this.setMessage(this.labels.offline);
      return;
    }
    this.state = 'uploading';
    this.sendButton.disabled = true;
    this.deleteButton.disabled = true;
    this.progress.hidden = false;
    const bar = this.progress.querySelector('.progress-bar');
    const extension = this.blob.type.includes('mpeg') ? 'mp3' : this.blob.type.includes('mp4') ? 'm4a' : this.blob.type.includes('ogg') ? 'ogg' : 'webm';
    const form = new FormData();
    form.append('audio', this.blob, `enregistrement.${extension}`);
    form.append('duration', String(Math.round(this.elapsed)));
    form.append('_token', csrfToken());
    const xhr = new XMLHttpRequest();
    xhr.open('POST', this.uploadUrl, true);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-Token', csrfToken());
    xhr.timeout = 120000;
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        bar.style.width = `${percent}%`;
        this.progress.setAttribute('aria-valuenow', String(percent));
        this.setMessage(this.labels.uploading.replace(':percent', String(percent)));
      }
    };
    const fail = () => {
      if (attempt < 3 && navigator.onLine) {
        this.setMessage(this.labels.retrying.replace(':attempt', String(attempt + 1)));
        this.state = 'ready';
        setTimeout(() => this.upload(attempt + 1), 1500 * attempt);
        return;
      }
      this.state = 'ready';
      this.sendButton.disabled = false;
      this.deleteButton.disabled = false;
      this.sendButton.querySelector('i').className = 'bi bi-arrow-clockwise';
      this.sendButton.lastChild.textContent = ' ' + this.labels.retry;
      this.setMessage(navigator.onLine ? this.labels.failed : this.labels.offline);
    };
    xhr.onerror = fail;
    xhr.ontimeout = fail;
    xhr.onload = () => {
      let data = {};
      try { data = JSON.parse(xhr.responseText || '{}'); } catch { /* réponse vide */ }
      if (xhr.status >= 200 && xhr.status < 300 && data.media_uuid) {
        this.uploadedUuid = data.media_uuid;
        this.field.value = data.media_uuid;
        this.elapsed = data.duration || this.elapsed;
        this.sendButton.disabled = false;
        this.deleteButton.disabled = false;
        this.progress.hidden = true;
        this.showReview(true);
        draftClear(this.draftKey);
        this.root.dispatchEvent(new CustomEvent('recorder:uploaded', { detail: { uuid: data.media_uuid, duration: this.elapsed }, bubbles: true }));
      } else if (xhr.status === 422 || xhr.status === 413 || xhr.status === 401 || xhr.status === 419) {
        this.state = 'ready';
        this.sendButton.disabled = false;
        this.deleteButton.disabled = false;
        this.progress.hidden = true;
        this.setMessage(data.message || this.labels.failed);
      } else {
        fail();
      }
    };
    xhr.send(form);
  }
}
document.querySelectorAll('[data-voice-recorder]').forEach((root) => {
  root.recorder = new VoiceRecorder(root);
});
