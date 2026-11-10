<?php

namespace App\View;

/**
 * Flash a toast that the layout shows after the next full page load / redirect.
 *
 *   Toast::success(__('foods.saved'));  return redirect()->route('foods.index');
 *
 * Inside a Livewire component without a redirect, use the App\Livewire\Concerns\Toasts trait instead.
 */
class Toast
{
    public const TYPES = ['success', 'error', 'warning', 'info'];

    public static function flash(string $message, string $type = 'success'): void
    {
        $toasts = session()->get('toasts', []);
        $toasts[] = ['message' => $message, 'type' => in_array($type, self::TYPES, true) ? $type : 'info'];

        session()->flash('toasts', $toasts);
    }

    public static function success(string $message): void
    {
        self::flash($message, 'success');
    }

    public static function error(string $message): void
    {
        self::flash($message, 'error');
    }

    public static function warning(string $message): void
    {
        self::flash($message, 'warning');
    }

    public static function info(string $message): void
    {
        self::flash($message, 'info');
    }

    /**
     * Toasts waiting in the session (including Laravel's conventional `status` flash).
     *
     * @return list<array{message: string, type: string}>
     */
    public static function pending(): array
    {
        $toasts = session()->get('toasts', []);

        if (is_string($status = session()->get('status'))) {
            $toasts[] = ['message' => $status, 'type' => 'success'];
        }

        return array_values($toasts);
    }
}
