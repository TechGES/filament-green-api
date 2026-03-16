<?php

namespace Ges\FilamentGreenApi\Tests\Feature;

use Ges\FilamentGreenApi\Filament\Pages\GreenApiInbox;
use Ges\FilamentGreenApi\Tests\Fixtures\User;
use Ges\FilamentGreenApi\Tests\TestCase;
use Ges\LaravelGreenApi\Models\GreenApiConversation;
use Ges\LaravelGreenApi\Models\GreenApiMessage;
use Ges\LaravelGreenApi\Services\GreenApiInboxService;
use Ges\LaravelGreenApi\Services\GreenApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RuntimeException;

class GreenApiInboxPageTest extends TestCase
{
    public function test_inbox_page_allows_authenticated_user_without_ability(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');

        $this->actingAs($user);

        $this->assertTrue(GreenApiInbox::canAccess());
    }

    public function test_inbox_page_checks_the_configured_ability(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');

        $this->actingAs($user);
        config()->set('green_api_filament.whatsapp_view_ability', 'view-green-api-whatsapp');

        $this->assertFalse(GreenApiInbox::canAccess());

        Gate::define('view-green-api-whatsapp', fn (User $user): bool => $user->email === 'jane.doe@example.test');

        $this->assertTrue(GreenApiInbox::canAccess());
    }

    public function test_inbox_page_falls_back_to_the_legacy_shared_ability(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');

        $this->actingAs($user);
        config()->set('green_api_filament.view_ability', 'view-green-api');

        $this->assertFalse(GreenApiInbox::canAccess());

        Gate::define('view-green-api', fn (User $user): bool => $user->email === 'jane.doe@example.test');

        $this->assertTrue(GreenApiInbox::canAccess());
    }

    public function test_send_requires_a_message_or_attachment(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');

        $this->actingAs($user);

        Livewire::test(GreenApiInbox::class)
            ->call('send')
            ->assertNotified();

        $this->assertSame(0, GreenApiMessage::query()->count());
    }

    public function test_send_text_message_creates_an_outgoing_message_and_clears_the_input(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');
        $this->createConversation($user, '33612345678@c.us');

        $this->actingAs($user);

        $this->app->instance(GreenApiService::class, new class extends GreenApiService
        {
            public function sendMessage(string $chatId, string $message): array
            {
                return [
                    'idMessage' => 'msg-outgoing-1',
                    'statusMessage' => 'sent',
                ];
            }
        });

        $this->app->forgetInstance(GreenApiInboxService::class);

        Livewire::test(GreenApiInbox::class)
            ->set('messageBody', 'Bonjour')
            ->call('send')
            ->assertSet('messageBody', '')
            ->assertSet('attachment', null)
            ->assertNotified();

        $this->assertDatabaseHas(GreenApiMessage::class, [
            'remote_message_id' => 'msg-outgoing-1',
            'body' => 'Bonjour',
            'direction' => 'outgoing_api',
        ]);

        $conversation = GreenApiConversation::query()->first();

        $this->assertNotNull($conversation);
        $this->assertSame('Bonjour', $conversation->last_message_preview);
        $this->assertSame(0, $conversation->unread_count);
    }

    public function test_select_contact_marks_the_conversation_as_read(): void
    {
        $firstUser = $this->createContact('Jane Doe', '+33 6 12 34 56 78');
        $secondUser = $this->createContact('John Doe', '+33 6 98 76 54 32');

        GreenApiConversation::query()->create([
            'contact_id' => (string) $firstUser->getKey(),
            'chat_id' => '33612345678@c.us',
            'phone' => '33612345678',
            'unread_count' => 0,
        ]);

        GreenApiConversation::query()->create([
            'contact_id' => (string) $secondUser->getKey(),
            'chat_id' => '33698765432@c.us',
            'phone' => '33698765432',
            'unread_count' => 3,
        ]);

        $this->actingAs($firstUser);

        Livewire::test(GreenApiInbox::class)
            ->call('selectContact', (string) $secondUser->getKey())
            ->assertSet('activeContactId', (string) $secondUser->getKey());

        $this->assertSame(
            0,
            GreenApiConversation::query()
                ->where('contact_id', (string) $secondUser->getKey())
                ->value('unread_count')
        );
    }

    public function test_send_handles_service_failures_with_a_notification(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');
        $this->createConversation($user, '33612345678@c.us');

        $this->actingAs($user);

        $this->app->instance(GreenApiService::class, new class extends GreenApiService
        {
            public function sendMessage(string $chatId, string $message): array
            {
                throw new RuntimeException('Send failed.');
            }
        });

        $this->app->forgetInstance(GreenApiInboxService::class);

        Livewire::test(GreenApiInbox::class)
            ->set('messageBody', 'Bonjour')
            ->call('send')
            ->assertSet('messageBody', 'Bonjour')
            ->assertNotified();

        $this->assertSame(0, GreenApiMessage::query()->count());
    }

    public function test_sidebar_lists_only_existing_conversations(): void
    {
        $firstUser = $this->createContact('Jane Doe', '+33 6 12 34 56 78');
        $secondUser = $this->createContact('John Doe', '+33 6 98 76 54 32');

        $this->createConversation($firstUser, '33612345678@c.us', 'Bonjour');

        $this->actingAs($firstUser);

        /** @var \Illuminate\Support\Collection<int, GreenApiConversation> $conversations */
        $conversations = Livewire::test(GreenApiInbox::class)->instance()->conversations();

        $this->assertCount(1, $conversations);
        $this->assertSame((string) $firstUser->getKey(), (string) $conversations->first()?->contact_id);
        $this->assertNotSame((string) $secondUser->getKey(), (string) $conversations->first()?->contact_id);
    }

    public function test_thread_messages_are_lazily_loaded_and_can_load_older_entries(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');
        $conversation = $this->createConversation($user, '33612345678@c.us', 'Message 39');

        foreach (range(0, 39) as $index) {
            GreenApiMessage::query()->forceCreate([
                'green_api_conversation_id' => $conversation->id,
                'remote_message_id' => 'msg-'.$index,
                'remote_chat_id' => $conversation->chat_id,
                'direction' => $index % 2 === 0 ? 'incoming' : 'outgoing_api',
                'webhook_type' => 'incomingMessageReceived',
                'message_type' => 'textMessage',
                'body' => 'Message '.$index,
                'status' => 'sent',
                'sent_at' => Carbon::parse('2026-03-15 08:00:00')->addMinutes($index),
                'created_at' => Carbon::parse('2026-03-15 08:00:00')->addMinutes($index),
                'updated_at' => Carbon::parse('2026-03-15 08:00:00')->addMinutes($index),
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(GreenApiInbox::class);

        /** @var \Illuminate\Support\Collection<int, GreenApiMessage> $messages */
        $messages = $component->instance()->threadMessages();

        $this->assertCount(30, $messages);
        $this->assertSame('Message 10', $messages->first()?->body);
        $this->assertSame('Message 39', $messages->last()?->body);

        $component->call('loadOlderMessages');

        /** @var \Illuminate\Support\Collection<int, GreenApiMessage> $olderMessages */
        $olderMessages = $component->instance()->threadMessages();

        $this->assertCount(40, $olderMessages);
        $this->assertSame('Message 0', $olderMessages->first()?->body);
        $this->assertSame('Message 39', $olderMessages->last()?->body);
    }

    public function test_new_conversation_action_selects_contact_without_validation_type_error(): void
    {
        $user = $this->createContact('Jane Doe', '+33 6 12 34 56 78');

        $this->actingAs($user);

        Livewire::test(GreenApiInbox::class)
            ->callAction('newConversation', data: [
                'contact_id' => (string) $user->getKey(),
            ])
            ->assertSet('activeContactId', (string) $user->getKey())
            ->assertNotified();

        $this->assertDatabaseHas(GreenApiConversation::class, [
            'contact_id' => (string) $user->getKey(),
            'chat_id' => '33612345678@c.us',
        ]);
    }

    private function createContact(string $name, string $phone): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => str($name)->lower()->replace(' ', '.')->append('@example.test')->toString(),
            'phone' => $phone,
        ]);
    }

    private function createConversation(User $user, string $chatId, ?string $preview = null): GreenApiConversation
    {
        return GreenApiConversation::query()->create([
            'contact_id' => (string) $user->getKey(),
            'chat_id' => $chatId,
            'phone' => preg_replace('/\D+/', '', $user->phone ?? '') ?: null,
            'last_message_preview' => $preview,
            'last_message_at' => Carbon::parse('2026-03-15 09:00:00'),
            'unread_count' => 0,
        ]);
    }
}
