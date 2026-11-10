/*
 | UI behaviour shared by the Blade components. Alpine itself ships with Livewire
 | (including the focus plugin used by modals), so nothing is imported here.
 |
 | Theme: window.KetoTheme is defined inline in layouts/partials/head.blade.php
 | so the right theme is applied before first paint.
 */

import './voice.js';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /*
     | <x-toaster> — listens for `toast` browser events:
     |   $dispatch('toast', { message: '…', type: 'success' })        (Alpine)
     |   $this->dispatch('toast', message: '…', type: 'error')        (Livewire)
     |   window.dispatchEvent(new CustomEvent('toast', { detail: { message: '…' } }))
     */
    Alpine.data('toaster', (initial = []) => ({
        toasts: [],
        nextId: 1,

        init() {
            initial.forEach((toast) => this.push(toast));
        },

        push(detail) {
            // Livewire named params arrive as an object; positional params as an array.
            const data = Array.isArray(detail) ? detail[0] : detail;
            const toast = typeof data === 'string' ? { message: data } : { ...(data || {}) };

            if (!toast.message) return;

            toast.id = this.nextId++;
            toast.type = ['success', 'error', 'warning', 'info'].includes(toast.type) ? toast.type : 'success';
            this.toasts.push(toast);

            const duration = toast.duration ?? (toast.type === 'error' ? 7000 : 4000);
            if (duration > 0) setTimeout(() => this.dismiss(toast.id), duration);
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }));

    /*
     | <x-modal> / <x-bottom-sheet> — opened and closed by name:
     |   $dispatch('open-modal', 'name')                     (Alpine)
     |   $this->dispatch('open-modal', name: 'name')         (Livewire)
     | `close-modal` without a name closes every modal.
     */
    Alpine.data('modal', ({ name, open = false, dismissible = true }) => ({
        name,
        open,
        dismissible,

        target(event) {
            let detail = event.detail;
            if (Array.isArray(detail)) detail = detail[0];
            if (detail && typeof detail === 'object') detail = detail.name;
            return detail ?? null;
        },

        onOpen(event) {
            if (this.target(event) === this.name) this.open = true;
        },

        onClose(event) {
            const target = this.target(event);
            if (target === null || target === this.name) this.open = false;
        },

        dismiss() {
            if (this.open && this.dismissible) this.open = false;
        },
    }));

    /*
     | <x-number-stepper> — quantity entry with −/+ buttons and free typing.
     | Emits a normal `input` event so wire:model / x-model on the inner input work.
     */
    Alpine.data('numberStepper', ({ min = 0, max = null, step = 1 } = {}) => ({
        min,
        max,
        step,

        get input() {
            return this.$refs.input;
        },

        current() {
            const value = parseFloat(String(this.input.value).replace(',', '.'));
            return Number.isFinite(value) ? value : 0;
        },

        set(value) {
            if (this.max !== null) value = Math.min(value, this.max);
            value = Math.max(value, this.min);
            // Avoid 0.30000000000000004 when stepping decimals.
            this.input.value = String(Math.round(value * 1000) / 1000);
            this.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        increment() {
            this.set(this.current() + this.step);
        },

        decrement() {
            this.set(this.current() - this.step);
        },

        // Arabic keyboards type ٠-٩ and "٫"; store Western digits.
        normalize() {
            const map = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '٫': '.', '،': '.', ',': '.' };
            const normalized = this.input.value.replace(/[٠-٩٫،,]/g, (char) => map[char]);
            if (normalized !== this.input.value) {
                this.input.value = normalized;
                this.input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
    }));
});
