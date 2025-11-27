<?php

use App\Models\Webhook;
use App\Services\WebhookService;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public ?string $filterStatus = null;
    public ?int $filterFormId = null;
    public ?int $filterCallCenterId = null;
    public array $selected = [];
    public ?int $testingWebhookId = null;
    public ?array $testResult = null;

    public function updatedSearch(): void
    {
        $this->reset('selected');
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterFormId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCallCenterId(): void
    {
        $this->resetPage();
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
        session()->flash('message', __('Webhook supprimé avec succès.'));
        $this->reset('selected');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);
        Webhook::whereIn('id', $this->selected)->delete();
        
        session()->flash('message', __(':count webhook(s) supprimé(s) avec succès.', ['count' => $count]));
        $this->reset('selected');
        $this->resetPage();
    }

    public function toggleActive(Webhook $webhook): void
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);
        session()->flash('message', __('Webhook :action avec succès.', [
            'action' => $webhook->is_active ? __('activé') : __('désactivé')
        ]));
    }

    public function testWebhook(Webhook $webhook): void
    {
        $this->testingWebhookId = $webhook->id;
        $this->testResult = null;

        try {
            $service = app(WebhookService::class);
            $this->testResult = $service->testWebhook($webhook);
        } catch (\Exception $e) {
            $this->testResult = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->testingWebhookId = null;
        }
    }

    public function getAvailableEvents(): array
    {
        return [
            'lead.created' => __('Lead créé'),
            'lead.email_confirmed' => __('Email confirmé'),
            'lead.assigned' => __('Lead assigné'),
            'lead.status_updated' => __('Statut mis à jour'),
            'lead.converted' => __('Lead converti'),
        ];
    }

    public function with(): array
    {
        $webhooks = Webhook::query()
            ->with(['form', 'callCenter', 'user'])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('url', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== null, fn ($query) => $query->where('is_active', $this->filterStatus === 'active'))
            ->when($this->filterFormId, fn ($query) => $query->where('form_id', $this->filterFormId))
            ->when($this->filterCallCenterId, fn ($query) => $query->where('call_center_id', $this->filterCallCenterId))
            ->latest()
            ->paginate(10);
        
        return [
            'webhooks' => $webhooks,
            'selectedCount' => count($this->selected),
            'forms' => \App\Models\Form::orderBy('name')->get(),
            'callCenters' => \App\Models\CallCenter::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Header avec actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Webhooks') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez vos webhooks pour intégrer la plateforme avec des systèmes externes') }}</p>
        </div>
        <flux:button href="{{ route('admin.webhooks.create') }}" variant="primary" icon="plus">
            {{ __('Nouveau webhook') }}
        </flux:button>
    </div>

    <!-- Filtres -->
    <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800 sm:flex-row">
        <div class="flex-1">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Rechercher par nom ou URL...') }}" 
                icon="magnifying-glass"
            />
        </div>
        <flux:select wire:model.live="filterStatus" placeholder="{{ __('Tous les statuts') }}">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="active">{{ __('Actifs') }}</option>
            <option value="inactive">{{ __('Inactifs') }}</option>
        </flux:select>
        <flux:select wire:model.live="filterFormId" placeholder="{{ __('Tous les formulaires') }}">
            <option value="">{{ __('Tous les formulaires') }}</option>
            @foreach($forms as $form)
                <option value="{{ $form->id }}">{{ $form->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterCallCenterId" placeholder="{{ __('Tous les centres') }}">
            <option value="">{{ __('Tous les centres') }}</option>
            @foreach($callCenters as $callCenter)
                <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <!-- Liste des webhooks -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Nom') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('URL') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Événements') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Association') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Statut') }}</th>
                        <th class="px-6 py-3 text-right text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse ($webhooks as $webhook)
                        <tr class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                            <td class="px-6 py-4">
                                <div class="font-medium">{{ $webhook->name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                    <code class="rounded bg-neutral-100 px-1.5 py-0.5 dark:bg-neutral-900">{{ Str::limit($webhook->url, 50) }}</code>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($webhook->events ?? [] as $event)
                                        <flux:badge variant="neutral" size="sm">
                                            {{ $this->getAvailableEvents()[$event] ?? $event }}
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                    @if($webhook->form)
                                        <div>{{ __('Formulaire') }}: {{ $webhook->form->name }}</div>
                                    @endif
                                    @if($webhook->callCenter)
                                        <div>{{ __('Centre') }}: {{ $webhook->callCenter->name }}</div>
                                    @endif
                                    @if(!$webhook->form && !$webhook->callCenter)
                                        <span class="text-neutral-400">{{ __('Global') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:badge :variant="$webhook->is_active ? 'success' : 'neutral'">
                                    {{ $webhook->is_active ? __('Actif') : __('Inactif') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical">
                                        {{ __('Actions') }}
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                wire:click="testWebhook({{ $webhook->id }})"
                                                icon="bolt"
                                                wire:loading.attr="disabled"
                                                wire:target="testWebhook({{ $webhook->id }})"
                                            >
                                                <span wire:loading.remove wire:target="testWebhook({{ $webhook->id }})">
                                                    {{ __('Tester') }}
                                                </span>
                                                <span wire:loading wire:target="testWebhook({{ $webhook->id }})">
                                                    {{ __('Test en cours...') }}
                                                </span>
                                            </flux:menu.item>
                                            <flux:menu.item 
                                                href="{{ route('admin.webhooks.edit', $webhook) }}" 
                                                icon="pencil"
                                                wire:navigate
                                            >
                                                {{ __('Modifier') }}
                                            </flux:menu.item>
                                            <flux:menu.item 
                                                wire:click="toggleActive({{ $webhook->id }})"
                                                :icon="$webhook->is_active ? 'eye-slash' : 'eye'"
                                            >
                                                <span wire:loading.remove wire:target="toggleActive({{ $webhook->id }})">
                                                    {{ $webhook->is_active ? __('Désactiver') : __('Activer') }}
                                                </span>
                                                <span wire:loading wire:target="toggleActive({{ $webhook->id }})">
                                                    {{ __('Chargement...') }}
                                                </span>
                                            </flux:menu.item>
                                        </flux:menu.radio.group>

                                        <flux:menu.separator />

                                        <flux:menu.radio.group>
                                            <flux:menu.item 
                                                wire:click="delete({{ $webhook->id }})"
                                                wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce webhook ?') }}"
                                                icon="trash"
                                                class="!text-red-600 dark:!text-red-400"
                                            >
                                                {{ __('Supprimer') }}
                                            </flux:menu.item>
                                        </flux:menu.radio.group>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                        @if($testingWebhookId === $webhook->id && $testResult)
                            <tr>
                                <td colspan="6" class="px-6 py-4">
                                    <div class="rounded-lg border p-4 {{ $testResult['success'] ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20' }}">
                                        <div class="flex items-center gap-2">
                                            @if($testResult['success'])
                                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="font-medium text-green-900 dark:text-green-100">{{ __('Test réussi') }}</span>
                                            @else
                                                <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="font-medium text-red-900 dark:text-red-100">{{ __('Test échoué') }}</span>
                                            @endif
                                        </div>
                                        @if(isset($testResult['status']))
                                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ __('Statut HTTP') }}: {{ $testResult['status'] }}
                                            </div>
                                        @endif
                                        @if(isset($testResult['error']))
                                            <div class="mt-2 text-sm text-red-600 dark:text-red-400">
                                                {{ $testResult['error'] }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucun webhook trouvé') }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($search)
                                            {{ __('Essayez de modifier votre recherche') }}
                                        @else
                                            {{ __('Commencez par créer votre premier webhook') }}
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($webhooks->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $webhooks->links() }}
            </div>
        @endif
    </div>
</div>
