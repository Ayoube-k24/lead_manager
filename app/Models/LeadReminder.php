<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadReminder extends Model
{
    /** @use HasFactory<\Database\Factories\LeadReminderFactory> */
    use Auditable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'reminder_date',
        'reminder_type',
        'notes',
        'is_completed',
        'completed_at',
        'notified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reminder_date' => 'datetime',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Get the lead that owns the reminder.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user that created the reminder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include upcoming reminders.
     */
    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->where('reminder_date', '>=', now())
            ->where('reminder_date', '<=', now()->addDays($days))
            ->where('is_completed', false);
    }

    /**
     * Scope a query to only include completed reminders.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope a query to only include pending reminders.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_completed', false)
            ->where('reminder_date', '>=', now());
    }

    /**
     * Scope a query to filter by date.
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('reminder_date', $date);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('reminder_type', $type);
    }

    /**
     * Check if reminder is due soon (within 24 hours).
     */
    public function isDueSoon(): bool
    {
        return $this->reminder_date <= now()->addDay()
            && $this->reminder_date >= now()
            && ! $this->is_completed;
    }

    /**
     * Check if reminder is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->reminder_date < now() && ! $this->is_completed;
    }

    /**
     * Mark reminder as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get reminder type label.
     */
    public function getTypeLabel(): string
    {
        return match ($this->reminder_type) {
            'call_back' => __('Rappel'),
            'follow_up' => __('Suivi'),
            'appointment' => __('Rendez-vous'),
            default => $this->reminder_type,
        };
    }
}
