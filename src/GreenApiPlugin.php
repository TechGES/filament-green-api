<?php

namespace Ges\FilamentGreenApi;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Ges\FilamentGreenApi\Filament\Pages\GreenApiInbox;
use Ges\FilamentGreenApi\Filament\Pages\GreenApiSettings;

class GreenApiPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'green-api';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            GreenApiSettings::class,
            GreenApiInbox::class,
        ])->assets([
            Css::make('styles', __DIR__ . '/../resources/dist/filament-green-api.css'),
        ], 'ges/filament-green-api');
    }

    public function boot(Panel $panel): void {}
}
