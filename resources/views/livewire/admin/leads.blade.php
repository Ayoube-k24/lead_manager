<?php

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use Illuminate\Http\Request;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $callCenterFilter = null;
    public ?int $formFilter = null;

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

    public function getLeadsProperty()
    {
        return Lead::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('email', 'like', '%'.$this->search.'%')
                        ->orWhereJsonContains('data->name', $this->search)
                        ->orWhereJsonContains('data->first_name', $this->search)
                        ->orWhereJsonContains('data->last_name', $this->search);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->callCenterFilter, function ($query) {
                $query->where('call_center_id', $this->callCenterFilter);
            })
            ->when($this->formFilter, function ($query) {
                $query->where('form_id', $this->formFilter);
            })
            ->with(['form', 'callCenter', 'assignedAgent'])
            ->latest()
            ->paginate(20);
    }

    public function getCallCentersProperty()
    {
        return CallCenter::orderBy('name')->get();
    }

    public function getFormsProperty()
    {
        return Form::orderBy('name')->get();
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Rechercher')"
            placeholder="{{ __('Email, nom...') }}"
        />
        <flux:select wire:model.live="statusFilter" :label="__('Statut')">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="pending_email">{{ __('En attente email') }}</option>
            <option value="email_confirmed">{{ __('Email confirmé') }}</option>
            <option value="pending_call">{{ __('En attente d\'appel') }}</option>
            <option value="confirmed">{{ __('Confirmé') }}</option>
            <option value="rejected">{{ __('Rejeté') }}</option>
            <option value="callback_pending">{{ __('En attente de rappel') }}</option>
        </flux:select>
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
    </div>

    <!-- Liste des leads -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Email') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Nom') }}
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
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $lead->email }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $lead->data['name'] ?? $lead->data['first_name'] ?? 'N/A' }}
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
                            <td colspan="8" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
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

