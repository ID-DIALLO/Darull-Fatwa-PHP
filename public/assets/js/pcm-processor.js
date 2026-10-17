/**
 * Darul Fatwa — AudioWorklet : transmet les échantillons PCM (mono, Float32) au fil de l'eau
 * vers le thread principal, qui les envoie au worker d'encodage MP3 (§4.2.2).
 */
class PcmProcessor extends AudioWorkletProcessor {
  constructor() {
    super();
    this.buffer = new Float32Array(4096);
    this.offset = 0;
  }

  process(inputs) {
    const input = inputs[0];
    if (!input || input.length === 0) {
      return true;
    }
    const channel = input[0];
    for (let i = 0; i < channel.length; i++) {
      this.buffer[this.offset++] = channel[i];
      if (this.offset >= this.buffer.length) {
        this.port.postMessage(this.buffer.slice(0));
        this.offset = 0;
      }
    }
    return true;
  }
}

registerProcessor('df-pcm-processor', PcmProcessor);
