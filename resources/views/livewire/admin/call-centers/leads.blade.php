<?php

use App\LeadStatus;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $selectedCenterId = null;
    public string $statusFilter = '';
    public int $leadLimit = 5;
    public array $statusOptions = [];
    public array $leadLimitOptions = [5, 10, 20, 50];

    public function mount(): void
    {
        $this->statusOptions = collect(LeadStatus::cases())
            ->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->prepend(['value' => '', 'label' => __('Tous les statuts')])
            ->all();
    }

    public function updatedSelectedCenterId(): void
    {
        // Reset filters when center changes
        // No pagination to reset
    }

    public function updatedLeadLimit($value): void
    {
        $limit = (int) $value;
        $this->leadLimit = $limit > 0 ? min($limit, 100) : 5;
    }

    public function getAllCentersProperty(): Collection
    {
        return CallCenter::orderBy('name')->get();
    }

    public function getCentersProperty(): Collection
    {
        if (! $this->selectedCenterId) {
            return collect();
        }

        $centers = CallCenter::with('owner')
            ->where('id', $this->selectedCenterId)
            ->withCount([
                'leads as total_leads_count',
                'leads as pending_leads_count' => fn ($query) => $query->whereIn('status', [
                    LeadStatus::PendingEmail->value,
                    LeadStatus::EmailConfirmed->value,
                    LeadStatus::PendingCall->value,
                    LeadStatus::CallbackPending->value,
                    LeadStatus::FollowUp->value,
                    LeadStatus::QuoteSent->value,
                ]),
                'leads as confirmed_leads_count' => fn ($query) => $query->where('status', LeadStatus::Confirmed->value),
                'leads as rejected_leads_count' => fn ($query) => $query->whereIn('status', [
                    LeadStatus::Rejected->value,
                    LeadStatus::NotInterested->value,
                    LeadStatus::DoNotCall->value,
                ]),
                'users as agents_count' => fn ($query) => $query->whereHas('role', fn ($role) => $role->where('slug', 'agent')),
            ])
            ->orderBy('name')
            ->get();

        $centerIds = $centers->pluck('id');

        $recentLeads = Lead::with(['form', 'assignedAgent'])
            ->whereIn('call_center_id', $centerIds)
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('created_at')
            ->get()
            ->groupBy('call_center_id');

        $formsSummary = Form::withCount([
            'leads as total_leads_count' => function ($query) {
                if ($this->statusFilter) {
                    $query->where('status', $this->statusFilter);
                }
            },
        ])
            ->whereIn('call_center_id', $centerIds)
            ->orderBy('name')
            ->get()
            ->groupBy('call_center_id');

        return $centers->map(function (CallCenter $center) use ($recentLeads, $formsSummary) {
            $center->setRelation(
                'recentLeads',
                ($recentLeads->get($center->id) ?? collect())->take($this->leadLimit)
            );

            $center->setRelation(
                'formsSummary',
                $formsSummary->get($center->id) ?? collect()
            );

            return $center;
        });
    }

    public function statusLabel(?string $status): string
    {
        return $status ? (LeadStatus::tryFrom($status)?->label() ?? Str::headline(str_replace('_', ' ', $status))) : __('Non défini');
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm uppercase tracking-wide text-primary-500">{{ __('Vue par centre') }}</p>
            <h1 class="mt-1 text-3xl font-bold text-neutral-900 dark:text-neutral-50">
                {{ __('Leads par centre d\'appels') }}
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                {{ __('Sélectionnez un centre d\'appels pour voir ses leads et statistiques.') }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <flux:select wire:model.live="selectedCenterId" :label="__('Sélectionner un centre d\'appels')" required>
            <option value="">{{ __('Choisir un centre d\'appels...') }}</option>
            @foreach ($this->allCenters as $center)
                <option value="{{ $center->id }}">{{ $center->name }}</option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->allCenters->isEmpty())
        <flux:callout variant="neutral" icon="information-circle">
            {{ __('Aucun centre d\'appels n\'a été trouvé. Créez un centre pour commencer à suivre vos leads.') }}
        </flux:callout>
    @elseif (! $this->selectedCenterId)
        <flux:callout variant="neutral" icon="information-circle">
            {{ __('Veuillez sélectionner un centre d\'appels ci-dessus pour voir ses leads et statistiques.') }}
        </flux:callout>
    @else
        <div class="flex flex-col gap-4">
            <!-- Nuage de tags pour les statuts -->
            <div>
                <label class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {{ __('Filtrer par statut') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        wire:click="$set('statusFilter', '')"
                        type="button"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ empty($statusFilter) ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-500 ring-offset-2' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                    >
                        {{ __('Tous') }}
                    </button>
                    @foreach (\App\Models\LeadStatus::allStatuses() as $status)
                        @php
                            $isActive = $statusFilter === $status->slug;
                        @endphp
                        <button
                            wire:click="$set('statusFilter', '{{ $status->slug }}')"
                            type="button"
                            class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all {{ $isActive ? 'shadow-md ring-2 ring-offset-2 ' . str_replace('bg-', 'ring-', explode(' ', $status->getColorClass())[0]) . ' ' . $status->getColorClass() : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                        >
                            {{ $status->getLabel() }}
                        </button>
                    @endforeach
                </div>
            </div>
            
            <!-- Limite de leads -->
            <div class="flex items-end justify-end">
                <flux:select wire:model.live="leadLimit" :label="__('Nombre de leads récents')" class="w-full sm:w-48">
                    @foreach ($leadLimitOptions as $limit)
                        <option value="{{ $limit }}">{{ $limit }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    @endif

    <div class="grid gap-6">
        @foreach ($this->centers as $center)
            <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900/40">
                <div class="border-b border-neutral-100 px-6 py-4 dark:border-neutral-800">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="text-xl font-semibold text-neutral-900 dark:text-neutral-50">
                                    {{ $center->name }}
                                </h2>
                                @if ($center->is_active)
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">
                                        {{ __('Actif') }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-neutral-200 px-3 py-1 text-xs font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ __('Inactif') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ $center->owner ? __('Responsable : :name', ['name' => $center->owner->name]) : __('Aucun propriétaire associé') }}
                            </p>
                        </div>
                        <div class="text-sm text-neutral-500 dark:text-neutral-400">
                            <div>{{ __('Méthode : :method', ['method' => __($center->distribution_method === 'round_robin' ? 'Round Robin' : ($center->distribution_method === 'weighted' ? 'Pondérée' : 'Manuelle'))]) }}</div>
                            <div>{{ __('Agents actifs : :count', ['count' => $center->agents_count ?? 0]) }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 border-b border-neutral-100 px-6 py-4 dark:border-neutral-800 md:grid-cols-4">
                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/20">
                        <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ __('Total') }}</p>
                        <p class="text-2xl font-semibold text-neutral-900 dark:text-neutral-50">{{ $center->total_leads_count }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/20">
                        <p class="text-xs uppercase tracking-wide text-amber-500">{{ __('À traiter') }}</p>
                        <p class="text-2xl font-semibold text-amber-600 dark:text-amber-300">{{ $center->pending_leads_count }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/20">
                        <p class="text-xs uppercase tracking-wide text-emerald-500">{{ __('Confirmés') }}</p>
                        <p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-300">{{ $center->confirmed_leads_count }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-100 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-900/20">
                        <p class="text-xs uppercase tracking-wide text-rose-500">{{ __('Rejetés') }}</p>
                        <p class="text-2xl font-semibold text-rose-600 dark:text-rose-300">{{ $center->rejected_leads_count }}</p>
                    </div>
                </div>

                <div class="grid gap-4 px-6 py-6 lg:grid-cols-5">
                    <div class="space-y-4 lg:col-span-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-50">
                                {{ __('Leads récents') }}
                            </h3>
                            <span class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Affichage des :count derniers leads', ['count' => $leadLimit]) }}
                            </span>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800">
                            <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-800">
                                <thead class="bg-neutral-50 dark:bg-neutral-900/40">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                                        <th class="px-4 py-3">{{ __('Lead') }}</th>
                                        <th class="px-4 py-3">{{ __('Offre') }}</th>
                                        <th class="px-4 py-3">{{ __('Agent') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Statut') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100 bg-white text-sm dark:divide-neutral-800 dark:bg-transparent">
                                    @forelse ($center->recentLeads as $lead)
                                        @php
                                            $status = \App\LeadStatus::tryFrom($lead->status);
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3">
                                                @php
                                                    $phone = $lead->phone ?? data_get($lead->data, 'phone');
                                                    $fullName = $lead->data['name']
                                                        ?? trim(($lead->data['first_name'] ?? '') . ' ' . ($lead->data['last_name'] ?? ''));
                                                    $fullName = $fullName ?: $lead->email;
                                                @endphp
                                                <div class="flex flex-col gap-1">
                                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-neutral-400 dark:text-neutral-500">
                                                        #{{ $lead->id }}
                                                    </span>
                                                    <div class="font-medium text-neutral-900 dark:text-neutral-50">
                                                        {{ $fullName }}
                                                    </div>
                                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                                        {{ $lead->email }}
                                                    </p>
                                                    @if ($phone)
                                                        <p class="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-1">
                                                            📞 <span>{{ $phone }}</span>
                                                        </p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-neutral-800 dark:text-neutral-200">
                                                {{ $lead->form?->name ?? __('Sans formulaire') }}
                                            </td>
                                            <td class="px-4 py-3 text-neutral-800 dark:text-neutral-200">
                                                {{ $lead->assignedAgent?->name ?? __('Non assigné') }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                                    {{ $status?->label() ?? Str::headline($lead->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                                {{ $statusFilter ? __('Aucun lead correspondant à ce filtre.') : __('Aucun lead disponible pour ce centre.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-4 lg:col-span-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-50">
                                {{ __('Offres & formulaires') }}
                            </h3>
                            <span class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Top 3') }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            @php
                                $topForms = $center->formsSummary instanceof \Illuminate\Support\Collection
                                    ? $center->formsSummary->sortByDesc('total_leads_count')->take(3)
                                    : collect();
                            @endphp

                            @forelse ($topForms as $form)
                                <div class="rounded-xl border border-neutral-200 p-3 dark:border-neutral-800">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-neutral-900 dark:text-neutral-50">
                                                {{ $form->name }}
                                            </p>
                                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                                {{ __('Total leads : :count', ['count' => $form->total_leads_count]) }}
                                            </p>
                                        </div>
                                        <span class="text-sm font-semibold text-neutral-700 dark:text-neutral-200">
                                            {{ number_format($form->total_leads_count) }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <flux:callout variant="neutral" icon="list-bullet">
                                    {{ __('Aucun formulaire associé ou aucun lead pour ce centre.') }}
                                </flux:callout>
                            @endforelse
                        </div>

                        <flux:button
                            href="{{ route('admin.leads') }}?callCenter={{ $center->id }}"
                            variant="ghost"
                            wire:navigate
                            class="w-full justify-center"
                        >
                            {{ __('Voir tous les leads de ce centre') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

