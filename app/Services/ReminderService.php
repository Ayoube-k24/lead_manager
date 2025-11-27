<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReminderService
{
    /**
     * Schedule a reminder for a lead.
     */
    public function scheduleReminder(
        Lead $lead,
        User $user,
        Carbon $date,
        string $type,
        ?string $notes = null
    ): LeadReminder {
        return LeadReminder::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'reminder_date' => $date,
            'reminder_type' => $type,
            'notes' => $notes,
        ]);
    }

    /**
     * Get upcoming reminders.
     *
     * @return Collection<int, LeadReminder>
     */
    public function getUpcomingReminders(?User $user = null, int $days = 7): Collection
    {
        $query = LeadReminder::query()
            ->with(['lead', 'user'])
            ->upcoming($days)
            ->orderBy('reminder_date');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }

    /**
     * Get reminders for a specific date.
     *
     * @return Collection<int, LeadReminder>
     */
    public function getRemindersForDate(Carbon $date, ?User $user = null): Collection
    {
        $query = LeadReminder::query()
            ->with(['lead', 'user'])
            ->forDate($date)
            ->where('is_completed', false)
            ->orderBy('reminder_date');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }

    /**
     * Mark a reminder as completed.
     */
    public function completeReminder(LeadReminder $reminder): LeadReminder
    {
        $reminder->markAsCompleted();

        return $reminder->fresh();
    }

    /**
     * Cancel a reminder (soft delete or mark as cancelled).
     */
    public function cancelReminder(LeadReminder $reminder): bool
    {
        return $reminder->delete();
    }

    /**
     * Get reminders that need notification (within 24 hours and not yet notified).
     *
     * @return Collection<int, LeadReminder>
     */
    public function getRemindersToNotify(): Collection
    {
        return LeadReminder::query()
            ->with(['lead', 'user'])
            ->where('is_completed', false)
            ->where('reminder_date', '<=', now()->addDay())
            ->where('reminder_date', '>=', now())
            ->where(function ($query) {
                $query->whereNull('notified_at')
                    ->orWhere('notified_at', '<', now()->subHour());
            })
            ->orderBy('reminder_date')
            ->get();
    }

    /**
     * Mark reminder as notified.
     */
    public function markAsNotified(LeadReminder $reminder): void
    {
        $reminder->update(['notified_at' => now()]);
    }

    /**
     * Get overdue reminders.
     *
     * @return Collection<int, LeadReminder>
     */
    public function getOverdueReminders(?User $user = null): Collection
    {
        $query = LeadReminder::query()
            ->with(['lead', 'user'])
            ->where('is_completed', false)
            ->where('reminder_date', '<', now())
            ->orderBy('reminder_date');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }
}
