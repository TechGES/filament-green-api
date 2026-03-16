<?php

namespace Ges\FilamentGreenApi\Tests\Feature;

use Filament\Notifications\Notification;
use Ges\FilamentGreenApi\Filament\Pages\GreenApiSettings;
use Ges\FilamentGreenApi\Tests\Fixtures\User;
use Ges\FilamentGreenApi\Tests\TestCase;
use Ges\LaravelGreenApi\Models\GreenApiConfig;
use Ges\LaravelGreenApi\Services\GreenApiService;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RuntimeException;

class GreenApiSettingsPageTest extends TestCase
{
    public function test_settings_page_allows_authenticated_user_without_ability(): void
    {
        $user = User::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

        $this->actingAs($user);

        $this->assertTrue(GreenApiSettings::canAccess());
    }

    public function test_settings_page_checks_the_configured_ability(): void
    {
        $user = User::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

        $this->actingAs($user);
        config()->set('green_api_filament.config_view_ability', 'view-green-api-settings');

        $this->assertFalse(GreenApiSettings::canAccess());

        Gate::define('view-green-api-settings', fn (User $user): bool => $user->email === 'jane@example.test');

        $this->assertTrue(GreenApiSettings::canAccess());
    }

    public function test_settings_page_falls_back_to_the_legacy_shared_ability(): void
    {
        $user = User::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

        $this->actingAs($user);
        config()->set('green_api_filament.view_ability', 'view-green-api');

        $this->assertFalse(GreenApiSettings::canAccess());

        Gate::define('view-green-api', fn (User $user): bool => $user->email === 'jane@example.test');

        $this->assertTrue(GreenApiSettings::canAccess());
    }

    public function test_settings_page_can_save_without_a_webhook_authorization_header(): void
    {
        $user = User::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

        $this->actingAs($user);

        Livewire::test(GreenApiSettings::class)
            ->fillForm([
                'api_url' => 'https://api.example.test',
                'media_url' => 'https://media.example.test',
                'instance_id' => '123456',
                'token' => 'secret',
                'test_chat_id' => '33612345678',
                'webhook_url' => '',
                'webhook_authorization_header' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(GreenApiConfig::class, [
            'instance_id' => '123456',
            'webhook_authorization_header' => null,
        ]);
    }

    public function test_check_connection_handles_service_exceptions_with_a_notification(): void
    {
        $user = User::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

        $this->actingAs($user);

        $this->app->instance(GreenApiService::class, new class extends GreenApiService
        {
            public function getStateInstance(): array
            {
                throw new RuntimeException('Connection failed.');
            }
        });

        Livewire::test(GreenApiSettings::class)
            ->fillForm([
                'api_url' => 'https://api.example.test',
                'media_url' => 'https://media.example.test',
                'instance_id' => '123456',
                'token' => 'secret',
                'test_chat_id' => '33612345678',
                'webhook_url' => '',
                'webhook_authorization_header' => '',
            ])
            ->call('checkConnection')
            ->assertNotified();

        Notification::assertNotified();
    }

    public function test_sync_webhook_handles_service_exceptions_with_a_notification(): void
    {
        $user = User::query()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

        $this->actingAs($user);

        $this->app->instance(GreenApiService::class, new class extends GreenApiService
        {
            public function configureWebhook(?string $webhookUrl = null, ?string $webhookAuthorizationHeader = null): array
            {
                throw new RuntimeException('Webhook failed.');
            }
        });

        Livewire::test(GreenApiSettings::class)
            ->fillForm([
                'api_url' => 'https://api.example.test',
                'media_url' => 'https://media.example.test',
                'instance_id' => '123456',
                'token' => 'secret',
                'test_chat_id' => '33612345678',
                'webhook_url' => '',
                'webhook_authorization_header' => '',
            ])
            ->call('syncWebhook')
            ->assertNotified();

        Notification::assertNotified();
    }
}
