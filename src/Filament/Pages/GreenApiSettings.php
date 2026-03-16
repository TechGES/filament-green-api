<?php

namespace Ges\FilamentGreenApi\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Ges\LaravelGreenApi\Models\GreenApiConfig;
use Ges\LaravelGreenApi\Services\GreenApiInboxService;
use Ges\LaravelGreenApi\Services\GreenApiService;
use Illuminate\Contracts\Support\Htmlable;

class GreenApiSettings extends Page
{
    use InteractsWithForms;

    protected static ?string $title = 'Green API';

    protected static string $view = 'green-api::filament.pages.green-api-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $ability = config('green_api_filament.pages.settings.view_ability');

        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return $user->can($ability);
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        $icon = config('green_api_filament.pages.settings.navigation_icon');

        return is_string($icon) && $icon !== '' ? $icon : null;
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('green_api_filament.pages.settings.navigation_group');

        return is_string($group) && $group !== '' ? $group : null;
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('green_api_filament.pages.settings.navigation_sort');

        if (is_int($sort)) {
            return $sort;
        }

        if (is_numeric($sort)) {
            return (int) $sort;
        }

        return null;
    }

    public function mount(GreenApiInboxService $greenApiInboxService): void
    {
        $this->form->fill($greenApiInboxService->currentConfig()->only($this->formFields()));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Connexion')
                    ->description('Parametres de l\'instance Green API utilises par le CRM et le webhook.')
                    ->schema([
                        TextInput::make('api_url')
                            ->label('API URL')
                            ->required()
                            ->url(),
                        TextInput::make('media_url')
                            ->label('Media URL')
                            ->required()
                            ->url(),
                        TextInput::make('instance_id')
                            ->label('Instance ID')
                            ->required(),
                        TextInput::make('token')
                            ->label('Token')
                            ->password()
                            ->revealable()
                            ->required(),
                        TextInput::make('test_chat_id')
                            ->label('Numero de test')
                            ->helperText('Format attendu : indicatif + numero sans le 0 initial, sans signe +. Exemple : 33766300740')
                            ->required(),
                    ])->columns(2),
                Section::make('Webhook')
                    ->description('Ces valeurs sont envoyees a Green API via setSettings.')
                    ->schema([
                        TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->helperText('Laissez vide pour utiliser automatiquement la route API du package.'),
                        TextInput::make('webhook_authorization_header')
                            ->label('Authorization Header')
                            ->helperText('Optionnel. Exemple : Bearer mon-secret-webhook'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkConnection')
                ->label('Verifier la connexion')
                ->icon('heroicon-o-signal')
                ->action('checkConnection'),
            Action::make('syncWebhook')
                ->label('Configurer le webhook')
                ->icon('heroicon-o-link')
                ->requiresConfirmation()
                ->action('syncWebhook'),
            Action::make('save')
                ->label('Enregistrer')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $config = $this->persistData();

        Notification::make()
            ->title('Configuration Green API enregistree')
            ->body("Instance {$config->instance_id} mise a jour.")
            ->success()
            ->send();
    }

    public function checkConnection(): void
    {
        try {
            $this->persistData();

            $state = app(GreenApiService::class)->getStateInstance();
            $instanceState = $state['stateInstance'] ?? $state['statusInstance'] ?? $state['status'] ?? 'unknown';

            app(GreenApiInboxService::class)->markConnectionChecked(is_string($instanceState) ? $instanceState : null);

            Notification::make()
                ->title('Connexion Green API verifiee')
                ->body('Etat courant : '.(is_string($instanceState) ? $instanceState : 'unknown'))
                ->success()
                ->send();
        } catch (\Throwable $throwable) {
            Notification::make()
                ->title('Verification impossible')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncWebhook(): void
    {
        try {
            $config = $this->persistData();

            app(GreenApiService::class)->configureWebhook(
                $config->webhook_url,
                $config->webhook_authorization_header
            );

            app(GreenApiInboxService::class)->markWebhookSynced();

            Notification::make()
                ->title('Webhook Green API configure')
                ->body('Les notifications WhatsApp sont maintenant synchronisees avec le CRM.')
                ->success()
                ->send();
        } catch (\Throwable $throwable) {
            Notification::make()
                ->title('Configuration du webhook impossible')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getConfigRecord(): GreenApiConfig
    {
        return app(GreenApiInboxService::class)->currentConfig();
    }

    private function persistData(): GreenApiConfig
    {
        $config = app(GreenApiInboxService::class)->saveConfig($this->form->getState());
        $this->form->fill($config->only($this->formFields()));

        return $config;
    }

    /**
     * @return list<string>
     */
    private function formFields(): array
    {
        return [
            'api_url',
            'media_url',
            'instance_id',
            'token',
            'test_chat_id',
            'webhook_url',
            'webhook_authorization_header',
        ];
    }
}
