<?php

namespace Ges\FilamentGreenApi\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Ges\LaravelGreenApi\Models\GreenApiConfig;
use Ges\LaravelGreenApi\Models\GreenApiConversation;
use Ges\LaravelGreenApi\Services\GreenApiInboxService;
use Ges\LaravelGreenApi\Support\GreenApiContactManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class GreenApiInbox extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'WhatsApp';

    protected static string $view = 'green-api::filament.pages.green-api-inbox';

    public int|string|null $activeContactId = null;

    public string $search = '';

    public string $messageBody = '';

    public ?TemporaryUploadedFile $attachment = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $ability = config('green_api_filament.view_ability');

        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return $user->can($ability);
    }

    public function mount(GreenApiInboxService $greenApiInboxService): void
    {
        $firstContact = $greenApiInboxService->contacts()->first();
        $this->activeContactId = $firstContact?->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newConversation')
                ->label('Nouveau message')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('contact_id')
                        ->label('Contact')
                        ->searchable()
                        ->required()
                        ->options(app(GreenApiContactManager::class)->options()),
                ])
                ->action(function (array $data): void {
                    $contact = app(GreenApiContactManager::class)->findOrFail($data['contact_id']);

                    app(GreenApiInboxService::class)->ensureConversationForContact($contact);
                    $this->activeContactId = $contact->getKey();

                    Notification::make()
                        ->title('Conversation prete')
                        ->body("La conversation avec {$this->contactLabel($contact)} est selectionnee.")
                        ->success()
                        ->send();
                }),
            Action::make('markAsRead')
                ->label('Marquer comme lu')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => $this->activeConversation()?->unread_count > 0)
                ->action('markConversationAsRead'),
        ];
    }

    public function selectContact(int|string $contactId): void
    {
        $this->activeContactId = $contactId;
        $this->markConversationAsRead();
    }

    public function send(): void
    {
        $contact = $this->activeContact();

        if ($contact === null) {
            Notification::make()
                ->title('Aucun contact selectionne')
                ->danger()
                ->send();

            return;
        }

        if (trim($this->messageBody) === '' && $this->attachment === null) {
            Notification::make()
                ->title('Ajoutez un message ou un fichier')
                ->warning()
                ->send();

            return;
        }

        try {
            if ($this->attachment !== null) {
                app(GreenApiInboxService::class)->sendFileMessage(
                    $contact,
                    $this->attachment,
                    trim($this->messageBody) !== '' ? trim($this->messageBody) : null,
                    $this->attachment->getClientOriginalName()
                );
            } else {
                app(GreenApiInboxService::class)->sendTextMessage($contact, trim($this->messageBody));
            }
        } catch (\Throwable $throwable) {
            Notification::make()
                ->title('Envoi impossible')
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->messageBody = '';
        $this->attachment = null;

        Notification::make()
            ->title('Message envoye')
            ->success()
            ->send();
    }

    public function refreshThread(): void {}

    public function markConversationAsRead(): void
    {
        $conversation = $this->activeConversation();

        if ($conversation === null || $conversation->unread_count === 0) {
            return;
        }

        $conversation->update(['unread_count' => 0]);
    }

    /**
     * @return Collection<int, Model>
     */
    public function contacts(): Collection
    {
        return app(GreenApiInboxService::class)->contacts($this->search);
    }

    public function activeContact(): ?Model
    {
        if ($this->activeContactId === null) {
            return null;
        }

        return app(GreenApiInboxService::class)->contact($this->activeContactId);
    }

    public function activeConversation(): ?GreenApiConversation
    {
        return $this->activeContact()?->greenApiConversation;
    }

    public function currentConfig(): GreenApiConfig
    {
        return app(GreenApiInboxService::class)->currentConfig();
    }

    public function contactLabel(Model $contact): string
    {
        return app(GreenApiContactManager::class)->label($contact);
    }

    public function contactPhone(Model $contact): string
    {
        return app(GreenApiContactManager::class)->phone($contact);
    }

    public function contactInitials(Model $contact): string
    {
        return app(GreenApiContactManager::class)->initials($contact);
    }

    public function contactIsActive(Model $contact): bool
    {
        return (string) $contact->getKey() === (string) $this->activeContactId;
    }

    public function messages(): Collection
    {
        return $this->activeConversation()?->messages ?? collect();
    }
}
