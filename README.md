# Filament Green API

Filament v3 panel plugin for configuring and using `ges/laravel-green-api` from the Filament admin.

## Installation

```bash
composer require ges/filament-green-api
```

Publish the plugin config:

```bash
php artisan vendor:publish --tag="filament-green-api-config"
```

If you have not already installed the base Green API package, run its installer:

```bash
php artisan green-api:install
```

Publish Filament assets so the plugin stylesheet is available in your panel:

```bash
php artisan filament:assets
```

If you prefer publishing manually, use the dependency package tags:

```bash
php artisan vendor:publish --tag="laravel-green-api-config"
php artisan vendor:publish --tag="laravel-green-api-migrations"
php artisan migrate
```

## Register The Plugin

Register the plugin in your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Ges\FilamentGreenApi\GreenApiPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                GreenApiPlugin::make(),
            ]);
    }
}
```

## Required Green API Configuration

This plugin depends on `ges/laravel-green-api` for transport, webhook handling, models, and contact resolution.

At minimum, make sure these values are configured in `config/green_api.php` or your environment:

- `api_url`
- `media_url`
- `instance_id`
- `token`
- `contact_model`
- `contact_phone_attribute`

If your contact model does not use a `phone` column, update `contact_phone_attribute` before opening the inbox page.

## Plugin Configuration

The plugin publishes `config/green_api_filament.php` with:

```php
return [
    'config_view_ability' => null,
    'whatsapp_view_ability' => null,
    'view_ability' => null,
];
```

Set `config_view_ability` to restrict the configuration page and `whatsapp_view_ability` to restrict the WhatsApp inbox page.
The legacy `view_ability` key is still supported as a shared fallback for existing installs.
