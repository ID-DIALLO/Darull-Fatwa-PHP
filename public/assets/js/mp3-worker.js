/**
 * Darul Fatwa — encodage MP3 dans un Web Worker (lamejs, hébergé localement, §4.2.2).
 * Messages reçus : { cmd: 'init', sampleRate, bitRate, lame } · { cmd: 'encode', samples: Float32Array }
 *                  · { cmd: 'finish' } · { cmd: 'reset' }
 * Messages envoyés : { cmd: 'ready' } · { cmd: 'done', blob } · { cmd: 'error', message }
 * Mono, 64 kb/s par défaut (≈ 0,5 Mo/min), lisible partout.
 */
let encoder = null;
let parts = [];
const BLOCK = 1152;

function toInt16(samples) {
  const out = new Int16Array(samples.length);
  for (let i = 0; i < samples.length; i++) {
    const s = Math.max(-1, Math.min(1, samples[i]));
    out[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
  }
  return out;
}

self.onmessage = (event) => {
  const data = event.data || {};
  try {
    switch (data.cmd) {
      case 'init':
        if (typeof self.lamejs === 'undefined') {
          importScripts(data.lame);
        }
        encoder = new self.lamejs.Mp3Encoder(1, data.sampleRate || 44100, data.bitRate || 64);
        parts = [];
        self.postMessage({ cmd: 'ready' });
        break;
      case 'encode': {
        if (!encoder) return;
        const pcm = toInt16(data.samples);
        for (let i = 0; i < pcm.length; i += BLOCK) {
          const chunk = pcm.subarray(i, i + BLOCK);
          const mp3 = encoder.encodeBuffer(chunk);
          if (mp3.length > 0) parts.push(new Int8Array(mp3));
        }
        break;
      }
      case 'finish': {
        if (!encoder) return;
        const tail = encoder.flush();
        if (tail.length > 0) parts.push(new Int8Array(tail));
        const blob = new Blob(parts, { type: 'audio/mpeg' });
        parts = [];
        self.postMessage({ cmd: 'done', blob });
        break;
      }
      case 'reset':
        parts = [];
        break;
      default:
        break;
    }
  } catch (error) {
    self.postMessage({ cmd: 'error', message: String(error && error.message ? error.message : error) });
  }
};
