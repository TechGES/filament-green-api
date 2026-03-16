<?php

namespace Ges\FilamentGreenApi\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Ges\LaravelGreenApi\Models\GreenApiConfig;
use Ges\LaravelGreenApi\Models\GreenApiConversation;
use Ges\LaravelGreenApi\Models\GreenApiMessage;
use Ges\LaravelGreenApi\Services\GreenApiInboxService;
use Ges\LaravelGreenApi\Support\GreenApiContactManager;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class GreenApiInbox extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'WhatsApp';

    protected static string $view = 'green-api::filament.pages.green-api-inbox';

    public int|string|null $activeContactId = null;

    public string $search = '';

    public string $messageSearch = '';

    public string $messageBody = '';

    public ?TemporaryUploadedFile $attachment = null;

    public int $conversationLimit = 25;

    public int $messageChunkSize = 30;

    public int $messageLimit = 30;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $ability = config('green_api_filament.pages.whatsapp.view_ability');

        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return $user->can($ability);
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        $icon = config('green_api_filament.pages.whatsapp.navigation_icon');

        return is_string($icon) && $icon !== '' ? $icon : null;
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('green_api_filament.pages.whatsapp.navigation_group');

        return is_string($group) && $group !== '' ? $group : null;
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('green_api_filament.pages.whatsapp.navigation_sort');

        if (is_int($sort)) {
            return $sort;
        }

        if (is_numeric($sort)) {
            return (int) $sort;
        }

        return null;
    }

    public function mount(): void
    {
        $this->messageLimit = $this->messageChunkSize;
        $this->activeContactId = $this->initialConversation()?->contact_id;
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
                        ->placeholder('Rechercher un contact')
                        ->searchPrompt('Saisissez un nom, email ou numero')
                        ->noSearchResultsMessage('Aucun contact trouve')
                        ->getSearchResultsUsing(fn (string $search): array => $this->newConversationContactOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => $this->newConversationContactLabel($value)),
                ])
                ->action(function (array $data): void {
                    $contact = app(GreenApiContactManager::class)->findOrFail($data['contact_id']);

                    app(GreenApiInboxService::class)->ensureConversationForContact($contact);
                    $this->activateContact($contact->getKey());

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
        $this->activateContact($contactId);
        $this->markConversationAsRead();
    }

    public function loadMoreConversations(): void
    {
        $this->conversationLimit += 25;
    }

    public function loadOlderMessages(): void
    {
        if (! $this->hasMoreMessages()) {
            return;
        }

        $this->messageLimit += $this->messageChunkSize;
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

    public function updatedSearch(): void
    {
        $this->conversationLimit = 25;
    }

    public function updatedMessageSearch(): void
    {
        $this->messageLimit = $this->messageChunkSize;
    }

    public function markConversationAsRead(): void
    {
        $conversation = $this->activeConversation();

        if ($conversation === null || $conversation->unread_count === 0) {
            return;
        }

        $conversation->update(['unread_count' => 0]);
    }

    /**
     * @return Collection<int, GreenApiConversation>
     */
    public function conversations(): Collection
    {
        $query = $this->conversationQuery();

        return $query
            ->limit($this->conversationLimit)
            ->get()
            ->filter(fn (GreenApiConversation $conversation): bool => $conversation->contact !== null)
            ->values();
    }

    public function activeContact(): ?Model
    {
        return $this->activeConversation()?->contact;
    }

    public function activeConversation(): ?GreenApiConversation
    {
        if ($this->activeContactId === null) {
            return null;
        }

        return GreenApiConversation::query()
            ->with('contact')
            ->where('contact_id', (string) $this->activeContactId)
            ->first();
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

    /**
     * @return Collection<int, GreenApiMessage>
     */
    public function threadMessages(): Collection
    {
        $query = $this->messageQuery();

        if ($query === null) {
            return collect();
        }

        return $query
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->limit($this->messageLimit)
            ->get()
            ->reverse()
            ->values();
    }

    public function hasMoreConversations(): bool
    {
        return $this->conversationQuery()->count() > $this->conversationLimit;
    }

    public function hasMoreMessages(): bool
    {
        $query = $this->messageQuery();

        return $query !== null && $query->count() > $this->messageLimit;
    }

    /**
     * @return array<string, string>
     */
    public function newConversationContactOptions(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $manager = app(GreenApiContactManager::class);
        $modelClass = $manager->modelClass();
        $phoneAttribute = $manager->phoneAttribute();
        $searchAttributes = $manager->searchAttributes();
        $searchAttributes = $searchAttributes !== [] ? $searchAttributes : [$phoneAttribute];
        $digitsNeedle = preg_replace('/\D+/', '', $search) ?: '';

        return $modelClass::query()
            ->whereNotNull($phoneAttribute)
            ->where(function (Builder $query) use ($digitsNeedle, $phoneAttribute, $search, $searchAttributes): void {
                foreach ($searchAttributes as $index => $attribute) {
                    if ($index === 0) {
                        $query->where($attribute, 'like', '%'.$search.'%');

                        continue;
                    }

                    $query->orWhere($attribute, 'like', '%'.$search.'%');
                }

                if ($digitsNeedle !== '') {
                    $query->orWhere($phoneAttribute, 'like', '%'.$digitsNeedle.'%');
                }
            })
            ->limit(50)
            ->get()
            ->sortBy(fn (Model $contact): string => Str::lower($this->contactLabel($contact)))
            ->mapWithKeys(fn (Model $contact): array => [
                (string) $contact->getKey() => "{$this->contactLabel($contact)} ({$this->contactPhone($contact)})",
            ])
            ->all();
    }

    public function newConversationContactLabel(mixed $value): ?string
    {
        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        $contact = app(GreenApiContactManager::class)->find((string) $value);

        if ($contact === null) {
            return null;
        }

        return "{$this->contactLabel($contact)} ({$this->contactPhone($contact)})";
    }

    private function activateContact(int|string $contactId): void
    {
        $this->activeContactId = $contactId;
        $this->messageSearch = '';
        $this->messageLimit = $this->messageChunkSize;
    }

    private function initialConversation(): ?GreenApiConversation
    {
        return GreenApiConversation::query()
            ->with('contact')
            ->whereNotNull('contact_id')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function conversationQuery(): Builder
    {
        $manager = app(GreenApiContactManager::class);
        $phoneAttribute = $manager->phoneAttribute();
        $searchAttributes = $manager->searchAttributes();
        $searchAttributes = $searchAttributes !== [] ? $searchAttributes : [$phoneAttribute];
        $needle = trim($this->search);
        $digitsNeedle = preg_replace('/\D+/', '', $needle) ?: '';

        $query = GreenApiConversation::query()
            ->with('contact')
            ->whereNotNull('contact_id')
            ->whereHas('contact', fn (Builder $query): Builder => $query->whereNotNull($phoneAttribute))
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        if ($needle === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($digitsNeedle, $needle, $phoneAttribute, $searchAttributes): void {
            $query->where('last_message_preview', 'like', '%'.$needle.'%')
                ->orWhere('phone', 'like', '%'.$needle.'%');

            if ($digitsNeedle !== '') {
                $query->orWhere('phone', 'like', '%'.$digitsNeedle.'%');
            }

            $query->orWhereHas('contact', function (Builder $contactQuery) use ($digitsNeedle, $needle, $phoneAttribute, $searchAttributes): void {
                foreach ($searchAttributes as $index => $attribute) {
                    if ($index === 0) {
                        $contactQuery->where($attribute, 'like', '%'.$needle.'%');

                        continue;
                    }

                    $contactQuery->orWhere($attribute, 'like', '%'.$needle.'%');
                }

                if ($digitsNeedle !== '') {
                    $contactQuery->orWhere($phoneAttribute, 'like', '%'.$digitsNeedle.'%');
                }
            });
        });
    }

    private function messageQuery(): ?Builder
    {
        $conversation = $this->activeConversation();

        if ($conversation === null) {
            return null;
        }

        $query = GreenApiMessage::query()
            ->where('green_api_conversation_id', $conversation->getKey());

        $needle = trim($this->messageSearch);

        if ($needle === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($needle): void {
            $query->where('body', 'like', '%'.$needle.'%')
                ->orWhere('caption', 'like', '%'.$needle.'%')
                ->orWhere('file_name', 'like', '%'.$needle.'%');
        });
    }
}
