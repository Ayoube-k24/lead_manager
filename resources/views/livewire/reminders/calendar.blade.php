<?php

use App\Models\LeadReminder;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $view = 'month'; // month, week, list

    public Carbon $currentDate;

    public bool $showReminderDetails = false;

    public ?int $selectedReminderId = null;

    public function mount(): void
    {
        $this->currentDate = now();
    }

    public function previousPeriod(): void
    {
        $this->currentDate = match ($this->view) {
            'month' => $this->currentDate->copy()->subMonth(),
            'week' => $this->currentDate->copy()->subWeek(),
            default => $this->currentDate->copy()->subDay(),
        };
    }

    public function nextPeriod(): void
    {
        $this->currentDate = match ($this->view) {
            'month' => $this->currentDate->copy()->addMonth(),
            'week' => $this->currentDate->copy()->addWeek(),
            default => $this->currentDate->copy()->addDay(),
        };
    }

    public function goToToday(): void
    {
        $this->currentDate = now();
    }

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    public function selectReminder(int $reminderId): void
    {
        $this->selectedReminderId = $reminderId;
        $this->showReminderDetails = true;
    }

    public function closeReminderDetails(): void
    {
        $this->showReminderDetails = false;
        $this->selectedReminderId = null;
    }

    public function completeReminder(LeadReminder $reminder): void
    {
        $service = app(ReminderService::class);
        $service->completeReminder($reminder);

        session()->flash('message', __('Rappel marqué comme complété.'));
        $this->closeReminderDetails();
    }

    public function cancelReminder(LeadReminder $reminder): void
    {
        $service = app(ReminderService::class);
        $service->cancelReminder($reminder);

        session()->flash('message', __('Rappel annulé.'));
        $this->closeReminderDetails();
    }

    public function getRemindersForMonth(): array
    {
        $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
        $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();

        $reminders = LeadReminder::where('user_id', Auth::id())
            ->whereBetween('reminder_date', [$start, $end])
            ->where('is_completed', false)
            ->with('lead')
            ->get()
            ->groupBy(fn ($r) => $r->reminder_date->format('Y-m-d'));

        $days = [];
        $current = $start->copy();

        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $days[] = [
                'date' => $current->copy(),
                'reminders' => $reminders->get($dateKey, collect()),
                'isCurrentMonth' => $current->month === $this->currentDate->month,
                'isToday' => $current->isToday(),
            ];
            $current->addDay();
        }

        return $days;
    }

    public function getRemindersForWeek(): array
    {
        $start = $this->currentDate->copy()->startOfWeek();
        $end = $this->currentDate->copy()->endOfWeek();

        $reminders = LeadReminder::where('user_id', Auth::id())
            ->whereBetween('reminder_date', [$start, $end])
            ->where('is_completed', false)
            ->with('lead')
            ->orderBy('reminder_date')
            ->get()
            ->groupBy(fn ($r) => $r->reminder_date->format('Y-m-d'));

        $days = [];
        $current = $start->copy();

        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $days[] = [
                'date' => $current->copy(),
                'reminders' => $reminders->get($dateKey, collect()),
                'isToday' => $current->isToday(),
            ];
            $current->addDay();
        }

        return $days;
    }

    public function with(): array
    {
        $service = app(ReminderService::class);

        $upcomingReminders = $service->getUpcomingReminders(Auth::user(), 30);
        $overdueReminders = $service->getOverdueReminders(Auth::user());

        return [
            'upcomingReminders' => $upcomingReminders,
            'overdueReminders' => $overdueReminders,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Calendrier des Rappels') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Visualisez et gérez tous vos rappels planifiés') }}</p>
        </div>
    </div>

    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Rappels en retard -->
    @if ($overdueReminders->isNotEmpty())
        <flux:callout variant="danger" icon="exclamation-triangle">
            <div class="flex items-center justify-between">
                <div>
                    <strong>{{ __('Rappels en retard') }}</strong>
                    <p class="mt-1 text-sm">{{ __('Vous avez :count rappel(s) en retard', ['count' => $overdueReminders->count()]) }}</p>
                </div>
                <flux:button href="{{ route('agent.leads') }}" variant="primary" size="sm">
                    {{ __('Voir les leads') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <!-- Contrôles de navigation -->
    <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-800 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <flux:button wire:click="previousPeriod" variant="ghost" size="sm" icon="chevron-left">
                {{ __('Précédent') }}
            </flux:button>
            <flux:button wire:click="goToToday" variant="ghost" size="sm">
                {{ __('Aujourd\'hui') }}
            </flux:button>
            <flux:button wire:click="nextPeriod" variant="ghost" size="sm" icon="chevron-right">
                {{ __('Suivant') }}
            </flux:button>
            <div class="ml-4 text-lg font-semibold">
                @if ($view === 'month')
                    {{ $currentDate->format('F Y') }}
                @elseif ($view === 'week')
                    {{ $currentDate->copy()->startOfWeek()->format('d/m') }} - {{ $currentDate->copy()->endOfWeek()->format('d/m/Y') }}
                @else
                    {{ $currentDate->format('d/m/Y') }}
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button 
                wire:click="setView('month')" 
                :variant="$view === 'month' ? 'primary' : 'ghost'" 
                size="sm"
            >
                {{ __('Mois') }}
            </flux:button>
            <flux:button 
                wire:click="setView('week')" 
                :variant="$view === 'week' ? 'primary' : 'ghost'" 
                size="sm"
            >
                {{ __('Semaine') }}
            </flux:button>
            <flux:button 
                wire:click="setView('list')" 
                :variant="$view === 'list' ? 'primary' : 'ghost'" 
                size="sm"
            >
                {{ __('Liste') }}
            </flux:button>
        </div>
    </div>

    <!-- Vue Mensuelle -->
    @if ($view === 'month')
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="grid grid-cols-7 gap-px border-b border-neutral-200 bg-neutral-200 dark:border-neutral-700 dark:bg-neutral-700">
                @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $day)
                    <div class="bg-neutral-50 px-4 py-3 text-center text-sm font-semibold text-neutral-700 dark:bg-neutral-900/50 dark:text-neutral-300">
                        {{ $day }}
                    </div>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-px bg-neutral-200 dark:bg-neutral-700">
                @foreach ($this->getRemindersForMonth() as $day)
                    <div class="min-h-24 bg-white p-2 dark:bg-neutral-800 {{ !$day['isCurrentMonth'] ? 'opacity-50' : '' }} {{ $day['isToday'] ? 'ring-2 ring-blue-500' : '' }}">
                        <div class="mb-1 text-sm font-medium {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : 'text-neutral-900 dark:text-neutral-100' }}">
                            {{ $day['date']->format('j') }}
                        </div>
                        <div class="space-y-1">
                            @foreach ($day['reminders']->take(3) as $reminder)
                                <button
                                    wire:click="selectReminder({{ $reminder->id }})"
                                    class="w-full rounded px-1.5 py-0.5 text-left text-xs transition-colors hover:opacity-80 {{ $reminder->isOverdue() ? 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' : ($reminder->isDueSoon() ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400') }}"
                                >
                                    <div class="truncate">{{ $reminder->reminder_date->format('H:i') }}</div>
                                    <div class="truncate font-medium">{{ $reminder->lead->email }}</div>
                                </button>
                            @endforeach
                            @if ($day['reminders']->count() > 3)
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                    +{{ $day['reminders']->count() - 3 }} {{ __('autre(s)') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Vue Hebdomadaire -->
    @if ($view === 'week')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-7">
            @foreach ($this->getRemindersForWeek() as $day)
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800 {{ $day['isToday'] ? 'ring-2 ring-blue-500' : '' }}">
                    <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900/50">
                        <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ match($day['date']->dayOfWeek) {
                                0 => __('Dimanche'),
                                1 => __('Lundi'),
                                2 => __('Mardi'),
                                3 => __('Mercredi'),
                                4 => __('Jeudi'),
                                5 => __('Vendredi'),
                                6 => __('Samedi'),
                                default => $day['date']->format('l')
                            } }}
                        </div>
                        <div class="text-xs text-neutral-600 dark:text-neutral-400">
                            {{ $day['date']->format('d/m/Y') }}
                        </div>
                    </div>
                    <div class="space-y-2 p-4">
                        @forelse ($day['reminders'] as $reminder)
                            <button
                                wire:click="selectReminder({{ $reminder->id }})"
                                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-left transition-colors hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900/50 dark:hover:bg-neutral-900"
                            >
                                <div class="flex items-center gap-2 mb-1">
                                    <flux:badge 
                                        :variant="$reminder->isOverdue() ? 'danger' : ($reminder->isDueSoon() ? 'warning' : 'neutral')" 
                                        size="sm"
                                    >
                                        {{ $reminder->reminder_date->format('H:i') }}
                                    </flux:badge>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $reminder->getTypeLabel() }}
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $reminder->lead->email }}
                                </div>
                                @if ($reminder->notes)
                                    <div class="mt-1 text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2">
                                        {{ $reminder->notes }}
                                    </div>
                                @endif
                            </button>
                        @empty
                            <div class="py-8 text-center">
                                <p class="text-xs text-neutral-400">{{ __('Aucun rappel') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Vue Liste -->
    @if ($view === 'list')
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse ($upcomingReminders as $reminder)
                    <div class="p-4 transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-900/30">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <flux:badge 
                                        :variant="$reminder->isOverdue() ? 'danger' : ($reminder->isDueSoon() ? 'warning' : 'neutral')" 
                                        size="sm"
                                    >
                                        {{ $reminder->getTypeLabel() }}
                                    </flux:badge>
                                    @if ($reminder->isOverdue())
                                        <flux:badge variant="danger" size="sm">
                                            {{ __('En retard') }}
                                        </flux:badge>
                                    @elseif ($reminder->isDueSoon())
                                        <flux:badge variant="warning" size="sm">
                                            {{ __('Bientôt') }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400 mb-1">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>{{ $reminder->reminder_date->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Lead') }}: {{ $reminder->lead->email }}
                                </div>
                                @if ($reminder->notes)
                                    <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $reminder->notes }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button 
                                    wire:click="selectReminder({{ $reminder->id }})"
                                    variant="ghost" 
                                    size="sm"
                                    icon="eye"
                                >
                                    {{ __('Détails') }}
                                </flux:button>
                                <flux:button 
                                    wire:click="completeReminder({{ $reminder->id }})"
                                    variant="ghost" 
                                    size="sm"
                                    icon="check"
                                    wire:loading.attr="disabled"
                                    wire:target="completeReminder({{ $reminder->id }})"
                                >
                                    {{ __('Compléter') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ __('Aucun rappel à venir') }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Les rappels planifiés apparaîtront ici') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Modal de détails du rappel -->
    @if ($selectedReminderId)
        @php
            $reminder = LeadReminder::with('lead')->find($selectedReminderId);
        @endphp
        @if ($reminder)
            <flux:modal wire:model="showReminderDetails" name="reminder-details">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ __('Détails du rappel') }}
                        </h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Type') }}</label>
                            <div class="mt-1">
                                <flux:badge variant="neutral" size="sm">
                                    {{ $reminder->getTypeLabel() }}
                                </flux:badge>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Date et heure') }}</label>
                            <div class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">
                                {{ $reminder->reminder_date->format('d/m/Y H:i') }}
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Lead') }}</label>
                            <div class="mt-1">
                                <flux:button 
                                    href="{{ route('agent.leads.show', $reminder->lead) }}" 
                                    variant="ghost" 
                                    size="sm"
                                    wire:navigate
                                >
                                    {{ $reminder->lead->email }}
                                </flux:button>
                            </div>
                        </div>

                        @if ($reminder->notes)
                            <div>
                                <label class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Notes') }}</label>
                                <div class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">
                                    {{ $reminder->notes }}
                                </div>
                            </div>
                        @endif

                        @if ($reminder->isOverdue())
                            <flux:callout variant="danger" icon="exclamation-triangle">
                                {{ __('Ce rappel est en retard') }}
                            </flux:callout>
                        @elseif ($reminder->isDueSoon())
                            <flux:callout variant="warning" icon="clock">
                                {{ __('Ce rappel arrive bientôt') }}
                            </flux:callout>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <flux:button wire:click="closeReminderDetails" variant="ghost">
                            {{ __('Fermer') }}
                        </flux:button>
                        @if (!$reminder->is_completed)
                            <flux:button 
                                wire:click="completeReminder({{ $reminder->id }})"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:target="completeReminder({{ $reminder->id }})"
                            >
                                <span wire:loading.remove wire:target="completeReminder({{ $reminder->id }})">
                                    {{ __('Marquer comme complété') }}
                                </span>
                                <span wire:loading wire:target="completeReminder({{ $reminder->id }})" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Traitement...') }}
                                </span>
                            </flux:button>
                        @endif
                    </div>
                </div>
            </flux:modal>
        @endif
    @endif
</div>

