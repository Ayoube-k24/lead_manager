<?php

use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public ?string $filterStatus = null;
    public ?int $filterFormId = null;
    public ?int $testingWebhookId = null;
    public ?array $testResult = null;

    public function mount(): void
    {
        // Ensure user is a call center owner
        $user = Auth::user();
        if (! $user->isCallCenterOwner()) {
            abort(403, __('Accès refusé'));
        }
    }

    public function updatedSearch(): void
    {
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

    public function delete(Webhook $webhook): void
    {
        // Verify ownership
        $user = Auth::user();
        if ($webhook->call_center_id !== $user->call_center_id) {
            abort(403, __('Vous n\'avez pas la permission de supprimer ce webhook.'));
        }

        $webhook->delete();
        session()->flash('message', __('Webhook supprimé avec succès.'));
    }

    public function toggleActive(Webhook $webhook): void
    {
        // Verify ownership
        $user = Auth::user();
        if ($webhook->call_center_id !== $user->call_center_id) {
            abort(403, __('Vous n\'avez pas la permission de modifier ce webhook.'));
        }

        $webhook->update(['is_active' => ! $webhook->is_active]);
        session()->flash('message', __('Webhook :action avec succès.', [
            'action' => $webhook->is_active ? __('activé') : __('désactivé')
        ]));
    }

    public function testWebhook(Webhook $webhook): void
    {
        // Verify ownership
        $user = Auth::user();
        if ($webhook->call_center_id !== $user->call_center_id) {
            abort(403, __('Vous n\'avez pas la permission de tester ce webhook.'));
        }

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
        $user = Auth::user();
        
        $webhooks = Webhook::query()
            ->where('call_center_id', $user->call_center_id)
            ->with(['form', 'user'])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('url', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== null, fn ($query) => $query->where('is_active', $this->filterStatus === 'active'))
            ->when($this->filterFormId, fn ($query) => $query->where('form_id', $this->filterFormId))
            ->latest()
            ->paginate(10);
        
        return [
            'webhooks' => $webhooks,
            'forms' => \App\Models\Form::where('call_center_id', $user->call_center_id)
                ->orderBy('name')
                ->get(),
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
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Gérez les webhooks de votre centre d\'appels') }}</p>
        </div>
        <flux:button href="{{ route('owner.webhooks.create') }}" variant="primary" icon="plus">
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
                        <th class="px-6 py-3 text-left text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Formulaire') }}</th>
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
                                    {{ $webhook->form?->name ?? __('Tous les formulaires') }}
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
