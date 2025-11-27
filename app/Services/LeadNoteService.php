<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadNoteService
{
    /**
     * Create a note for a lead.
     */
    public function createNote(
        Lead $lead,
        User $user,
        string $content,
        bool $isPrivate = false,
        ?string $type = null,
        ?array $attachments = null
    ): LeadNote {
        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'content' => $content,
            'is_private' => $isPrivate,
            'type' => $type ?? 'comment',
            'attachments' => $attachments,
        ]);

        // Recalculate lead score if configured
        if (config('lead-scoring.auto_recalculate.on_note_added', true)) {
            try {
                $scoringService = app(LeadScoringService::class);
                $scoringService->updateScore($lead->fresh());
            } catch (\Exception $e) {
                // Log error but don't fail note creation
                \Log::error('Error recalculating score after note creation', [
                    'lead_id' => $lead->id,
                    'note_id' => $note->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $note;
    }

    /**
     * Update a note.
     */
    public function updateNote(LeadNote $note, string $content): LeadNote
    {
        $note->update(['content' => $content]);

        return $note->fresh();
    }

    /**
     * Delete a note.
     */
    public function deleteNote(LeadNote $note): bool
    {
        return $note->delete();
    }

    /**
     * Get notes for a lead, filtered by user permissions.
     *
     * @return Collection<int, LeadNote>
     */
    public function getNotesForLead(Lead $lead, ?User $user = null): Collection
    {
        $query = LeadNote::where('lead_id', $lead->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filter private notes based on user permissions
        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere(function ($subQ) use ($user) {
                        $subQ->where('is_private', true)
                            ->where(function ($privateQ) use ($user) {
                                $privateQ->where('user_id', $user->id);

                                // Super admins can see all private notes
                                if ($user->isSuperAdmin()) {
                                    $privateQ->orWhereRaw('1 = 1');
                                }

                                // Supervisors can see private notes from their agents
                                if ($user->isSupervisor()) {
                                    $agentIds = $user->supervisedAgents()->pluck('id');
                                    if ($agentIds->isNotEmpty()) {
                                        $privateQ->orWhereIn('user_id', $agentIds);
                                    }
                                }

                                // Call center owners can see private notes from their center
                                if ($user->isCallCenterOwner() && $lead->call_center_id) {
                                    $centerUserIds = \App\Models\User::where('call_center_id', $lead->call_center_id)
                                        ->pluck('id');
                                    if ($centerUserIds->isNotEmpty()) {
                                        $privateQ->orWhereIn('user_id', $centerUserIds);
                                    }
                                }
                            });
                    });
            });
        } else {
            // If no user, only show public notes
            $query->where('is_private', false);
        }

        return $query->get();
    }

    /**
     * Get notes by type for a lead.
     *
     * @return Collection<int, LeadNote>
     */
    public function getNotesByType(Lead $lead, string $type, ?User $user = null): Collection
    {
        return $this->getNotesForLead($lead, $user)
            ->where('type', $type);
    }
}
