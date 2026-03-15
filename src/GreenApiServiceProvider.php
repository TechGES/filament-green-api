<?php

namespace Ges\FilamentGreenApi;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GreenApiServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-green-api';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile('green_api_filament')
            ->hasViews('green-api')
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command): void {
                        $command->newLine();
                        $command->warn('This plugin depends on ges/laravel-green-api.');
                        $command->line('Run <fg=yellow>php artisan laravel-green-api:install</> to publish the base package config and migrations.');
                        $command->line('Run <fg=yellow>php artisan filament:assets</> after installation or upgrades to publish the plugin styles.');
                        $command->line('Update <fg=yellow>green_api.contact_model</> and <fg=yellow>green_api.contact_phone_attribute</> before opening the inbox if your contacts do not use the default User/phone setup.');
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(GreenApiPlugin::class, function (): GreenApiPlugin {
            return new GreenApiPlugin;
        });
    }
}
