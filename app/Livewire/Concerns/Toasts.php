<?php

namespace App\Livewire\Concerns;

use App\View\Toast;

/**
 * Toast helpers for Livewire components.
 *
 *   $this->toast(__('log.saved'));                 // stays on the page: browser event picked up by <x-toaster>
 *   $this->toast(__('log.failed'), 'error');
 *   $this->flashToast(__('foods.saved'));          // before $this->redirect(...): shown on the next page
 */
trait Toasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    protected function flashToast(string $message, string $type = 'success'): void
    {
        Toast::flash($message, $type);
    }
}
