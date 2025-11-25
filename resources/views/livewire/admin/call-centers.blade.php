<?php

use App\Models\CallCenter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showAccessModal = false;
    public bool $showCreateModal = false;
    public ?int $editingCenterId = null;
    public string $editingCenterName = '';
    public ?int $ownerId = null;
    /** @var list<int> */
    public array $agentIds = [];
    public string $distributionMethod = 'round_robin';
    public bool $isActive = true;
    public string $newCenterName = '';
    public ?string $newCenterDescription = null;
    public string $newOwnerName = '';
    public string $newOwnerEmail = '';
    public string $newOwnerPassword = '';
    public string $newDistributionMethod = 'round_robin';
    public bool $newIsActive = true;

    public function mount(): void
    {
        $this->distributionMethod = 'round_robin';
        $this->newDistributionMethod = 'round_robin';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openAccessModal(int $callCenterId): void
    {
        $callCenter = CallCenter::with(['users', 'owner'])->findOrFail($callCenterId);

        $this->editingCenterId = $callCenter->id;
        $this->editingCenterName = $callCenter->name;
        $this->ownerId = $callCenter->owner_id;
        $this->agentIds = $callCenter->users()
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
        $this->distributionMethod = $callCenter->distribution_method ?? 'round_robin';
        $this->isActive = (bool) $callCenter->is_active;
        $this->showAccessModal = true;
    }

    public function closeModal(): void
    {
        $this->reset([
            'showAccessModal',
            'editingCenterId',
            'editingCenterName',
            'ownerId',
            'agentIds',
        ]);
        $this->distributionMethod = 'round_robin';
        $this->isActive = true;
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->reset([
            'newCenterName',
            'newCenterDescription',
            'newOwnerName',
            'newOwnerEmail',
            'newOwnerPassword',
        ]);
        $this->newDistributionMethod = 'round_robin';
        $this->newIsActive = true;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->reset([
            'showCreateModal',
            'newCenterName',
            'newCenterDescription',
            'newOwnerName',
            'newOwnerEmail',
            'newOwnerPassword',
        ]);
        $this->newDistributionMethod = 'round_robin';
        $this->newIsActive = true;
    }

    public function saveAccess(): void
    {
        $this->validate([
            'editingCenterId' => ['required', Rule::exists('call_centers', 'id')],
            'ownerId' => ['required', Rule::exists('users', 'id')],
            'agentIds' => ['array'],
            'agentIds.*' => [Rule::exists('users', 'id')],
            'distributionMethod' => ['required', Rule::in(['round_robin', 'weighted', 'manual'])],
        ]);

        $callCenter = CallCenter::findOrFail($this->editingCenterId);

        $owner = User::where('id', $this->ownerId)
            ->whereHas('role', fn ($q) => $q->where('slug', 'call_center_owner'))
            ->firstOrFail();

        $callCenter->fill([
            'owner_id' => $owner->id,
            'distribution_method' => $this->distributionMethod,
            'is_active' => $this->isActive,
        ])->save();

        // Ensure only one owner is linked to this call center.
        User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'call_center_owner'))
            ->where('id', '!=', $owner->id)
            ->update(['call_center_id' => null]);

        $owner->call_center_id = $callCenter->id;
        $owner->save();

        // Reset agent assignments before applying the new selection.
        User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->update(['call_center_id' => null]);

        if (! empty($this->agentIds)) {
            User::whereIn('id', $this->agentIds)
                ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
                ->update(['call_center_id' => $callCenter->id]);
        }

        session()->flash('message', __('Accès mis à jour avec succès.'));
        $this->closeModal();
        $this->dispatch('call-center-access-updated');
        $this->resetPage();
    }

    public function createCallCenter(): void
    {
        $this->validate([
            'newCenterName' => ['required', 'string', 'max:255'],
            'newOwnerName' => ['required', 'string', 'max:255'],
            'newOwnerEmail' => ['required', 'email', Rule::unique('users', 'email')],
            'newOwnerPassword' => ['required', 'string', 'min:8'],
            'newDistributionMethod' => ['required', Rule::in(['round_robin', 'weighted', 'manual'])],
        ], [], [
            'newCenterName' => __('Nom du centre'),
            'newOwnerName' => __('Nom du propriétaire'),
            'newOwnerEmail' => __('Email du propriétaire'),
            'newOwnerPassword' => __('Mot de passe temporaire'),
        ]);

        $ownerRole = User::whereHas('role', fn ($q) => $q->where('slug', 'call_center_owner'))->first()?->role;

        if (! $ownerRole) {
            $ownerRole = \App\Models\Role::firstOrCreate(
                ['slug' => 'call_center_owner'],
                ['name' => 'Call Center Owner', 'description' => 'Propriétaire de centre d\'appels']
            );
        }

        $owner = User::create([
            'name' => $this->newOwnerName,
            'email' => $this->newOwnerEmail,
            'password' => Hash::make($this->newOwnerPassword),
            'role_id' => $ownerRole->id,
            'email_verified_at' => now(),
        ]);

        $callCenter = CallCenter::create([
            'name' => $this->newCenterName,
            'description' => $this->newCenterDescription,
            'owner_id' => $owner->id,
            'distribution_method' => $this->newDistributionMethod,
            'is_active' => $this->newIsActive,
        ]);

        $owner->call_center_id = $callCenter->id;
        $owner->save();

        session()->flash('message', __('Centre d\'appels et propriétaire créés avec succès.'));
        $this->closeCreateModal();
        $this->dispatch('call-center-created');
        $this->resetPage();
    }

    public function getCallCentersProperty()
    {
        return CallCenter::query()
            ->with(['owner'])
            ->withCount([
                'leads',
                'users as agents_count' => fn ($query) => $query->whereHas('role', fn ($q) => $q->where('slug', 'agent')),
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function with(): array
    {
        return [
            'owners' => User::whereHas('role', fn ($q) => $q->where('slug', 'call_center_owner'))
                ->orderBy('name')
                ->get(),
            'agents' => User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))
                ->with('callCenter')
                ->orderBy('name')
                ->get(),
            'distributionMethods' => [
                'round_robin' => __('Round Robin équilibré'),
                'weighted' => __('Répartition pondérée'),
                'manual' => __('Attribution manuelle'),
            ],
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="grow">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                {{ __('Centres d\'appels') }}
            </h1>
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Pilotez les propriétaires et les agents sans quitter le tableau de bord super administrateur.') }}
            </p>
        </div>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Rechercher un centre...') }}"
                icon="magnifying-glass"
            />
            <flux:button icon="plus" variant="primary" wire:click="openCreateModal">
                {{ __('Créer un centre + propriétaire') }}
            </flux:button>
        </div>
    </div>

    @if (session()->has('message'))
        <flux:callout variant="success" icon="sparkles">
            {{ session('message') }}
        </flux:callout>
    @endif

    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900/20">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
                <thead class="bg-neutral-50 dark:bg-neutral-900/40">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Centre') }}</th>
                        <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Propriétaire') }}</th>
                        <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Distribution') }}</th>
                        <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Agents') }}</th>
                        <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Leads') }}</th>
                        <th class="px-6 py-3 text-left font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Statut') }}</th>
                        <th class="px-6 py-3 text-right font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-800 dark:bg-transparent">
                    @forelse ($this->callCenters as $center)
                        <tr wire:key="center-{{ $center->id }}" class="hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                            <td class="px-6 py-4">
                                <div class="font-medium text-neutral-900 dark:text-neutral-50">
                                    {{ $center->name }}
                                </div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ Str::limit($center->description, 80) }}
                                </p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-neutral-900 dark:text-neutral-50">
                                    {{ $center->owner?->name ?? __('Aucun propriétaire') }}
                                </div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $center->owner?->email ?? '' }}
                                </p>
                            </td>
                            <td class="px-6 py-4 capitalize text-neutral-900 dark:text-neutral-50">
                                {{ $center->distribution_method === 'round_robin' ? __('Round Robin') : ($center->distribution_method === 'weighted' ? __('Pondérée') : __('Manuelle')) }}
                            </td>
                            <td class="px-6 py-4 text-neutral-900 dark:text-neutral-50">
                                {{ $center->agents_count }}
                            </td>
                            <td class="px-6 py-4 text-neutral-900 dark:text-neutral-50">
                                {{ $center->leads_count }}
                            </td>
                            <td class="px-6 py-4">
                                @if ($center->is_active)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                        {{ __('Actif') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ __('Inactif') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:button
                                    size="xs"
                                    variant="primary"
                                    wire:click="openAccessModal({{ $center->id }})"
                                >
                                    {{ __('Gérer les accès') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun centre d\'appels trouvé.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->callCenters->hasPages())
            <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-800">
                {{ $this->callCenters->links() }}
            </div>
        @endif
    </div>

    <flux:modal wire:model="showAccessModal" name="manage-call-center-access">
        <form wire:submit.prevent="saveAccess" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-50">
                    {{ __('Gestion des accès') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ $editingCenterName ?: __('Sélectionnez un centre d\'appels pour modifier ses accès.') }}
                </p>
            </div>

            <flux:select wire:model="ownerId" :label="__('Propriétaire du centre')">
                <option value="">{{ __('Sélectionner un propriétaire') }}</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}">
                        {{ $owner->name }} — {{ $owner->email }}
                    </option>
                @endforeach
            </flux:select>
            @error('ownerId')
                <p class="text-sm text-red-500">{{ $message }}</p>
            @enderror

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="distributionMethod" :label="__('Méthode de distribution')">
                    @foreach ($distributionMethods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>

                <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <div>
                        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-50">{{ __('Centre actif') }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Désactivez pour suspendre toutes les opérations.') }}</p>
                    </div>
                    <flux:switch wire:model="isActive" />
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-50">
                    {{ __('Agents affectés') }}
                </p>
                <div class="max-h-56 space-y-2 overflow-y-auto">
                    @forelse ($agents as $agent)
                        <label wire:key="agent-{{ $agent->id }}" class="flex items-start gap-3 rounded-lg border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700">
                            <input
                                type="checkbox"
                                class="mt-1 rounded border-neutral-300 text-primary-600 focus:ring-primary-500"
                                value="{{ $agent->id }}"
                                wire:model="agentIds"
                            >
                            <div>
                                <p class="font-medium text-neutral-900 dark:text-neutral-50">{{ $agent->name }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $agent->email }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $agent->callCenter?->name ? __('Actuellement : :name', ['name' => $agent->callCenter->name]) : __('Non assigné') }}
                                </p>
                            </div>
                        </label>
                    @empty
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">
                            {{ __('Aucun agent disponible pour le moment.') }}
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeModal">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Enregistrer les accès') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCreateModal" name="create-call-center">
        <form wire:submit.prevent="createCallCenter" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-50">
                    {{ __('Nouveau centre d\'appels') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ __('Créez un propriétaire et son centre d\'appels sans activer la page d\'inscription publique.') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    wire:model="newCenterName"
                    :label="__('Nom du centre')"
                    required
                />
                <flux:select wire:model="newDistributionMethod" :label="__('Méthode de distribution')">
                    @foreach ($distributionMethods as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>
            @error('newCenterName')
                <p class="text-sm text-red-500">{{ $message }}</p>
            @enderror

            <flux:textarea
                wire:model="newCenterDescription"
                :label="__('Description')"
                rows="3"
            />

            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <div>
                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-50">{{ __('Centre actif') }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Désactivez si le centre doit rester en pause pour le moment.') }}</p>
                </div>
                <flux:switch wire:model="newIsActive" />
            </div>

            <div class="space-y-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-50">{{ __('Informations propriétaire') }}</p>
                <flux:input
                    wire:model="newOwnerName"
                    :label="__('Nom complet')"
                    required
                />
                @error('newOwnerName')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror

                <flux:input
                    wire:model="newOwnerEmail"
                    type="email"
                    :label="__('Email professionnel')"
                    required
                />
                @error('newOwnerEmail')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror

                <flux:input
                    wire:model="newOwnerPassword"
                    type="password"
                    :label="__('Mot de passe temporaire')"
                    helper-text="{{ __('Communiquez ce mot de passe au propriétaire. Il pourra le modifier depuis son espace.') }}"
                    required
                />
                @error('newOwnerPassword')
                    <p class="text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closeCreateModal">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Créer le centre') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

