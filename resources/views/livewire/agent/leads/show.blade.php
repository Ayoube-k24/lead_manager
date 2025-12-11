<?php

use App\Models\EmailSubject;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadReminder;
use App\Models\Tag;
use App\Services\AgentEmailService;
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

    public bool $showEmailModal = false;

    public ?int $selectedEmailSubjectId = null;

    public string $emailSubject = '';

    public string $emailBody = '';

    public $emailAttachment = null;

    public string $emailEditorMode = 'html'; // 'html' or 'visual'

    public bool $showEmailPreview = false;

    public function updatedEmailEditorMode(): void
    {
        if ($this->emailEditorMode === 'visual') {
            $this->dispatch('email-editor-mode-changed', mode: 'visual');
        }
    }

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
        $this->emailEditorMode = 'visual'; // Default to visual editor
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
                    // Dispatch event to update TinyMCE if in visual mode
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
            'emailAttachment' => ['nullable', 'file', 'max:10240'], // 10MB max
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

        // Get SMTP profile from lead's form
        $form = $this->lead->form;
        $smtpProfile = $form?->smtpProfile;

        return [
            'notes' => $notes,
            'reminders' => $reminders,
            'tags' => $tags,
            'availableTags' => $availableTags,
            'emailSubjects' => $emailSubjects,
            'smtpProfile' => $smtpProfile,
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
            // Permettre la mise à jour si le statut est actif, post-appel, ou si l'agent veut simplement changer le statut
            $canUpdate = true; // Les agents peuvent toujours mettre à jour les statuts de leurs leads
        @endphp
        <div class="flex items-center gap-2">
            <flux:button wire:click="openEmailModal" variant="primary" icon="envelope">
                {{ __('Envoyer un email') }}
            </flux:button>
            <flux:button wire:click="openUpdateModal" variant="primary" icon="arrow-path">
                {{ __('Mettre à jour le statut') }}
            </flux:button>
        </div>
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
                            <span>{{ __('Cliquez sur le bouton "Mettre à jour le statut" en haut à droite pour enregistrer le résultat de votre appel') }}</span>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    @else
        <!-- Aide rapide pour les agents expérimentés -->
        @php
            $currentStatus = $this->lead->leadStatus;
            $isPendingCall = $currentStatus && $currentStatus->slug === 'pending_call';
        @endphp
        @if ($isPendingCall)
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>{{ __('Astuce :') }}</strong> {{ __('Ce lead est en attente d\'appel. Après avoir contacté le prospect, utilisez le bouton "Mettre à jour le statut" en haut à droite pour enregistrer le résultat.') }}
                    </p>
                </div>
            </div>
        @endif
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
                            $colorClass = $statusModel ? $statusModel->getColorClass() : 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
                        @endphp
                        <div class="relative group">
                            <button
                                type="button"
                                wire:click="$set('status', '{{ $value }}')"
                                class="inline-flex items-center rounded-full px-4 py-2.5 text-sm font-medium transition-all {{ $isSelected ? 'shadow-md ring-2 ring-offset-2 ' . str_replace('bg-', 'ring-', explode(' ', $colorClass)[0]) . ' ' . $colorClass : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700' }}"
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

    <!-- Modal d'envoi d'email - Modal personnalisé pour contrôle total de la taille -->
    @if ($showEmailModal)
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-2 sm:p-4"
            wire:click="closeEmailModal"
            wire:key="email-modal-backdrop"
        >
            <div 
                class="relative w-full h-full sm:w-[95vw] sm:h-[95vh] sm:max-w-[95vw] sm:max-h-[95vh] md:max-w-[90vw] lg:max-w-[85vw] xl:max-w-[1400px] bg-white dark:bg-neutral-800 rounded-lg shadow-xl flex flex-col overflow-hidden"
                wire:click.stop
                wire:key="email-modal-content"
            >
                <form wire:submit="sendEmail" class="flex flex-col h-full overflow-hidden">
                    <!-- En-tête du modal avec bouton de fermeture - Fixe en haut -->
                    <div class="flex items-start sm:items-center justify-between p-4 sm:p-6 pb-3 sm:pb-4 border-b border-neutral-200 dark:border-neutral-700 flex-shrink-0">
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
                    
                    <!-- Contenu scrollable -->
                    <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 sm:space-y-6">

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

            <!-- Informations SMTP -->
            @php
                $form = $this->lead->form;
                $smtpProfile = $form?->smtpProfile;
            @endphp
            @if ($smtpProfile)
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-700 dark:bg-blue-900/20">
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium text-blue-900 dark:text-blue-100">{{ __('SMTP utilisé :') }}</span>
                        <span class="text-blue-700 dark:text-blue-300">{{ $smtpProfile->name }} ({{ $smtpProfile->from_address }})</span>
                    </div>
                    <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                        {{ __('L\'email sera envoyé via le profil SMTP du formulaire rempli par le lead.') }}
                    </p>
                </div>
            @endif

            <flux:field class="flex-1 flex flex-col min-h-0">
                <div class="mb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
                    <flux:label class="mb-0">{{ __('Contenu de l\'email') }} <span class="text-red-500">*</span></flux:label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <flux:button 
                            type="button" 
                            wire:click="$set('emailEditorMode', 'visual')"
                            variant="{{ $emailEditorMode === 'visual' ? 'primary' : 'ghost' }}"
                            size="sm"
                        >
                            {{ __('Éditeur Email') }}
                        </flux:button>
                        <flux:button 
                            type="button" 
                            wire:click="$set('emailEditorMode', 'html')"
                            variant="{{ $emailEditorMode === 'html' ? 'primary' : 'ghost' }}"
                            size="sm"
                        >
                            {{ __('Code HTML') }}
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
                        <div wire:ignore class="email-visual-editor-container w-full flex-1 flex flex-col min-h-[400px] sm:min-h-[500px] md:min-h-[600px]">
                            <div id="emailBodyEditor" class="w-full flex-1 border border-neutral-200 dark:border-neutral-700 rounded-lg" style="min-height: 400px;"></div>
                            <textarea id="emailBodyHidden" wire:model="emailBody" class="hidden"></textarea>
                        </div>
                    @else
                        <flux:textarea wire:model.blur="emailBody" rows="12" required class="min-h-[300px] sm:min-h-[400px] md:min-h-[500px] w-full" />
                    @endif
                
                <flux:description>{{ __('Utilisez l\'éditeur email pour créer des emails compatibles avec tous les clients email') }}</flux:description>
                <flux:error name="emailBody" />
            </flux:field>

            @if ($showEmailPreview)
                <div class="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                    <h3 class="mb-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Prévisualisation de l\'email') }}</h3>
                    <div class="rounded border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <div class="mb-2 border-b border-neutral-200 pb-2 dark:border-neutral-700">
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('De :') }} {{ $smtpProfile?->from_address ?? 'N/A' }}</div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('À :') }} {{ $this->lead->email }}</div>
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Sujet :') }} {{ $emailSubject ?: __('(vide)') }}</div>
                        </div>
                        <div class="email-preview-content max-w-none overflow-auto">
                            @if ($emailBody)
                                {!! $emailBody !!}
                            @else
                                <p class="text-neutral-400 italic">{{ __('Aucun contenu') }}</p>
                            @endif
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
                    </div>
                    
                    <!-- Boutons fixes en bas -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3 p-4 sm:p-6 pt-3 sm:pt-4 border-t border-neutral-200 dark:border-neutral-700 flex-shrink-0 bg-white dark:bg-neutral-800">
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
        <!-- GrapesJS CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.7/dist/css/grapes.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs-preset-newsletter@1.0.12/dist/grapesjs-preset-newsletter.min.css">
        
        <!-- GrapesJS JS - Load with proper sequencing -->
        <script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.7"></script>
        <script>
            // Wait for GrapesJS to load, then load preset
            (function() {
                function loadPreset() {
                    if (typeof grapesjs !== 'undefined') {
                        // Check if preset script already exists
                        if (document.querySelector('script[src*="grapesjs-preset-newsletter"]')) {
                            console.log('GrapesJS preset script already exists');
                            window.grapesjsPresetLoaded = true;
                            window.dispatchEvent(new Event('grapesjs-preset-ready'));
                            return;
                        }
                        
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/grapesjs-preset-newsletter@1.0.12';
                        script.async = false; // Load synchronously to ensure order
                        script.onload = function() {
                            console.log('GrapesJS preset newsletter loaded successfully');
                            window.grapesjsPresetLoaded = true;
                            // Dispatch event to notify that preset is ready
                            window.dispatchEvent(new Event('grapesjs-preset-ready'));
                        };
                        script.onerror = function() {
                            console.error('Failed to load GrapesJS preset newsletter');
                            // Try to continue anyway after a delay
                            setTimeout(function() {
                                window.grapesjsPresetLoaded = true;
                                window.dispatchEvent(new Event('grapesjs-preset-ready'));
                            }, 1000);
                        };
                        document.head.appendChild(script);
                    } else {
                        setTimeout(loadPreset, 100);
                    }
                }
                
                // Start loading preset when GrapesJS script loads
                if (typeof grapesjs !== 'undefined') {
                    loadPreset();
                } else {
                    // Wait for script to load
                    const checkGrapesJS = setInterval(function() {
                        if (typeof grapesjs !== 'undefined') {
                            clearInterval(checkGrapesJS);
                            loadPreset();
                        }
                    }, 100);
                    
                    // Timeout after 10 seconds
                    setTimeout(function() {
                        clearInterval(checkGrapesJS);
                        if (typeof grapesjs !== 'undefined') {
                            loadPreset();
                        }
                    }, 10000);
                }
            })();
        </script>
        
        <style>
            /* Styles pour GrapesJS dans le modal */
            .email-visual-editor-container {
                position: relative;
                width: 100%;
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                min-height: 400px;
            }
            
            @media (min-width: 640px) {
                .email-visual-editor-container {
                    min-height: 500px;
                }
            }
            
            @media (min-width: 1024px) {
                .email-visual-editor-container {
                    min-height: 600px;
                }
            }
            
            #emailBodyEditor {
                background: white;
                width: 100%;
                flex: 1 1 auto;
                display: block !important;
                min-height: 400px;
            }
            
            @media (min-width: 640px) {
                #emailBodyEditor {
                    min-height: 500px;
                }
            }
            
            @media (min-width: 1024px) {
                #emailBodyEditor {
                    min-height: 600px;
                }
            }
            
            .dark #emailBodyEditor {
                background: rgb(31, 41, 55);
            }
            
            /* Personnalisation du thème GrapesJS pour le dark mode */
            .dark .gjs-editor {
                background-color: rgb(31, 41, 55);
                color: rgb(243, 244, 246);
            }
            
            .dark .gjs-pn-panels {
                background-color: rgb(17, 24, 39);
                border-color: rgb(55, 65, 81);
            }
            
            .dark .gjs-pn-btn {
                color: rgb(209, 213, 219);
            }
            
            .dark .gjs-pn-btn:hover {
                background-color: rgb(55, 65, 81);
            }
            
            .dark .gjs-cv-canvas {
                background-color: rgb(31, 41, 55);
            }
            
            .dark .gjs-sm-sector {
                background-color: rgb(17, 24, 39);
                border-color: rgb(55, 65, 81);
            }
            
            .dark .gjs-sm-property {
                background-color: rgb(31, 41, 55);
                border-color: rgb(55, 65, 81);
            }
            
            .dark .gjs-sm-property input,
            .dark .gjs-sm-property select {
                background-color: rgb(31, 41, 55);
                color: rgb(243, 244, 246);
                border-color: rgb(55, 65, 81);
            }
            
            /* Ajuster la hauteur de l'éditeur GrapesJS */
            .gjs-editor {
                min-height: 400px;
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            
            @media (min-width: 640px) {
                .gjs-editor {
                    min-height: 500px;
                }
            }
            
            @media (min-width: 1024px) {
                .gjs-editor {
                    min-height: 600px;
                }
            }
            
            /* Responsive adjustments for modal */
            @media (max-width: 640px) {
                .email-visual-editor-container {
                    min-height: 350px;
                }
                
                #emailBodyEditor {
                    min-height: 350px;
                }
                
                .gjs-editor {
                    min-height: 350px;
                }
            }
        </style>
        <script>
            (function() {
                let editorInstance = null;
                let isInitializing = false;
                let lastContent = '';

                // Initialize GrapesJS Email Editor
                function initEmailEditor() {
                    const container = document.getElementById('emailBodyEditor');
                    const hiddenTextarea = document.getElementById('emailBodyHidden');
                    
                    if (!container) {
                        console.log('GrapesJS: Container not found');
                        return;
                    }
                    
                    // Check if already initialized
                    if (editorInstance || isInitializing) {
                        console.log('GrapesJS: Already initialized or initializing');
                        return;
                    }

                    // Check if container is visible
                    const containerStyle = window.getComputedStyle(container);
                    if (containerStyle.display === 'none' || container.offsetParent === null) {
                        console.log('GrapesJS: Container is not visible');
                        return;
                    }

                    // Check if GrapesJS is loaded
                    if (typeof grapesjs === 'undefined') {
                        console.log('GrapesJS: Library not loaded yet, retrying...');
                        setTimeout(initEmailEditor, 500);
                        return;
                    }

                    // Wait for preset to be loaded (with timeout)
                    if (!window.grapesjsPresetLoaded) {
                        console.log('GrapesJS: Preset not loaded yet, waiting...');
                        let timeoutId = setTimeout(() => {
                            console.warn('GrapesJS: Preset loading timeout, trying to initialize anyway...');
                            window.grapesjsPresetLoaded = true; // Force continue
                            initEmailEditor();
                        }, 5000); // 5 second timeout
                        
                        const presetReadyHandler = () => {
                            clearTimeout(timeoutId);
                            window.removeEventListener('grapesjs-preset-ready', presetReadyHandler);
                            setTimeout(initEmailEditor, 200);
                        };
                        window.addEventListener('grapesjs-preset-ready', presetReadyHandler);
                        return;
                    }

                    console.log('GrapesJS: Initializing editor...');
                    isInitializing = true;
                    const initialContent = hiddenTextarea ? hiddenTextarea.value || '' : '';

                    try {
                        // Get container height - use a calculated height based on viewport
                        const containerRect = container.getBoundingClientRect();
                        const viewportHeight = window.innerHeight;
                        // Use 60% of viewport height, with min 400px and max 800px
                        const calculatedHeight = Math.max(400, Math.min(800, viewportHeight * 0.6));
                        const containerHeight = containerRect.height > 0 ? containerRect.height : calculatedHeight;
                        
                        // Try to initialize with preset, fallback to basic if preset fails
                        let plugins = ['gjs-preset-newsletter'];
                        let pluginsOpts = {
                            'gjs-preset-newsletter': {
                                modalLabelImport: '{{ __('Importez votre template') }}',
                                modalLabelExport: '{{ __('Exportez votre template') }}',
                            },
                        };
                        
                        // Initialize GrapesJS with newsletter preset
                        editorInstance = grapesjs.init({
                            container: '#emailBodyEditor',
                            height: containerHeight + 'px',
                            width: '100%',
                            plugins: plugins,
                            pluginsOpts: pluginsOpts,
                            storageManager: {
                                type: 'local',
                                autosave: false,
                                autoload: false,
                            },
                            canvas: {
                                styles: [],
                            },
                            deviceManager: {
                                devices: [
                                    {
                                        name: 'Desktop',
                                        width: '',
                                    },
                                    {
                                        name: 'Tablet',
                                        width: '768px',
                                        widthMedia: '992px',
                                    },
                                    {
                                        name: 'Mobile',
                                        width: '320px',
                                        widthMedia: '768px',
                                    },
                                ],
                            },
                        });
                        
                        // Force editor to be visible
                        const editorEl = document.querySelector('#emailBodyEditor');
                        if (editorEl) {
                            editorEl.style.display = 'block';
                            editorEl.style.visibility = 'visible';
                            editorEl.style.opacity = '1';
                        }

                        console.log('GrapesJS: Editor initialized successfully');

                        // Load initial content if provided
                        if (initialContent) {
                            loadContentIntoEditor(initialContent);
                        }

                        // Sync with Livewire on change
                        editorInstance.on('update', syncToLivewire);
                        editorInstance.on('component:update', syncToLivewire);
                        editorInstance.on('component:add', syncToLivewire);
                        editorInstance.on('component:remove', syncToLivewire);
                        editorInstance.on('style:update', syncToLivewire);

                        isInitializing = false;
                    } catch (e) {
                        console.error('Error initializing GrapesJS editor:', e);
                        isInitializing = false;
                    }
                }

                // Load content into GrapesJS editor
                function loadContentIntoEditor(content) {
                    if (!editorInstance || !content) {
                        return;
                    }

                    try {
                        // Try to parse style and HTML separately
                        const styleMatch = content.match(/<style[^>]*>([\s\S]*?)<\/style>/);
                        const htmlMatch = content.match(/<\/style>([\s\S]*)$/) || content.match(/^([\s\S]*)$/);

                        if (styleMatch && htmlMatch) {
                            editorInstance.setComponents(htmlMatch[1].trim());
                            editorInstance.setStyle(styleMatch[1]);
                        } else {
                            // If no style tag, try to load as HTML
                            editorInstance.setComponents(content);
                        }

                        lastContent = getEditorContent();
                    } catch (error) {
                        console.error('Error loading content into editor:', error);
                        // Fallback: wrap content in a div
                        try {
                            editorInstance.setComponents('<div>' + content + '</div>');
                        } catch (e) {
                            console.error('Error in fallback content loading:', e);
                        }
                    }
                }

                // Get content from GrapesJS editor
                function getEditorContent() {
                    if (!editorInstance) {
                        return '';
                    }

                    try {
                        const html = editorInstance.getHtml();
                        const css = editorInstance.getCss();
                        return '<style>' + css + '</style>' + html;
                    } catch (e) {
                        console.error('Error getting editor content:', e);
                        return '';
                    }
                }

                // Sync editor content to Livewire
                function syncToLivewire() {
                    if (!editorInstance) {
                        return;
                    }

                    const content = getEditorContent();

                    if (content !== lastContent) {
                        lastContent = content;
                        const hiddenTextarea = document.getElementById('emailBodyHidden');
                        if (hiddenTextarea) {
                            hiddenTextarea.value = content;
                        }
                        @this.set('emailBody', content, false);
                    }
                }

                // Destroy GrapesJS editor
                function destroyEmailEditor() {
                    if (editorInstance) {
                        try {
                            // Save content before destroying
                            const content = getEditorContent();
                            const hiddenTextarea = document.getElementById('emailBodyHidden');
                            if (hiddenTextarea) {
                                hiddenTextarea.value = content;
                            }
                            
                            editorInstance.destroy();
                            editorInstance = null;
                            lastContent = '';
                        } catch (e) {
                            console.error('Error destroying editor:', e);
                        }
                    }
                    isInitializing = false;
                }

                // Update editor content from Livewire
                function updateEditorContent(content) {
                    if (!editorInstance || !content) {
                        return;
                    }
                    
                    try {
                        const currentContent = getEditorContent();
                        if (currentContent !== content) {
                            loadContentIntoEditor(content);
                            lastContent = getEditorContent();
                        }
                    } catch (e) {
                        console.error('Error updating editor content:', e);
                    }
                }

                // Function to check and initialize editor
                function checkAndInitEditor() {
                    const container = document.getElementById('emailBodyEditor');
                    if (!container) {
                        if (editorInstance) {
                            destroyEmailEditor();
                        }
                        return;
                    }

                    // Check if we're in visual mode by checking if container is visible
                    const parentContainer = container.closest('.email-visual-editor-container');
                    if (!parentContainer) {
                        if (editorInstance) {
                            destroyEmailEditor();
                        }
                        return;
                    }

                    // Check if container is visible
                    const containerStyle = window.getComputedStyle(parentContainer);
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
                        
                        // Initialize editor when modal opens (wait for modal to be fully rendered)
                        // Use multiple attempts to ensure editor initializes
                        setTimeout(() => {
                            checkAndInitEditor();
                        }, 300);
                        
                        setTimeout(() => {
                            checkAndInitEditor();
                        }, 800);
                        
                        setTimeout(() => {
                            checkAndInitEditor();
                        }, 1500);
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
                        // Check if the email editor container is in the updated element
                        if (el.querySelector && (el.querySelector('#emailBodyEditor') || el.id === 'emailBodyEditor')) {
                            setTimeout(() => {
                                checkAndInitEditor();
                            }, 500);
                        }
                    });

                    // Watch for editor mode changes via Livewire event
                    Livewire.on('email-editor-mode-changed', (event) => {
                        if (event.mode === 'visual') {
                            setTimeout(() => {
                                checkAndInitEditor();
                            }, 800);
                        } else {
                            destroyEmailEditor();
                        }
                    });
                    
                    // Also listen for preset ready event
                    window.addEventListener('grapesjs-preset-ready', () => {
                        setTimeout(() => {
                            checkAndInitEditor();
                        }, 300);
                    });
                });

                // Also check when DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        setTimeout(checkAndInitEditor, 500);
                    });
                } else {
                    setTimeout(checkAndInitEditor, 500);
                }
            })();
        </script>
    @endpush
</div>

