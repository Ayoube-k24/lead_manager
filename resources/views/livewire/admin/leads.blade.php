<?php

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Services\LeadSearchService;
use Illuminate\Http\Request;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public array $statusFilter = [];

    public ?int $callCenterFilter = null;

    public ?int $formFilter = null;

    public ?int $assignedToFilter = null;

    public ?string $createdFrom = null;

    public ?string $createdTo = null;

    public ?string $emailConfirmedFrom = null;

    public ?string $emailConfirmedTo = null;

    public ?string $calledFrom = null;

    public ?string $calledTo = null;

    public ?bool $emailConfirmed = null;

    public ?bool $hasNotes = null;

    public array $tagsFilter = [];

    public string $tagsMode = 'any';

    public ?bool $noTags = null;

    public array $sourceFilter = [];

    public bool $showAdvancedFilters = false;

    public function mount(Request $request): void
    {
        $callCenterId = $request->query('callCenter');
        if ($callCenterId) {
            $this->callCenterFilter = (int) $callCenterId;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCallCenterFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFormFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function getLeadsProperty()
    {
        $service = app(LeadSearchService::class);

        $filters = [
            'status' => ! empty($this->statusFilter) ? $this->statusFilter : null,
            'call_center_id' => $this->callCenterFilter,
            'form_id' => $this->formFilter,
            'assigned_to' => $this->assignedToFilter,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'email_confirmed_from' => $this->emailConfirmedFrom,
            'email_confirmed_to' => $this->emailConfirmedTo,
            'called_from' => $this->calledFrom,
            'called_to' => $this->calledTo,
            'email_confirmed' => $this->emailConfirmed,
            'has_notes' => $this->hasNotes,
            'tags' => ! empty($this->tagsFilter) ? $this->tagsFilter : null,
            'tags_mode' => $this->tagsMode,
            'no_tags' => $this->noTags,
            'source' => ! empty($this->sourceFilter) ? $this->sourceFilter : null,
        ];

        // Remove null values
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '' && $value !== []);

        return $service->search($this->search, $filters, 20);
    }

    public function getCallCentersProperty()
    {
        return CallCenter::orderBy('name')->get();
    }

    public function getFormsProperty()
    {
        return Form::orderBy('name')->get();
    }

    public function getAgentsProperty()
    {
        return User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->orderBy('name')
            ->get();
    }

    public function getTagsProperty()
    {
        return Tag::orderBy('name')->get();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = [];
        $this->callCenterFilter = null;
        $this->formFilter = null;
        $this->assignedToFilter = null;
        $this->createdFrom = null;
        $this->createdTo = null;
        $this->emailConfirmedFrom = null;
        $this->emailConfirmedTo = null;
        $this->calledFrom = null;
        $this->calledTo = null;
        $this->emailConfirmed = null;
        $this->hasNotes = null;
        $this->tagsFilter = [];
        $this->tagsMode = 'any';
        $this->noTags = null;
        $this->sourceFilter = [];
        $this->resetPage();
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => Lead::count(),
            'pending_email' => Lead::where('status', 'pending_email')->count(),
            'email_confirmed' => Lead::where('status', 'email_confirmed')->count(),
            'pending_call' => Lead::where('status', 'pending_call')->count(),
            'confirmed' => Lead::where('status', 'confirmed')->count(),
            'rejected' => Lead::where('status', 'rejected')->count(),
            'callback_pending' => Lead::where('status', 'callback_pending')->count(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Gestion des Leads') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Vue globale de tous les leads de la plateforme') }}
            </p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-7">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Total') }}</div>
            <div class="mt-1 text-xl font-bold text-neutral-900 dark:text-neutral-100">{{ $this->stats['total'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('En attente email') }}</div>
            <div class="mt-1 text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->stats['pending_email'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Email confirmé') }}</div>
            <div class="mt-1 text-xl font-bold text-blue-600 dark:text-blue-400">{{ $this->stats['email_confirmed'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('En attente appel') }}</div>
            <div class="mt-1 text-xl font-bold text-orange-600 dark:text-orange-400">{{ $this->stats['pending_call'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Confirmés') }}</div>
            <div class="mt-1 text-xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['confirmed'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rejetés') }}</div>
            <div class="mt-1 text-xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['rejected'] }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Rappels') }}</div>
            <div class="mt-1 text-xl font-bold text-purple-600 dark:text-purple-400">{{ $this->stats['callback_pending'] }}</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">{{ __('Recherche et Filtres') }}</h3>
            <div class="flex items-center gap-2">
                @if ($search || !empty($statusFilter) || $callCenterFilter || $formFilter || $assignedToFilter || $createdFrom || $createdTo || $emailConfirmedFrom || $emailConfirmedTo || $calledFrom || $calledTo || $emailConfirmed !== null || $hasNotes !== null || !empty($tagsFilter) || $noTags !== null || !empty($sourceFilter))
                    <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">
                        {{ __('Réinitialiser') }}
                    </flux:button>
                @endif
                <flux:button wire:click="toggleAdvancedFilters" variant="ghost" size="sm" icon="{{ $showAdvancedFilters ? 'chevron-up' : 'chevron-down' }}">
                    {{ $showAdvancedFilters ? __('Masquer les filtres avancés') : __('Afficher les filtres avancés') }}
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :label="__('Recherche')"
                placeholder="{{ __('Email, nom, téléphone...') }}"
                icon="magnifying-glass"
            />
            <flux:select wire:model.live="callCenterFilter" :label="__('Centre d\'appels')">
                <option value="">{{ __('Tous les centres') }}</option>
                @foreach ($this->callCenters as $callCenter)
                    <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="formFilter" :label="__('Formulaire')">
                <option value="">{{ __('Tous les formulaires') }}</option>
                @foreach ($this->forms as $form)
                    <option value="{{ $form->id }}">{{ $form->name }}</option>
                @endforeach
            </flux:select>
            <div>
                <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {{ __('Source') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        wire:click="
                            @if(in_array('form', $sourceFilter))
                                $set('sourceFilter', array_values(array_diff($sourceFilter, ['form'])))
                            @else
                                $set('sourceFilter', array_merge($sourceFilter, ['form']))
                            @endif
                        "
                        type="button"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ in_array('form', $sourceFilter) ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-500 ring-offset-2' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                    >
                        {{ __('Formulaire') }}
                    </button>
                    <button
                        wire:click="
                            @if(in_array('leads_seo', $sourceFilter))
                                $set('sourceFilter', array_values(array_diff($sourceFilter, ['leads_seo'])))
                            @else
                                $set('sourceFilter', array_merge($sourceFilter, ['leads_seo']))
                            @endif
                        "
                        type="button"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ in_array('leads_seo', $sourceFilter) ? 'bg-green-600 text-white shadow-md ring-2 ring-green-500 ring-offset-2' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                    >
                        {{ __('Leads SEO') }}
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Nuage de tags pour les statuts -->
        <div>
            <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                {{ __('Statuts') }}
            </label>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Models\LeadStatus::allStatuses() as $status)
                    @php
                        $isActive = in_array($status->slug, $statusFilter);
                    @endphp
                    <button
                        wire:click="
                            @if($isActive)
                                $set('statusFilter', array_values(array_diff($statusFilter, ['{{ $status->slug }}'])))
                            @else
                                $set('statusFilter', array_merge($statusFilter, ['{{ $status->slug }}']))
                            @endif
                        "
                        type="button"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ $isActive ? 'shadow-md ring-2 ring-offset-2 ' . str_replace('bg-', 'ring-', explode(' ', $status->getColorClass())[0]) . ' ' . $status->getColorClass() : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                    >
                        {{ $status->getLabel() }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Filtres avancés -->
        @if ($showAdvancedFilters)
            <div class="border-t border-neutral-200 pt-4 dark:border-neutral-700">
                <h4 class="mb-4 text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Filtres avancés') }}</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <flux:select wire:model.live="assignedToFilter" :label="__('Agent assigné')">
                        <option value="">{{ __('Tous les agents') }}</option>
                        @foreach ($this->agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model.live="createdFrom" type="date" :label="__('Créé depuis')" />
                    <flux:input wire:model.live="createdTo" type="date" :label="__('Créé jusqu\'au')" />
                    <flux:input wire:model.live="emailConfirmedFrom" type="date" :label="__('Email confirmé depuis')" />
                    <flux:input wire:model.live="emailConfirmedTo" type="date" :label="__('Email confirmé jusqu\'au')" />
                    <flux:input wire:model.live="calledFrom" type="date" :label="__('Appelé depuis')" />
                    <flux:input wire:model.live="calledTo" type="date" :label="__('Appelé jusqu\'au')" />
                    <flux:select wire:model.live="emailConfirmed" :label="__('Email confirmé')">
                        <option value="">{{ __('Tous') }}</option>
                        <option value="1">{{ __('Oui') }}</option>
                        <option value="0">{{ __('Non') }}</option>
                    </flux:select>
                    <flux:select wire:model.live="hasNotes" :label="__('A des notes')">
                        <option value="">{{ __('Tous') }}</option>
                        <option value="1">{{ __('Oui') }}</option>
                        <option value="0">{{ __('Non') }}</option>
                    </flux:select>
                </div>
            </div>
        @endif

        <!-- Filtres par tags -->
        <div class="border-t border-neutral-200 pt-4 dark:border-neutral-700">
            <h4 class="mb-4 text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Filtres par tags') }}</h4>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:label class="mb-2">{{ __('Tags') }}</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->tags as $tag)
                            @php
                                $isSelected = in_array($tag->id, $tagsFilter);
                            @endphp
                            <button
                                wire:click="
                                    @if($isSelected)
                                        $set('tagsFilter', array_values(array_diff($tagsFilter, [{{ $tag->id }}])))
                                    @else
                                        $set('tagsFilter', array_merge($tagsFilter, [{{ $tag->id }}]))
                                    @endif
                                "
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-all {{ $isSelected ? 'ring-2 ring-offset-2' : '' }}"
                                style="{{ $isSelected ? 'background-color: ' . $tag->color . '20; color: ' . $tag->color . '; ring-color: ' . $tag->color : 'background-color: #f3f4f6; color: #6b7280' }}"
                            >
                                <div class="h-2 w-2 rounded-full" style="background-color: {{ $tag->color }};"></div>
                                {{ $tag->name }}
                            </button>
                        @endforeach
                    </div>
                    @if (!empty($tagsFilter))
                        <div class="mt-2">
                            <flux:select wire:model.live="tagsMode" size="sm">
                                <option value="any">{{ __('Leads avec n\'importe lequel des tags sélectionnés') }}</option>
                                <option value="all">{{ __('Leads avec tous les tags sélectionnés') }}</option>
                            </flux:select>
                        </div>
                    @endif
                </div>
                <div>
                    <flux:select wire:model.live="noTags" :label="__('Sans tags')">
                        <option value="">{{ __('Tous') }}</option>
                        <option value="1">{{ __('Oui (uniquement les leads sans tags)') }}</option>
                        <option value="0">{{ __('Non (uniquement les leads avec tags)') }}</option>
                    </flux:select>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des leads -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('ID Lead') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Email') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Nom') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Téléphone') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Formulaire') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Centre d\'appels') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Agent') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Statut') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Date') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($this->leads as $lead)
                        <tr>
                            @php
                                $phone = $lead->phone ?? data_get($lead->data, 'phone');
                                $fullName = $lead->data['name']
                                    ?? trim(($lead->data['first_name'] ?? '') . ' ' . ($lead->data['last_name'] ?? ''));
                                $fullName = $fullName ?: __('Non renseigné');
                            @endphp
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-semibold text-neutral-500 dark:text-neutral-400">
                                #{{ $lead->id }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $lead->email }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $fullName }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $phone ?? __('Non renseigné') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->form?->name ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->callCenter?->name ?? 'N/A' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->assignedAgent?->name ?? __('Non assigné') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                @php
                                    $statusLabels = [
                                        'pending_email' => __('En attente email'),
                                        'email_confirmed' => __('Email confirmé'),
                                        'pending_call' => __('En attente d\'appel'),
                                        'confirmed' => __('Confirmé'),
                                        'rejected' => __('Rejeté'),
                                        'callback_pending' => __('En attente de rappel'),
                                    ];
                                    $statusColors = [
                                        'pending_email' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                                        'email_confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                                        'pending_call' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
                                        'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                        'callback_pending' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
                                    ];
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusColors[$lead->status] ?? 'bg-neutral-100 text-neutral-800 dark:bg-neutral-900/20 dark:text-neutral-400' }}">
                                    {{ $statusLabels[$lead->status] ?? $lead->status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $lead->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <flux:button
                                    href="{{ route('admin.leads.show', $lead) }}"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ __('Voir') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun lead trouvé') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->leads->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                {{ $this->leads->links() }}
            </div>
        @endif
    </div>
</div>

