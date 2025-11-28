<?php

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Models\Tag;
use App\Services\LeadNoteService;
use App\Services\ReminderService;
use App\Services\TagService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public Lead $lead;

    public string $status = '';

    public string $comment = '';

    public bool $showModal = false;
    public bool $showNoteModal = false;
    public string $noteContent = '';
    public bool $noteIsPrivate = false;
    public string $noteType = 'comment';
    public bool $showReminderModal = false;
    public ?string $reminderDate = null;
    public ?string $reminderTime = null;
    public string $reminderType = 'call_back';
    public ?string $reminderNotes = null;
    public bool $showTagModal = false;
    public string $tagSearch = '';
    public ?int $selectedTagId = null;

    public function mount(Lead $lead): void
    {
        // Vérifier que le lead est attribué à l'agent connecté
        $user = Auth::user();
        if ($lead->assigned_to !== $user->id) {
            abort(403, 'Vous n\'avez pas accès à ce lead.');
        }

        $this->lead = $lead;
        $this->status = $lead->status;
    }

    public function openUpdateModal(): void
    {
        $this->showModal = true;
        // Initialiser avec le statut actuel s'il est valide, sinon utiliser le premier statut post-appel par défaut
        $currentStatus = $this->lead->leadStatus;
        $postCallStatuses = \App\Models\LeadStatus::getPostCallStatuses();
        
        if ($currentStatus && $postCallStatuses->contains('id', $currentStatus->id)) {
            $this->status = $currentStatus->slug;
        } else {
            // Utiliser 'qualified' par défaut pour les nouveaux appels
            $qualifiedStatus = \App\Models\LeadStatus::getBySlug('qualified');
            $this->status = $qualifiedStatus ? $qualifiedStatus->slug : 'qualified';
        }
        
        $this->comment = $this->lead->call_comment ?? '';
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->status = '';
        $this->comment = '';
    }

    public function updateStatus(): void
    {
        // Get valid status slugs from model
        $validStatuses = \App\Models\LeadStatus::getPostCallStatuses()
            ->pluck('slug')
            ->toArray();
        
        $this->validate([
            'status' => ['required', 'in:'.implode(',', $validStatuses)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->lead->updateAfterCall($this->status, $this->comment);

        $this->closeModal();
        $this->dispatch('lead-updated');
    }

    public function getStatusOptionsProperty(): array
    {
        $user = Auth::user();
        
        // Get statuses based on experience level
        if ($user->isBeginner()) {
            // Simplified list for beginners
            $statuses = \App\Models\LeadStatus::getBeginnerStatuses();
        } else {
            // Full list for intermediate and advanced
            $statuses = \App\Models\LeadStatus::getPostCallStatuses();
        }
        
        $options = [];
        foreach ($statuses as $status) {
            $options[$status->slug] = $status->getLabel();
        }
        
        return $options;
    }

    public function getExperienceLevelProperty(): string
    {
        return Auth::user()->experience_level ?? 'beginner';
    }

    public function openNoteModal(): void
    {
        $this->showNoteModal = true;
        $this->noteContent = '';
        $this->noteIsPrivate = false;
        $this->noteType = 'comment';
    }

    public function closeNoteModal(): void
    {
        $this->showNoteModal = false;
        $this->noteContent = '';
        $this->noteIsPrivate = false;
        $this->noteType = 'comment';
    }

    public function addNote(): void
    {
        $this->validate([
            'noteContent' => ['required', 'string', 'min:3', 'max:5000'],
            'noteType' => ['required', 'in:comment,call_log,internal_note'],
        ]);

        $service = app(LeadNoteService::class);
        $service->createNote(
            $this->lead,
            Auth::user(),
            $this->noteContent,
            $this->noteIsPrivate,
            $this->noteType
        );

        $this->closeNoteModal();
        session()->flash('note-message', __('Note ajoutée avec succès.'));
        $this->dispatch('note-added');
    }

    public function deleteNote(LeadNote $note): void
    {
        // Vérifier les permissions
        $user = Auth::user();
        if ($note->user_id !== $user->id && ! $user->isSuperAdmin()) {
            abort(403, __('Vous n\'avez pas la permission de supprimer cette note.'));
        }

        $service = app(LeadNoteService::class);
        $service->deleteNote($note);

        session()->flash('note-message', __('Note supprimée avec succès.'));
        $this->dispatch('note-deleted');
    }

    public function openReminderModal(): void
    {
        $this->showReminderModal = true;
        $this->reminderDate = now()->addDay()->format('Y-m-d');
        $this->reminderTime = '09:00';
        $this->reminderType = 'call_back';
        $this->reminderNotes = null;
    }

    public function closeReminderModal(): void
    {
        $this->showReminderModal = false;
        $this->reminderDate = null;
        $this->reminderTime = null;
        $this->reminderType = 'call_back';
        $this->reminderNotes = null;
    }

    public function createReminder(): void
    {
        $this->validate([
            'reminderDate' => ['required', 'date', 'after_or_equal:today'],
            'reminderTime' => ['required', 'date_format:H:i'],
            'reminderType' => ['required', 'in:call_back,follow_up,appointment'],
            'reminderNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $reminderDateTime = Carbon::parse($this->reminderDate . ' ' . $this->reminderTime);
        
        $service = app(ReminderService::class);
        $service->scheduleReminder(
            $this->lead,
            Auth::user(),
            $reminderDateTime,
            $this->reminderType,
            $this->reminderNotes
        );

        $this->closeReminderModal();
        session()->flash('reminder-message', __('Rappel planifié avec succès.'));
        $this->dispatch('reminder-created');
    }

    public function completeReminder(LeadReminder $reminder): void
    {
        $service = app(ReminderService::class);
        $service->completeReminder($reminder);
        
        session()->flash('reminder-message', __('Rappel marqué comme complété.'));
        $this->dispatch('reminder-completed');
    }

    public function cancelReminder(LeadReminder $reminder): void
    {
        $service = app(ReminderService::class);
        $service->cancelReminder($reminder);
        
        session()->flash('reminder-message', __('Rappel annulé.'));
        $this->dispatch('reminder-cancelled');
    }

    public function openTagModal(): void
    {
        $this->showTagModal = true;
        $this->tagSearch = '';
        $this->selectedTagId = null;
    }

    public function closeTagModal(): void
    {
        $this->showTagModal = false;
        $this->tagSearch = '';
        $this->selectedTagId = null;
    }

    public function attachTag(): void
    {
        $this->validate([
            'selectedTagId' => ['required', 'exists:tags,id'],
        ]);

        $tag = Tag::find($this->selectedTagId);
        $service = app(TagService::class);
        $service->attachTag($this->lead, $tag, Auth::user());

        $this->closeTagModal();
        session()->flash('tag-message', __('Tag ajouté avec succès.'));
        $this->dispatch('tag-attached');
    }

    public function detachTag(Tag $tag): void
    {
        $service = app(TagService::class);
        $service->detachTag($this->lead, $tag);

        session()->flash('tag-message', __('Tag retiré avec succès.'));
        $this->dispatch('tag-detached');
    }

    public function with(): array
    {
        $noteService = app(LeadNoteService::class);
        $notes = $noteService->getNotesForLead($this->lead, Auth::user());

        $reminders = $this->lead->reminders()
            ->with('user')
            ->orderBy('reminder_date')
            ->get();

        $tagService = app(TagService::class);
        $tags = $tagService->getTagsForLead($this->lead);
        $availableTags = Tag::where('name', 'like', "%{$this->tagSearch}%")
            ->orderBy('name')
            ->limit(10)
            ->get();

        return [
            'notes' => $notes,
            'reminders' => $reminders,
            'tags' => $tags,
            'availableTags' => $availableTags,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button
                href="{{ route('agent.leads') }}"
                variant="ghost"
                size="sm"
            >
                ← {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Détails du Lead') }}
            </h1>
        </div>
        @php
            $currentStatus = $this->lead->leadStatus;
            $postCallStatuses = \App\Models\LeadStatus::getPostCallStatuses();
            $canUpdate = ($currentStatus && $currentStatus->isActiveStatus()) || ($currentStatus && $postCallStatuses->contains('id', $currentStatus->id));
        @endphp
        @if ($canUpdate)
            <flux:button wire:click="openUpdateModal" variant="primary">
                {{ __('Mettre à jour le statut') }}
            </flux:button>
        @endif
    </div>

    @php
        $experienceLevel = Auth::user()->experience_level ?? 'beginner';
        $isBeginner = $experienceLevel === 'beginner';
    @endphp

    @if ($isBeginner)
        <!-- Guide de démarrage pour débutants -->
        <div class="rounded-xl border-2 border-green-200 bg-gradient-to-r from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-900/20 dark:to-emerald-900/20">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 rounded-full bg-green-100 p-2 dark:bg-green-900/40">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-green-900 dark:text-green-100">{{ __('Bienvenue ! Guide de démarrage') }}</h3>
                    <p class="mt-2 text-sm text-green-700 dark:text-green-300">
                        {{ __('Vous êtes sur la page de détail d\'un lead. Voici ce que vous devez faire :') }}
                    </p>
                    <ol class="mt-3 space-y-2 text-sm text-green-700 dark:text-green-300">
                        <li class="flex items-start gap-2">
                            <span class="font-semibold">1.</span>
                            <span>{{ __('Lisez les informations du prospect ci-dessous') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-semibold">2.</span>
                            <span>{{ __('Contactez le prospect par téléphone') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-semibold">3.</span>
                            <span>{{ __('Cliquez sur "Mettre à jour le statut" pour enregistrer le résultat de votre appel') }}</span>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    @endif

    <!-- Informations principales -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Informations du lead') }}
                </h2>
                @if ($isBeginner)
                    <flux:tooltip>
                        <flux:button variant="ghost" size="sm">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </flux:button>
                        <flux:tooltip.content>
                            {{ __('Ces informations proviennent du formulaire rempli par le prospect. Utilisez-les pour personnaliser votre appel.') }}
                        </flux:tooltip.content>
                    </flux:tooltip>
                @endif
            </div>
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Email') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Statut') }}</dt>
                    <dd class="mt-1">
                        @php
                            $statusEnum = $this->lead->getStatusEnum();
                        @endphp
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusEnum->colorClass() }}">
                            {{ $statusEnum->label() }}
                        </span>
                    </dd>
                </div>
                @if ($this->lead->score !== null)
                    <div>
                        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Score') }}</dt>
                        <dd class="mt-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold {{ $this->lead->getScoreBadgeColor() }}">
                                    {{ $this->lead->score }}/100
                                </span>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $this->lead->getScoreLabel() }}
                                </span>
                            </div>
                            @if ($this->lead->score_factors)
                                <div class="mt-2 space-y-1">
                                    @foreach ($this->lead->score_factors as $factor => $data)
                                        <div class="text-xs text-neutral-600 dark:text-neutral-400">
                                            <span class="font-medium">{{ config("lead-scoring.factors.{$factor}.label", $factor) }}:</span>
                                            <span>{{ $data['contribution'] ?? 0 }} pts ({{ $data['value'] ?? 0 }}/100 × {{ $data['weight'] ?? 0 }}%)</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Formulaire') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->form?->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Date de création') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                @if ($this->lead->email_confirmed_at)
                    <div>
                        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Email confirmé le') }}</dt>
                        <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->email_confirmed_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                @if ($this->lead->called_at)
                    <div>
                        <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Appelé le') }}</dt>
                        <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->called_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Données du formulaire') }}
            </h2>
            <dl class="space-y-4">
                @foreach ($this->lead->data ?? [] as $key => $value)
                    @if (!empty($value))
                        <div>
                            <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">
                                @if (is_array($value))
                                    {{ json_encode($value) }}
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </div>
    </div>

    <!-- Commentaire d'appel -->
    @if ($this->lead->call_comment)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Commentaire d\'appel') }}
            </h2>
            <p class="text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->call_comment }}</p>
        </div>
    @endif

    <!-- Historique des statuts -->
    @php
        $statusHistory = $this->lead->getStatusHistory();
    @endphp
    @if ($statusHistory->isNotEmpty())
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Historique des statuts') }}
            </h2>
            <div class="space-y-3">
                @foreach ($statusHistory as $log)
                    @php
                        $oldStatus = \App\Models\LeadStatus::getBySlug($log->properties['old_status'] ?? '');
                        $newStatus = \App\Models\LeadStatus::getBySlug($log->properties['new_status'] ?? '');
                    @endphp
                    <div class="flex items-start gap-3 border-b border-neutral-200 pb-3 last:border-0 dark:border-neutral-700">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                @if ($oldStatus && $newStatus)
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $oldStatus->label() }}
                                    </span>
                                    <span class="text-neutral-400">→</span>
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $newStatus->colorClass() }}">
                                        {{ $newStatus->label() }}
                                    </span>
                                @else
                                    <span class="text-sm text-neutral-900 dark:text-neutral-100">
                                        {{ $log->properties['new_status'] ?? 'N/A' }}
                                    </span>
                                @endif
                            </div>
                            @if (!empty($log->properties['comment']))
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $log->properties['comment'] }}
                                </p>
                            @endif
                            <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                                {{ $log->created_at->format('d/m/Y H:i') }}
                                @if ($log->user)
                                    • {{ $log->user->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Notes et Commentaires -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Notes et Commentaires') }}
            </h2>
            <flux:button wire:click="openNoteModal" variant="primary" size="sm" icon="plus">
                {{ __('Ajouter une note') }}
            </flux:button>
        </div>

        @if (session('note-message'))
            <flux:callout variant="success" icon="check-circle" class="mb-4">
                {{ session('note-message') }}
            </flux:callout>
        @endif

        <div class="space-y-4">
            @forelse ($notes as $note)
                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                @if ($note->is_private)
                                    <flux:badge variant="warning" size="sm" icon="lock-closed">
                                        {{ __('Privée') }}
                                    </flux:badge>
                                @endif
                                <flux:badge variant="neutral" size="sm">
                                    @if ($note->type === 'call_log')
                                        {{ __('Journal d\'appel') }}
                                    @elseif ($note->type === 'internal_note')
                                        {{ __('Note interne') }}
                                    @else
                                        {{ __('Commentaire') }}
                                    @endif
                                </flux:badge>
                            </div>
                            <p class="text-sm text-neutral-900 dark:text-neutral-100 whitespace-pre-wrap">{{ $note->content }}</p>
                            <div class="mt-2 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                                <span>{{ $note->user->name }}</span>
                                <span>•</span>
                                <span>{{ $note->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                        @if ($note->user_id === Auth::id() || Auth::user()->isSuperAdmin())
                            <flux:button 
                                wire:click="deleteNote({{ $note->id }})"
                                wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer cette note ?') }}"
                                variant="ghost" 
                                size="sm"
                                icon="trash"
                                class="!text-red-600 dark:!text-red-400"
                            >
                                {{ __('Supprimer') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucune note pour le moment') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Rappels Planifiés -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Rappels Planifiés') }}
            </h2>
            <flux:button wire:click="openReminderModal" variant="primary" size="sm" icon="calendar">
                {{ __('Planifier un rappel') }}
            </flux:button>
        </div>

        @if (session('reminder-message'))
            <flux:callout variant="success" icon="check-circle" class="mb-4">
                {{ session('reminder-message') }}
            </flux:callout>
        @endif

        <div class="space-y-3">
            @forelse ($reminders as $reminder)
                <div class="flex items-start justify-between gap-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
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
                            @if ($reminder->is_completed)
                                <flux:badge variant="success" size="sm">
                                    {{ __('Complété') }}
                                </flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>{{ $reminder->reminder_date->format('d/m/Y H:i') }}</span>
                            @if ($reminder->user)
                                <span>•</span>
                                <span>{{ $reminder->user->name }}</span>
                            @endif
                        </div>
                        @if ($reminder->notes)
                            <p class="mt-2 text-sm text-neutral-900 dark:text-neutral-100">{{ $reminder->notes }}</p>
                        @endif
                    </div>
                    @if (!$reminder->is_completed)
                        <div class="flex items-center gap-2">
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
                            <flux:button 
                                wire:click="cancelReminder({{ $reminder->id }})"
                                wire:confirm="{{ __('Êtes-vous sûr de vouloir annuler ce rappel ?') }}"
                                variant="ghost" 
                                size="sm"
                                icon="x-mark"
                                class="!text-red-600 dark:!text-red-400"
                                wire:loading.attr="disabled"
                                wire:target="cancelReminder({{ $reminder->id }})"
                            >
                                {{ __('Annuler') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun rappel planifié') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Tags -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Tags') }}
            </h2>
            <flux:button wire:click="openTagModal" variant="primary" size="sm" icon="plus">
                {{ __('Ajouter un tag') }}
            </flux:button>
        </div>

        @if (session('tag-message'))
            <flux:callout variant="success" icon="check-circle" class="mb-4">
                {{ session('tag-message') }}
            </flux:callout>
        @endif

        <div class="flex flex-wrap gap-2">
            @forelse ($tags as $tag)
                <div class="group relative inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium" style="background-color: {{ $tag->color }}20; color: {{ $tag->color }};">
                    <span>{{ $tag->name }}</span>
                    <button
                        wire:click="detachTag({{ $tag->id }})"
                        wire:confirm="{{ __('Êtes-vous sûr de vouloir retirer ce tag ?') }}"
                        class="opacity-0 transition-opacity group-hover:opacity-100"
                        type="button"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @empty
                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucun tag assigné') }}</p>
            @endforelse
        </div>
    </div>

    <!-- Modal d'ajout de tag -->
    <flux:modal wire:model="showTagModal" name="add-tag">
        <form wire:submit="attachTag" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Ajouter un tag') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Sélectionnez un tag à associer à ce lead') }}
                </p>
            </div>

            <flux:field>
                <flux:label>{{ __('Rechercher un tag') }}</flux:label>
                <flux:input 
                    wire:model.live.debounce.300ms="tagSearch" 
                    placeholder="{{ __('Tapez pour rechercher...') }}"
                    icon="magnifying-glass"
                />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tag') }}</flux:label>
                <flux:select wire:model="selectedTagId">
                    <option value="">{{ __('Sélectionnez un tag') }}</option>
                    @foreach ($availableTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedTagId" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeTagModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="attachTag">
                        {{ __('Ajouter') }}
                    </span>
                    <span wire:loading wire:target="attachTag" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Ajout...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal de planification de rappel -->
    <flux:modal wire:model="showReminderModal" name="schedule-reminder">
        <form wire:submit="createReminder" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Planifier un rappel') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Planifiez un rappel pour ce lead') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Date') }}</flux:label>
                    <flux:input wire:model="reminderDate" type="date" />
                    <flux:error name="reminderDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Heure') }}</flux:label>
                    <flux:input wire:model="reminderTime" type="time" />
                    <flux:error name="reminderTime" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Type de rappel') }}</flux:label>
                <flux:select wire:model="reminderType">
                    <option value="call_back">{{ __('Rappel') }}</option>
                    <option value="follow_up">{{ __('Suivi') }}</option>
                    <option value="appointment">{{ __('Rendez-vous') }}</option>
                </flux:select>
                <flux:error name="reminderType" />
            </flux:field>

            <flux:field>
                <flux:textarea
                    wire:model="reminderNotes"
                    label="{{ __('Notes (optionnel)') }}"
                    placeholder="{{ __('Ajoutez des notes pour ce rappel...') }}"
                    rows="4"
                />
                <flux:error name="reminderNotes" />
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeReminderModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createReminder">
                        {{ __('Planifier') }}
                    </span>
                    <span wire:loading wire:target="createReminder" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Planification...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal d'ajout de note -->
    <flux:modal wire:model="showNoteModal" name="add-note">
        <form wire:submit="addNote" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Ajouter une note') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Ajoutez une note ou un commentaire sur ce lead') }}
                </p>
            </div>

            <flux:field>
                <flux:label>{{ __('Type de note') }}</flux:label>
                <flux:select wire:model="noteType">
                    <option value="comment">{{ __('Commentaire') }}</option>
                    <option value="call_log">{{ __('Journal d\'appel') }}</option>
                    <option value="internal_note">{{ __('Note interne') }}</option>
                </flux:select>
                <flux:error name="noteType" />
            </flux:field>

            <flux:field>
                <flux:textarea
                    wire:model="noteContent"
                    label="{{ __('Contenu') }}"
                    placeholder="{{ __('Saisissez votre note...') }}"
                    rows="6"
                />
                <flux:error name="noteContent" />
            </flux:field>

            <flux:field>
                <flux:checkbox 
                    wire:model="noteIsPrivate" 
                    label="{{ __('Note privée') }}" 
                />
                <flux:description>{{ __('Les notes privées ne sont visibles que par vous et les administrateurs') }}</flux:description>
            </flux:field>

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeNoteModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="addNote">
                        {{ __('Ajouter') }}
                    </span>
                    <span wire:loading wire:target="addNote" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Ajout...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal de mise à jour -->
    <flux:modal wire:model="showModal" name="update-status">
        <form wire:submit="updateStatus" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Mettre à jour le statut') }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Mettez à jour le statut de ce lead après votre appel téléphonique.') }}
                </p>
            </div>

            @php
                $experienceLevel = $this->experienceLevel;
                $isBeginner = $experienceLevel === 'beginner';
            @endphp

            @if ($isBeginner)
                <!-- Guide pour débutants -->
                <div class="rounded-lg border-2 border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">{{ __('Guide rapide') }}</h3>
                            <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">
                                {{ __('Sélectionnez le statut qui correspond le mieux au résultat de votre appel. Des descriptions détaillées apparaîtront lorsque vous survolerez chaque option.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div>
                <label class="mb-3 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {{ __('Nouveau statut') }} <span class="text-red-500">*</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->statusOptions as $value => $label)
                        @php
                            $statusModel = \App\Models\LeadStatus::getBySlug($value);
                            $isSelected = $status === $value;
                            $description = $statusModel ? $statusModel->description : '';
                        @endphp
                        <div class="relative group">
                            <button
                                type="button"
                                wire:click="$set('status', '{{ $value }}')"
                                class="inline-flex items-center rounded-full px-4 py-2.5 text-sm font-medium transition-all {{ $isSelected ? 'shadow-md ring-2 ring-offset-2 ' . ($statusEnum ? str_replace('bg-', 'ring-', explode(' ', $statusEnum->colorClass())[0]) . ' ' . $statusEnum->colorClass() : 'bg-blue-600 text-white ring-blue-500') : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
                            >
                                {{ $label }}
                                @if ($isSelected)
                                    <svg class="ml-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            </button>
                            @if ($isBeginner && $description)
                                <!-- Tooltip pour débutants -->
                                <div class="absolute bottom-full left-1/2 mb-2 hidden -translate-x-1/2 transform group-hover:block z-10">
                                    <div class="w-64 rounded-lg bg-neutral-900 px-3 py-2 text-xs text-white shadow-lg">
                                        <p class="font-semibold mb-1">{{ $label }}</p>
                                        <p class="text-neutral-300">{{ $description }}</p>
                                        <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-neutral-900"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($isBeginner && $status)
                    @php
                        $selectedStatusModel = \App\Models\LeadStatus::getBySlug($status);
                    @endphp
                    @if ($selectedStatusModel)
                        <div class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-900">
                            <p class="text-xs font-medium text-neutral-700 dark:text-neutral-300">{{ __('Description :') }}</p>
                            <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">{{ $selectedStatusModel->description }}</p>
                        </div>
                    @endif
                @endif
                @error('status')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <flux:textarea
                wire:model="comment"
                :label="__('Commentaire d\'appel')"
                placeholder="{{ $isBeginner ? __('Exemple : Le prospect a demandé un devis pour...') : __('Décrivez le résultat de votre appel...') }}"
                rows="5"
            />
            @if ($isBeginner)
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    💡 {{ __('Astuce : Notez les informations importantes de votre conversation pour faciliter le suivi.') }}
                </p>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeModal" variant="ghost">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Enregistrer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

