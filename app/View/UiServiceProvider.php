<?php

namespace App\View;

use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * UI wiring: Blade formatting directives and the Carbon locale mapping.
 * Registered from bootstrap/app.php.
 */
class UiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // @num($value, $decimals = 0, $fixed = false) — Western digits in both locales.
        Blade::directive('num', fn (string $expression) => "<?php echo e(\\App\\View\\Format::number($expression)); ?>");

        // @date($date, $format = 'medium') — localized, Western digits.
        Blade::directive('date', fn (string $expression) => "<?php echo e(\\App\\View\\Format::date($expression)); ?>");

        // Keep Carbon on the Levantine Arabic locale whenever the app locale changes
        // (SetLocale middleware, Livewire's locale re-hydration, tests).
        Event::listen(LocaleUpdated::class, function (LocaleUpdated $event): void {
            Carbon::setLocale(Format::carbonLocale($event->locale));
        });

        Carbon::setLocale(Format::carbonLocale());
    }
}
