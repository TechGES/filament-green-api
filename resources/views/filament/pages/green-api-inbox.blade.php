<x-filament-panels::page>
    @php($contacts = $this->contacts())
    @php($activeContact = $this->activeContact())
    @php($activeConversation = $this->activeConversation())
    @php($messages = $this->messages())
    @php($config = $this->currentConfig())

    <div class="ga-plugin ga-inbox-layout">
        <aside class="ga-sidebar">
            <div class="ga-sidebar-search">
                <label class="sr-only" for="green-api-contact-search">Recherche</label>
                <input
                    id="green-api-contact-search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher un contact"
                    class="ga-search-input"
                />
            </div>

            <div class="ga-contact-list">
                @forelse ($contacts as $contact)
                    @php($conversation = $contact->greenApiConversation)
                    <button
                        wire:key="green-api-contact-{{ $contact->getKey() }}"
                        type="button"
                        wire:click="selectContact('{{ $contact->getKey() }}')"
                        class="ga-contact-button {{ $this->contactIsActive($contact) ? 'is-active' : '' }}"
                    >
                        <div class="ga-contact-avatar">
                            {{ $this->contactInitials($contact) }}
                        </div>
                        <div class="ga-contact-main">
                            <div class="ga-contact-heading">
                                <div>
                                    <p class="ga-contact-name">{{ $this->contactLabel($contact) }}</p>
                                    <p class="ga-contact-phone">{{ $this->contactPhone($contact) }}</p>
                                </div>
                                @if ($conversation?->last_message_at)
                                    <span class="ga-contact-time">
                                        {{ $conversation->last_message_at->format('d/m H:i') }}
                                    </span>
                                @endif
                            </div>
                            <p class="ga-contact-preview">
                                {{ $conversation?->last_message_preview ?: 'Aucun message' }}
                            </p>
                        </div>
                        @if (($conversation?->unread_count ?? 0) > 0)
                            <span class="ga-contact-badge">
                                {{ $conversation->unread_count }}
                            </span>
                        @endif
                    </button>
                @empty
                    <div class="ga-empty-state ga-empty-state-light">
                        Aucun contact avec numero de telephone.
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="ga-thread">
            @if ($activeContact)
                <header class="ga-thread-header">
                    <div class="ga-thread-header-content">
                        <div class="ga-thread-contact">
                            <h2 class="ga-thread-title">{{ $this->contactLabel($activeContact) }}</h2>
                            <p class="ga-thread-subtitle">{{ $this->contactPhone($activeContact) }}</p>
                        </div>
                        <div class="ga-thread-state">
                            <p class="ga-thread-state-label">Etat instance</p>
                            <p class="ga-thread-state-value">{{ $config->instance_state ?: 'Inconnu' }}</p>
                        </div>
                    </div>
                </header>

                <div wire:poll.10s="refreshThread" class="ga-thread-body">
                    <div class="ga-message-list">
                        @forelse ($messages as $message)
                            @php($incoming = $message->direction === 'incoming')
                            <div wire:key="green-api-message-{{ $message->id }}" class="ga-message-row {{ $incoming ? 'ga-message-row-incoming' : 'ga-message-row-outgoing' }}">
                                <article class="ga-message-bubble {{ $incoming ? 'ga-message-bubble-incoming' : 'ga-message-bubble-outgoing' }}">
                                    @if ($message->body)
                                        <p class="ga-message-text">{{ $message->body }}</p>
                                    @endif

                                    @if ($message->caption)
                                        <p class="ga-message-caption {{ $message->body ? 'ga-message-caption-muted' : '' }}">{{ $message->caption }}</p>
                                    @endif

                                    @if ($message->file_name)
                                        <div class="ga-message-file">
                                            <p class="ga-message-file-name">{{ $message->file_name }}</p>
                                            @if ($message->file_url)
                                                <a href="{{ $message->file_url }}" target="_blank" rel="noopener noreferrer" class="ga-message-file-link">
                                                    Ouvrir le fichier
                                                </a>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="ga-message-meta {{ $incoming ? 'ga-message-meta-incoming' : 'ga-message-meta-outgoing' }}">
                                        <span>{{ $message->sent_at?->format('d/m H:i') ?: $message->created_at->format('d/m H:i') }}</span>
                                        @if ($message->status)
                                            <span>{{ $message->status }}</span>
                                        @endif
                                    </div>
                                </article>
                            </div>
                        @empty
                            <div class="ga-empty-state ga-empty-state-dark">
                                Aucun message pour ce contact. Envoyez le premier message depuis cette page.
                            </div>
                        @endforelse
                    </div>
                </div>

                <form wire:submit="send" class="ga-thread-form">
                    <div class="ga-thread-form-fields">
                        <textarea
                            wire:model.live="messageBody"
                            rows="3"
                            placeholder="Ecrire un message WhatsApp"
                            class="ga-thread-textarea"
                        ></textarea>

                        <div class="ga-thread-actions">
                            <div class="ga-thread-attachments">
                                <label class="ga-attachment-button">
                                    <span>Joindre un fichier</span>
                                    <input type="file" wire:model="attachment" class="ga-attachment-input" />
                                </label>
                                @if ($attachment)
                                    <span class="ga-attachment-name">{{ $attachment->getClientOriginalName() }}</span>
                                @endif
                            </div>

                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                                Envoyer
                            </x-filament::button>
                        </div>

                        @error('attachment')
                            <p class="ga-error-text">{{ $message }}</p>
                        @enderror
                    </div>
                </form>
            @else
                <div class="ga-thread-empty">
                    Selectionnez un contact pour ouvrir la conversation WhatsApp.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
