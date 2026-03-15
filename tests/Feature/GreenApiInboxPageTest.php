<?php

namespace Ges\FilamentGreenApi\Tests\Feature;

use Ges\FilamentGreenApi\Filament\Pages\GreenApiInbox;
use Ges\FilamentGreenApi\Tests\Fixtures\User;
use Ges\FilamentGreenApi\Tests\TestCase;
use Ges\LaravelGreenApi\Models\GreenApiConversation;
use Ges\LaravelGreenApi\Models\GreenApiMessage;
use Ges\LaravelGreenApi\Services\GreenApiInboxService;
use Ges\LaravelGreenApi\Services\GreenApiService;
use Livewire\Livewire;
use RuntimeException;

class GreenApiInboxPageTest extends TestCase
{
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

    private function createContact(string $name, string $phone): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => str($name)->lower()->replace(' ', '.')->append('@example.test')->toString(),
            'phone' => $phone,
        ]);
    }
}
