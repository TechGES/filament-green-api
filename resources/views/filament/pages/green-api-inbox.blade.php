<x-filament-panels::page>
    @php($contacts = $this->contacts())
    @php($activeContact = $this->activeContact())
    @php($activeConversation = $this->activeConversation())
    @php($messages = $this->messages())
    @php($config = $this->currentConfig())

    <div class="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)] xl:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:min-h-[70vh]">
            <div class="border-b border-slate-200 p-4">
                <label class="sr-only" for="green-api-contact-search">Recherche</label>
                <input
                    id="green-api-contact-search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher un contact"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-inner focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                />
            </div>

            <div class="max-h-[70vh] overflow-y-auto">
                @forelse ($contacts as $contact)
                    @php($conversation = $contact->greenApiConversation)
                    <button
                        wire:key="green-api-contact-{{ $contact->getKey() }}"
                        type="button"
                        wire:click="selectContact('{{ $contact->getKey() }}')"
                        class="flex w-full items-start gap-3 border-b border-slate-100 px-4 py-4 text-left transition hover:bg-slate-50 {{ $this->contactIsActive($contact) ? 'bg-emerald-50' : '' }}"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-sm font-semibold text-white">
                            {{ $this->contactInitials($contact) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $this->contactLabel($contact) }}</p>
                                    <p class="text-xs text-slate-500">{{ $this->contactPhone($contact) }}</p>
                                </div>
                                @if ($conversation?->last_message_at)
                                    <span class="shrink-0 text-[11px] text-slate-400">
                                        {{ $conversation->last_message_at->format('d/m H:i') }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 truncate text-sm text-slate-600">
                                {{ $conversation?->last_message_preview ?: 'Aucun message' }}
                            </p>
                        </div>
                        @if (($conversation?->unread_count ?? 0) > 0)
                            <span class="mt-1 inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-500 px-2 text-xs font-semibold text-white">
                                {{ $conversation->unread_count }}
                            </span>
                        @endif
                    </button>
                @empty
                    <div class="p-6 text-sm text-slate-500">
                        Aucun contact avec numero de telephone.
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="flex min-h-[70vh] min-w-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 shadow-sm">
            @if ($activeContact)
                <header class="border-b border-white/10 bg-white px-6 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $this->contactLabel($activeContact) }}</h2>
                            <p class="text-sm text-slate-500">{{ $this->contactPhone($activeContact) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p>Etat instance</p>
                            <p class="font-semibold text-slate-900">{{ $config->instance_state ?: 'Inconnu' }}</p>
                        </div>
                    </div>
                </header>

                <div wire:poll.10s="refreshThread" class="flex-1 overflow-y-auto bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.18),_transparent_40%),linear-gradient(180deg,_#020617,_#111827)] p-6">
                    <div class="space-y-4">
                        @forelse ($messages as $message)
                            @php($incoming = $message->direction === 'incoming')
                            <div wire:key="green-api-message-{{ $message->id }}" class="flex {{ $incoming ? 'justify-start' : 'justify-end' }}">
                                <article class="max-w-2xl rounded-3xl px-4 py-3 shadow-sm {{ $incoming ? 'bg-white text-slate-900' : 'bg-emerald-500 text-white' }}">
                                    @if ($message->body)
                                        <p class="whitespace-pre-wrap text-sm leading-6">{{ $message->body }}</p>
                                    @endif

                                    @if ($message->caption)
                                        <p class="mt-2 whitespace-pre-wrap text-sm leading-6 {{ $message->body ? 'opacity-80' : '' }}">{{ $message->caption }}</p>
                                    @endif

                                    @if ($message->file_name)
                                        <div class="mt-3 rounded-2xl border border-black/10 bg-black/5 px-3 py-2 text-sm">
                                            <p class="font-medium">{{ $message->file_name }}</p>
                                            @if ($message->file_url)
                                                <a href="{{ $message->file_url }}" target="_blank" class="mt-1 inline-block text-xs underline">
                                                    Ouvrir le fichier
                                                </a>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="mt-3 flex items-center justify-end gap-2 text-[11px] {{ $incoming ? 'text-slate-400' : 'text-emerald-50/80' }}">
                                        <span>{{ $message->sent_at?->format('d/m H:i') ?: $message->created_at->format('d/m H:i') }}</span>
                                        @if ($message->status)
                                            <span>{{ $message->status }}</span>
                                        @endif
                                    </div>
                                </article>
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-white/20 bg-white/5 p-6 text-sm text-white/70">
                                Aucun message pour ce contact. Envoyez le premier message depuis cette page.
                            </div>
                        @endforelse
                    </div>
                </div>

                <form wire:submit="send" class="border-t border-white/10 bg-white p-4">
                    <div class="space-y-3">
                        <textarea
                            wire:model.live="messageBody"
                            rows="3"
                            placeholder="Ecrire un message WhatsApp"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        ></textarea>

                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex flex-1 items-center gap-3">
                                <label class="inline-flex cursor-pointer items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-emerald-500 hover:text-emerald-600">
                                    <span>Joindre un fichier</span>
                                    <input type="file" wire:model="attachment" class="hidden" />
                                </label>
                                @if ($attachment)
                                    <span class="truncate text-sm text-slate-500">{{ $attachment->getClientOriginalName() }}</span>
                                @endif
                            </div>

                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                                Envoyer
                            </x-filament::button>
                        </div>

                        @error('attachment')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </form>
            @else
                <div class="flex min-h-[70vh] items-center justify-center p-8 text-center text-sm text-white/70">
                    Selectionnez un contact pour ouvrir la conversation WhatsApp.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
