<x-filament-panels::page>
    @php($config = $this->getConfigRecord())

    <div class="grid gap-6 xl:grid-cols-[2fr,1fr]">
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    Enregistrer
                </x-filament::button>
            </div>
        </form>

        <div class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Etat</h3>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-xs text-slate-500">Instance</dt>
                        <dd class="text-lg font-semibold text-slate-900">{{ $config->instance_id ?: 'Non configuree' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Etat Green API</dt>
                        <dd class="text-lg font-semibold text-slate-900">{{ $config->instance_state ?: 'Inconnu' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Derniere verification</dt>
                        <dd class="text-sm text-slate-700">{{ $config->last_connection_checked_at?->format('d/m/Y H:i') ?: 'Jamais' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Derniere synchro webhook</dt>
                        <dd class="text-sm text-slate-700">{{ $config->last_webhook_synced_at?->format('d/m/Y H:i') ?: 'Jamais' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Dernier webhook recu</dt>
                        <dd class="text-sm text-slate-700">
                            {{ $config->last_webhook_type ?: 'Aucun' }}
                            @if ($config->last_webhook_received_at)
                                <span class="text-slate-400">· {{ $config->last_webhook_received_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 p-6 text-white shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-200">Webhook CRM</h3>
                <p class="mt-4 text-2xl font-semibold">{{ route('green-api.webhook') }}</p>
                <p class="mt-3 text-sm text-emerald-50/80">
                    Cette URL est celle a utiliser si vous laissez le champ webhook vide dans la configuration.
                </p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
