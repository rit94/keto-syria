<?php

namespace App\View;

use Illuminate\Support\Facades\Route;

/**
 * Primary navigation. Items only appear once their route exists, so feature
 * areas light up as they are built without anyone editing the layout.
 */
class Nav
{
    /** key => [route name, icon, extra route patterns that also mark the item active] */
    private const ITEMS = [
        'today' => ['today', 'flame', ['log.*']],
        'foods' => ['foods.index', 'search', ['foods.*']],
        'coach' => ['coach', 'chat', ['coach.*']],
        'recipes' => ['recipes.index', 'book', ['recipes.*']],
        'plans' => ['plans.index', 'calendar', ['plans.*']],
        'progress' => ['progress', 'chart', ['progress.*']],
        'profile' => ['profile', 'user', ['profile.*']],
    ];

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, route: string, url: string, icon: string, label: string, active: bool}>
     */
    public static function items(array $keys): array
    {
        $items = [];

        foreach ($keys as $key) {
            if ($item = self::item($key)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array{key: string, route: string, url: string, icon: string, label: string, active: bool}|null */
    public static function item(string $key): ?array
    {
        if (! isset(self::ITEMS[$key])) {
            return null;
        }

        [$route, $icon, $patterns] = self::ITEMS[$key];

        if (! Route::has($route)) {
            return null;
        }

        return [
            'key' => $key,
            'route' => $route,
            'url' => route($route),
            'icon' => $icon,
            'label' => __('nav.'.$key),
            'active' => request()->routeIs($route, ...$patterns),
        ];
    }

    /** Desktop side rail: everything. */
    public static function rail(): array
    {
        return self::items(['today', 'foods', 'recipes', 'plans', 'progress', 'coach', 'profile']);
    }

    /**
     * Mobile tab bar: two items, the Coach centre action, two items.
     * Slot 4 is Plans (falls back to Recipes), slot 5 is Progress (falls back to Profile).
     *
     * @return array{start: list<array>, coach: ?array, end: list<array>}
     */
    public static function tabs(): array
    {
        return [
            'start' => self::items(['today', 'foods']),
            'coach' => self::item('coach'),
            'end' => array_values(array_filter([
                self::item('plans') ?? self::item('recipes'),
                self::item('progress') ?? self::item('profile'),
            ])),
        ];
    }

    /** Items the mobile tab bar has no room for; listed in the account menu. */
    public static function overflow(): array
    {
        $shown = array_column([...self::tabs()['start'], ...self::tabs()['end']], 'key');

        return array_values(array_filter(
            self::items(['recipes', 'plans', 'progress', 'profile']),
            fn (array $item) => ! in_array($item['key'], $shown, true),
        ));
    }

    public static function smartLogUrl(): ?string
    {
        return Route::has('log.smart') ? route('log.smart') : null;
    }
}
