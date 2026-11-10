/*
 | Voice dictation for text fields, through the browser's Web Speech API.
 |
 |   <div x-data="voiceInput({ locale: 'ar', target: '#coach-message', denied: '…', failed: '…' })" x-show="supported" x-cloak>
 |       <button type="button" x-on:click="toggle()" :aria-pressed="listening.toString()">…</button>
 |   </div>
 |
 | The transcript is written into the target field (and an `input` event is dispatched so wire:model
 | picks it up) for the user to review and edit. It is never submitted automatically.
 | `supported` is false when the browser has no SpeechRecognition: hide the control with x-show.
 | Arabic tries Syrian Arabic (ar-SY) first and falls back to Saudi Arabic (ar-SA); English uses en-US.
 */

const LANGUAGES = {
    ar: ['ar-SY', 'ar-SA'],
    en: ['en-US'],
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('voiceInput', ({ locale = 'ar', target = null, denied = '', failed = '' } = {}) => ({
        supported: false,
        listening: false,
        error: '',
        recognition: null,
        languages: LANGUAGES[locale] ?? LANGUAGES.en,
        languageIndex: 0,
        base: '',
        heard: false,

        init() {
            this.supported = Boolean(window.SpeechRecognition || window.webkitSpeechRecognition);
        },

        field() {
            return target ? document.querySelector(target) : null;
        },

        toggle() {
            if (this.listening) {
                this.stop();
            } else {
                this.languageIndex = 0;
                this.start();
            }
        },

        start() {
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const field = this.field();
            if (!Recognition || !field) return;

            this.error = '';
            this.heard = false;
            this.base = field.value ? field.value.replace(/\s+$/, '') + ' ' : '';

            const recognition = new Recognition();
            recognition.lang = this.languages[this.languageIndex];
            recognition.interimResults = true;
            recognition.continuous = false;
            recognition.maxAlternatives = 1;

            recognition.onresult = (event) => {
                let transcript = '';
                for (let i = 0; i < event.results.length; i++) {
                    transcript += event.results[i][0].transcript;
                }
                this.heard = transcript.trim() !== '';
                this.write(this.base + transcript.trim());
            };

            recognition.onerror = (event) => {
                if (event.error === 'language-not-supported' && this.languageIndex < this.languages.length - 1) {
                    this.languageIndex++;
                    this.listening = false;
                    this.start();
                    return;
                }
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    this.error = denied;
                } else if (event.error !== 'aborted' && event.error !== 'no-speech') {
                    this.error = failed;
                }
                this.listening = false;
            };

            recognition.onend = () => {
                this.listening = false;
                this.recognition = null;
            };

            this.recognition = recognition;
            this.listening = true;

            try {
                recognition.start();
            } catch {
                this.listening = false;
                this.error = failed;
            }
        },

        stop() {
            try {
                this.recognition?.stop();
            } catch {
                // already stopped
            }
            this.listening = false;
        },

        write(text) {
            const field = this.field();
            if (!field) return;
            field.value = text;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));
});
