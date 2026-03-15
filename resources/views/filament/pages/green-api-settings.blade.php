<x-filament-panels::page>
    @php($config = $this->getConfigRecord())

    <div class="ga-plugin ga-settings-layout">
        <form wire:submit="save" class="ga-settings-form">
            {{ $this->form }}

            <div class="ga-settings-actions">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    Enregistrer
                </x-filament::button>
            </div>
        </form>

        <div class="ga-stack">
            <section class="ga-card">
                <h3 class="ga-card-eyebrow">Etat</h3>
                <dl class="ga-card-list">
                    <div class="ga-card-list-item">
                        <dt class="ga-card-label">Instance</dt>
                        <dd class="ga-card-value ga-card-value-lg">{{ $config->instance_id ?: 'Non configuree' }}</dd>
                    </div>
                    <div class="ga-card-list-item">
                        <dt class="ga-card-label">Etat Green API</dt>
                        <dd class="ga-card-value ga-card-value-lg">{{ $config->instance_state ?: 'Inconnu' }}</dd>
                    </div>
                    <div class="ga-card-list-item">
                        <dt class="ga-card-label">Derniere verification</dt>
                        <dd class="ga-card-value">{{ $config->last_connection_checked_at?->format('d/m/Y H:i') ?: 'Jamais' }}</dd>
                    </div>
                    <div class="ga-card-list-item">
                        <dt class="ga-card-label">Derniere synchro webhook</dt>
                        <dd class="ga-card-value">{{ $config->last_webhook_synced_at?->format('d/m/Y H:i') ?: 'Jamais' }}</dd>
                    </div>
                    <div class="ga-card-list-item">
                        <dt class="ga-card-label">Dernier webhook recu</dt>
                        <dd class="ga-card-value">
                            {{ $config->last_webhook_type ?: 'Aucun' }}
                            @if ($config->last_webhook_received_at)
                                <span class="ga-card-muted">· {{ $config->last_webhook_received_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="ga-card ga-card-dark">
                <h3 class="ga-card-eyebrow ga-card-eyebrow-dark">Webhook CRM</h3>
                <p class="ga-card-url">{{ route('green-api.webhook') }}</p>
                <p class="ga-card-help ga-card-help-dark">
                    Cette URL est celle a utiliser si vous laissez le champ webhook vide dans la configuration.
                </p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
