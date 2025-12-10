<?php

use App\Models\EmailSubject;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Models\Tag;
use App\Services\AgentEmailService;
use App\Services\LeadConfirmationService;
use App\Services\LeadNoteService;
use App\Services\ReminderService;
use App\Services\TagService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Lead $lead;

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

    public bool $showEmailModal = false;

    public ?int $selectedEmailSubjectId = null;

    public string $emailSubject = '';

    public string $emailBody = '';

    public $emailAttachment = null;

    public string $emailEditorMode = 'html';

    public bool $showEmailPreview = false;

    public function mount(Lead $lead): void
    {
        // Vérifier que le lead appartient au centre d'appels de l'owner
        $user = Auth::user();
        if (! $user->isCallCenterOwner() || $lead->call_center_id !== $user->call_center_id) {
            abort(403, 'Vous n\'avez pas accès à ce lead.');
        }

        $this->lead = $lead->load(['form', 'callCenter', 'assignedAgent']);
    }

    public function sendOptInReminder(): void
    {
        // Vérifier que le lead est dans un statut approprié pour recevoir un email de rappel opt-in
        if (! in_array($this->lead->status, ['pending_email', 'email_confirmed'])) {
            session()->flash('optin-error', __('Ce lead n\'est pas dans un statut approprié pour recevoir un email de rappel opt-in.'));
            return;
        }

        $service = app(LeadConfirmationService::class);
        $success = $service->sendConfirmationEmail($this->lead);

        if ($success) {
            session()->flash('optin-message', __('Email de rappel opt-in envoyé avec succès.'));
            $this->dispatch('optin-sent');
        } else {
            session()->flash('optin-error', __('Erreur lors de l\'envoi de l\'email de rappel opt-in. Veuillez vérifier la configuration SMTP.'));
        }
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
        $user = Auth::user();
        if ($note->user_id !== $user->id && ! $user->isSuperAdmin() && ! $user->isCallCenterOwner()) {
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

        $reminderDateTime = Carbon::parse($this->reminderDate.' '.$this->reminderTime);

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

    public function openEmailModal(): void
    {
        $this->showEmailModal = true;
        $this->selectedEmailSubjectId = null;
        $this->emailSubject = '';
        $this->emailBody = '';
        $this->emailAttachment = null;
        $this->emailEditorMode = 'html';
        $this->showEmailPreview = false;
        $this->dispatch('email-modal-opened');
    }

    public function closeEmailModal(): void
    {
        $this->showEmailModal = false;
        $this->selectedEmailSubjectId = null;
        $this->emailSubject = '';
        $this->emailBody = '';
        $this->emailAttachment = null;
        $this->emailEditorMode = 'html';
        $this->showEmailPreview = false;
        $this->dispatch('email-modal-closed');
    }

    public function toggleEmailPreview(): void
    {
        $this->showEmailPreview = ! $this->showEmailPreview;
    }

    public function updatedSelectedEmailSubjectId(): void
    {
        if ($this->selectedEmailSubjectId) {
            $emailSubject = EmailSubject::find($this->selectedEmailSubjectId);
            if ($emailSubject) {
                $this->emailSubject = $emailSubject->subject;
                if ($emailSubject->default_template_html) {
                    $this->emailBody = $emailSubject->default_template_html;
                    $this->dispatch('email-body-updated', content: $this->emailBody);
                }
            }
        }
    }

    public function sendEmail(): void
    {
        $this->validate([
            'emailSubject' => ['required', 'string', 'max:255'],
            'emailBody' => ['required', 'string'],
            'emailAttachment' => ['nullable', 'file', 'max:10240'],
        ], [
            'emailSubject.required' => __('Le sujet de l\'email est requis.'),
            'emailBody.required' => __('Le contenu de l\'email est requis.'),
            'emailAttachment.max' => __('Le fichier ne doit pas dépasser 10 Mo.'),
        ]);

        $service = app(AgentEmailService::class);
        $success = $service->sendEmail(
            $this->lead,
            Auth::user(),
            $this->emailSubject,
            $this->emailBody,
            null,
            $this->selectedEmailSubjectId,
            $this->emailAttachment
        );

        if ($success) {
            $this->closeEmailModal();
            session()->flash('email-message', __('Email envoyé avec succès.'));
            $this->dispatch('email-sent');
        } else {
            session()->flash('email-error', __('Erreur lors de l\'envoi de l\'email. Veuillez réessayer.'));
        }
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

        $emailSubjects = EmailSubject::active()->ordered()->get();

        return [
            'notes' => $notes,
            'reminders' => $reminders,
            'tags' => $tags,
            'availableTags' => $availableTags,
            'emailSubjects' => $emailSubjects,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button
                href="{{ route('owner.leads') }}"
                variant="ghost"
                size="sm"
            >
                ← {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Détails du Lead') }}
            </h1>
        </div>
        <div class="flex items-center gap-2">
            @if (in_array($this->lead->status, ['pending_email', 'email_confirmed']))
                <flux:button 
                    wire:click="sendOptInReminder" 
                    variant="primary" 
                    icon="paper-airplane"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="sendOptInReminder">
                        {{ __('Envoyer rappel opt-in') }}
                    </span>
                    <span wire:loading wire:target="sendOptInReminder" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Envoi...') }}
                    </span>
                </flux:button>
            @endif
            <flux:button wire:click="openEmailModal" variant="primary" icon="envelope">
                {{ __('Envoyer un email') }}
            </flux:button>
        </div>
    </div>

    @if (session('optin-message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('optin-message') }}
        </flux:callout>
    @endif

    @if (session('optin-error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('optin-error') }}
        </flux:callout>
    @endif

    <!-- Informations principales -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Informations du lead') }}
            </h2>
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
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Formulaire') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->form?->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ __('Agent assigné') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-900 dark:text-neutral-100">{{ $this->lead->assignedAgent?->name ?? __('Non assigné') }}</dd>
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
                                        {{ $oldStatus->getLabel() }}
                                    </span>
                                    <span class="text-neutral-400">→</span>
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $newStatus->getColorClass() }}">
                                        {{ $newStatus->getLabel() }}
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

        @if (session('email-message'))
            <flux:callout variant="success" icon="check-circle" class="mb-4">
                {{ session('email-message') }}
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
                        @if ($note->user_id === Auth::id() || Auth::user()->isSuperAdmin() || Auth::user()->isCallCenterOwner())
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

    <!-- Modal d'envoi d'email -->
    @if ($showEmailModal)
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            wire:click="closeEmailModal"
        >
            <div 
                class="relative w-[95vw] h-[95vh] max-w-[95vw] max-h-[95vh] min-w-[95vw] sm:min-w-[600px] md:min-w-[800px] lg:min-w-[1200px] bg-white dark:bg-neutral-800 rounded-lg shadow-xl flex flex-col overflow-hidden m-2 sm:m-4 md:m-6"
                wire:click.stop
            >
                <form wire:submit="sendEmail" class="flex flex-col h-full overflow-y-auto p-4 sm:p-6 space-y-4 sm:space-y-6">
                    <div class="flex items-start sm:items-center justify-between mb-3 sm:mb-4 pb-3 sm:pb-4 border-b border-neutral-200 dark:border-neutral-700 flex-shrink-0">
                        <div class="flex-1 min-w-0 pr-2">
                            <h2 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ __('Envoyer un email') }}
                            </h2>
                            <p class="mt-1 text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 truncate">
                                {{ __('Envoyez un email au prospect : :email', ['email' => $this->lead->email]) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeEmailModal"
                            class="rounded-lg p-1.5 sm:p-2 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 transition-colors flex-shrink-0"
                        >
                            <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if (session('email-error'))
                        <flux:callout variant="danger" icon="exclamation-circle">
                            {{ session('email-error') }}
                        </flux:callout>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Sujet prédéfini (optionnel)') }}</flux:label>
                        <flux:select wire:model.live="selectedEmailSubjectId">
                            <option value="">{{ __('Sélectionnez un sujet...') }}</option>
                            @foreach ($emailSubjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->subject }}</option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ __('Sélectionnez un sujet prédéfini pour pré-remplir le formulaire') }}</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Sujet de l\'email') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model.blur="emailSubject" required autofocus />
                        <flux:error name="emailSubject" />
                    </flux:field>

                    <flux:field>
                        <div class="mb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
                            <flux:label class="mb-0">{{ __('Contenu de l\'email') }} <span class="text-red-500">*</span></flux:label>
                            <div class="flex items-center gap-2 flex-wrap">
                                <flux:button 
                                    type="button" 
                                    wire:click="$set('emailEditorMode', 'visual')"
                                    variant="{{ $emailEditorMode === 'visual' ? 'primary' : 'ghost' }}"
                                    size="sm"
                                >
                                    {{ __('Visuel') }}
                                </flux:button>
                                <flux:button 
                                    type="button" 
                                    wire:click="$set('emailEditorMode', 'html')"
                                    variant="{{ $emailEditorMode === 'html' ? 'primary' : 'ghost' }}"
                                    size="sm"
                                >
                                    {{ __('HTML') }}
                                </flux:button>
                                <flux:button 
                                    type="button" 
                                    wire:click="toggleEmailPreview"
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                    class="hidden sm:inline-flex"
                                >
                                    <span class="hidden md:inline">{{ $showEmailPreview ? __('Masquer la prévisualisation') : __('Prévisualiser') }}</span>
                                    <span class="md:hidden">{{ __('Aperçu') }}</span>
                                </flux:button>
                            </div>
                        </div>
                        
                        @if ($emailEditorMode === 'visual')
                            <div wire:ignore class="email-visual-editor-container w-full flex-1 flex flex-col min-h-0">
                                <textarea id="emailBodyEditor" wire:model="emailBody" class="min-h-[400px] sm:min-h-[500px] md:min-h-[600px] w-full border border-neutral-200 dark:border-neutral-700 rounded-lg"></textarea>
                            </div>
                        @else
                            <flux:textarea wire:model.blur="emailBody" rows="12" required class="min-h-[300px] sm:min-h-[400px] md:min-h-[500px] w-full" />
                        @endif
                        
                        <flux:description>{{ __('Vous pouvez modifier le contenu HTML du template sélectionné') }}</flux:description>
                        <flux:error name="emailBody" />
                    </flux:field>

                    @if ($showEmailPreview)
                        @php
                            $form = $this->lead->form;
                            $smtpProfile = $form?->smtpProfile;
                        @endphp
                        <div class="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                            <h3 class="mb-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Prévisualisation de l\'email') }}</h3>
                            <div class="rounded border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                                <div class="mb-2 border-b border-neutral-200 pb-2 dark:border-neutral-700">
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('De :') }} {{ $smtpProfile?->from_address ?? 'N/A' }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('À :') }} {{ $this->lead->email }}</div>
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Sujet :') }} {{ $emailSubject ?: __('(vide)') }}</div>
                                </div>
                                <div class="email-preview-content prose prose-sm max-w-none dark:prose-invert">
                                    {!! $emailBody ?: '<p class="text-neutral-400 italic">' . __('Aucun contenu') . '</p>' !!}
                                </div>
                            </div>
                        </div>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Pièce jointe (optionnel)') }}</flux:label>
                        <flux:input wire:model="emailAttachment" type="file" />
                        <flux:description>{{ __('Taille maximale : 10 Mo') }}</flux:description>
                        <flux:error name="emailAttachment" />
                    </flux:field>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3 pt-3 sm:pt-4 border-t border-neutral-200 dark:border-neutral-700 flex-shrink-0">
                        <flux:button type="button" wire:click="closeEmailModal" variant="ghost" class="w-full sm:w-auto">
                            {{ __('Annuler') }}
                        </flux:button>
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full sm:w-auto">
                            <span wire:loading.remove wire:target="sendEmail">
                                {{ __('Envoyer l\'email') }}
                            </span>
                            <span wire:loading wire:target="sendEmail" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('Envoi en cours...') }}
                            </span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
        <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
        <script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
        <style>
            /* Styles pour Quill dans le modal */
            .ql-container {
                font-family: Arial, sans-serif;
                font-size: 14px;
                min-height: 400px;
            }
            
            @media (min-width: 640px) {
                .ql-container {
                    min-height: 500px;
                }
            }
            
            @media (min-width: 1024px) {
                .ql-container {
                    min-height: 600px;
                }
            }
            
            @media (min-width: 1280px) {
                .ql-container {
                    min-height: 700px;
                }
            }
            
            .ql-editor {
                min-height: 400px;
            }
            
            @media (min-width: 640px) {
                .ql-editor {
                    min-height: 500px;
                }
            }
            
            @media (min-width: 1024px) {
                .ql-editor {
                    min-height: 600px;
                }
            }
            
            @media (min-width: 1280px) {
                .ql-editor {
                    min-height: 700px;
                }
            }
            
            .dark .ql-toolbar {
                background-color: rgb(31, 41, 55);
                border-color: rgb(55, 65, 81);
            }
            
            .dark .ql-container {
                background-color: rgb(17, 24, 39);
                color: rgb(243, 244, 246);
                border-color: rgb(55, 65, 81);
            }
            
            .dark .ql-editor {
                color: rgb(243, 244, 246);
            }
            
            .dark .ql-stroke {
                stroke: rgb(209, 213, 219);
            }
            
            .dark .ql-fill {
                fill: rgb(209, 213, 219);
            }
            
            .dark .ql-picker-label {
                color: rgb(209, 213, 219);
            }
        </style>
        <script>
            (function() {
                let editorInstance = null;
                let isInitializing = false;

                // Initialize Quill Editor
                function initEmailEditor() {
                    const textarea = document.getElementById('emailBodyEditor');
                    if (!textarea) {
                        return;
                    }
                    
                    // Check if already initialized
                    if (editorInstance || isInitializing) {
                        return;
                    }

                    // Check if Quill is loaded
                    if (typeof Quill === 'undefined') {
                        console.error('Quill is not loaded. Please wait for the library to load.');
                        setTimeout(initEmailEditor, 500);
                        return;
                    }

                    isInitializing = true;
                    const initialContent = textarea.value || '';

                    // Create editor container
                    const editorContainer = document.createElement('div');
                    editorContainer.id = 'quillEditorContainer';
                    textarea.parentNode.insertBefore(editorContainer, textarea);
                    textarea.style.display = 'none';

                    // Initialize Quill
                    editorInstance = new Quill('#quillEditorContainer', {
                        theme: 'snow',
                        modules: {
                            toolbar: [
                                [{ 'header': [1, 2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ 'color': [] }, { 'background': [] }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                [{ 'align': [] }],
                                ['link', 'image'],
                                ['clean'],
                                ['code-block']
                            ]
                        },
                        placeholder: 'Rédigez votre email ici...'
                    });

                    // Set initial content
                    if (initialContent) {
                        // Remove style tags if present (from old GrapesJS content)
                        const cleanContent = initialContent.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
                        editorInstance.root.innerHTML = cleanContent;
                    }

                    // Sync with Livewire on change
                    editorInstance.on('text-change', function() {
                        const html = editorInstance.root.innerHTML;
                        textarea.value = html;
                        @this.set('emailBody', html, false);
                    });

                    // Also sync on selection change (for formatting changes)
                    editorInstance.on('selection-change', function() {
                        const html = editorInstance.root.innerHTML;
                        textarea.value = html;
                        @this.set('emailBody', html, false);
                    });

                    isInitializing = false;
                }

                // Destroy Quill editor
                function destroyEmailEditor() {
                    if (editorInstance) {
                        try {
                            const container = document.getElementById('quillEditorContainer');
                            const textarea = document.getElementById('emailBodyEditor');
                            
                            if (container && textarea) {
                                // Save content back to textarea
                                textarea.value = editorInstance.root.innerHTML;
                                textarea.style.display = '';
                                container.remove();
                            }
                            
                            editorInstance = null;
                        } catch (e) {
                            console.error('Error destroying editor:', e);
                        }
                    }
                    isInitializing = false;
                }

                // Update editor content from Livewire
                function updateEditorContent(content) {
                    if (!editorInstance || !content) return;
                    
                    try {
                        // Remove style tags if present
                        const cleanContent = content.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
                        const currentContent = editorInstance.root.innerHTML;
                        
                        if (currentContent !== cleanContent) {
                            editorInstance.root.innerHTML = cleanContent;
                        }
                    } catch (e) {
                        console.error('Error updating editor content:', e);
                    }
                }

                // Function to check and initialize editor
                function checkAndInitEditor() {
                    const textarea = document.getElementById('emailBodyEditor');
                    if (!textarea) {
                        if (editorInstance) {
                            destroyEmailEditor();
                        }
                        return;
                    }

                    // Check if we're in visual mode by checking if container is visible
                    const container = textarea.closest('.email-visual-editor-container');
                    if (!container) {
                        if (editorInstance) {
                            destroyEmailEditor();
                        }
                        return;
                    }

                    // Check if container is visible
                    const containerStyle = window.getComputedStyle(container);
                    if (containerStyle.display === 'none') {
                        if (editorInstance) {
                            destroyEmailEditor();
                        }
                        return;
                    }

                    // In visual mode, initialize if needed
                    if (!editorInstance && !isInitializing) {
                        initEmailEditor();
                    }
                }

                // Livewire hooks
                document.addEventListener('livewire:init', () => {
                    // Watch for modal opening
                    Livewire.on('email-modal-opened', () => {
                        document.body.style.overflow = 'hidden';
                        
                        // Close modal on Escape key
                        const handleEscape = (e) => {
                            if (e.key === 'Escape') {
                                @this.closeEmailModal();
                                document.removeEventListener('keydown', handleEscape);
                            }
                        };
                        document.addEventListener('keydown', handleEscape);
                        
                        // Initialize editor when modal opens (if in visual mode)
                        setTimeout(() => {
                            checkAndInitEditor();
                        }, 300);
                    });

                    // Watch for modal closing
                    Livewire.on('email-modal-closed', () => {
                        document.body.style.overflow = '';
                        destroyEmailEditor();
                    });

                    // Watch for email body updates from Livewire
                    Livewire.on('email-body-updated', (event) => {
                        if (event.content && editorInstance) {
                            updateEditorContent(event.content);
                        }
                    });

                    // Watch for editor mode changes
                    Livewire.hook('morph.updated', ({ el }) => {
                        setTimeout(() => {
                            checkAndInitEditor();
                        }, 300);
                    });
                });

                // Also check when DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        setTimeout(checkAndInitEditor, 200);
                    });
                } else {
                    setTimeout(checkAndInitEditor, 200);
                }
            })();
        </script>
    @endpush
</div>
