<?php

namespace App\Models;

use App\Events\LeadConverted;
use App\Events\LeadEmailConfirmed;
use App\Events\LeadStatusUpdated;
use App\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    /** @use HasFactory<\Database\Factories\LeadFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'source',
        'data',
        'email',
        'status',
        'status_id',
        'email_confirmed_at',
        'email_confirmation_token',
        'email_confirmation_token_expires_at',
        'assigned_to',
        'call_center_id',
        'call_comment',
        'called_at',
        'score',
        'score_updated_at',
        'score_factors',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'email_confirmed_at' => 'datetime',
            'email_confirmation_token_expires_at' => 'datetime',
            'called_at' => 'datetime',
            'score_updated_at' => 'datetime',
            'score_factors' => 'array',
        ];
    }

    /**
     * Get the form that owns the lead.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the agent assigned to this lead.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the call center for this lead.
     */
    public function callCenter(): BelongsTo
    {
        return $this->belongsTo(CallCenter::class);
    }

    /**
     * Get the notes for this lead.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    /**
     * Get the reminders for this lead.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(LeadReminder::class);
    }

    /**
     * Get the tags for this lead.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'lead_tag')
            ->withPivot('user_id')
            ->withTimestamps();
    }

    /**
     * Get the status for this lead.
     */
    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LeadStatus::class, 'status_id');
    }

    /**
     * Get the score priority level.
     */
    public function getScorePriority(): string
    {
        $score = $this->score ?? 0;

        if ($score >= 80) {
            return 'high';
        } elseif ($score >= 60) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get the score badge color class.
     */
    public function getScoreBadgeColor(): string
    {
        return match ($this->getScorePriority()) {
            'high' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'medium' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
            default => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
        };
    }

    /**
     * Get the score label.
     */
    public function getScoreLabel(): string
    {
        return match ($this->getScorePriority()) {
            'high' => __('Priorité haute'),
            'medium' => __('Priorité moyenne'),
            default => __('Priorité basse'),
        };
    }

    /**
     * Check if email is confirmed.
     */
    public function isEmailConfirmed(): bool
    {
        return $this->email_confirmed_at !== null;
    }

    /**
     * Check if confirmation token is valid.
     */
    public function isConfirmationTokenValid(): bool
    {
        if (! $this->email_confirmation_token) {
            return false;
        }

        if ($this->email_confirmation_token_expires_at && $this->email_confirmation_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get the current status as LeadStatus enum (for backward compatibility).
     */
    public function getStatusEnum(): LeadStatus
    {
        // Try to get from relation first
        if ($this->status_id && $this->relationLoaded('leadStatus') && $this->leadStatus) {
            return LeadStatus::tryFrom($this->leadStatus->slug) ?? LeadStatus::PendingEmail;
        }

        // Fallback to old status column
        return LeadStatus::tryFrom($this->attributes['status'] ?? 'pending_email') ?? LeadStatus::PendingEmail;
    }

    /**
     * Set the status from enum, string, or LeadStatus model.
     */
    public function setStatus(LeadStatus|string|\App\Models\LeadStatus $status): void
    {
        $statusSlug = null;
        
        // If it's a LeadStatus model, use it directly
        if ($status instanceof \App\Models\LeadStatus) {
            $this->status_id = $status->id;
            $statusSlug = $status->slug;
        } elseif ($status instanceof LeadStatus) {
            // If it's an enum, find the corresponding model
            $statusModel = \App\Models\LeadStatus::where('slug', $status->value)->first();
            if ($statusModel) {
                $this->status_id = $statusModel->id;
                $statusSlug = $status->value;
            }
        } elseif (is_string($status)) {
            // If it's a string, try to find by slug
            $statusModel = \App\Models\LeadStatus::where('slug', $status)->first();
            if ($statusModel) {
                $this->status_id = $statusModel->id;
                $statusSlug = $status;
            } else {
                // Fallback: try enum
                $statusEnum = LeadStatus::tryFrom($status);
                if ($statusEnum) {
                    $statusModel = \App\Models\LeadStatus::where('slug', $statusEnum->value)->first();
                    if ($statusModel) {
                        $this->status_id = $statusModel->id;
                        $statusSlug = $statusEnum->value;
                    }
                } else {
                    // Last resort: just set the string
                    $statusSlug = $status;
                }
            }
        }
        
        // Set status using property assignment to ensure Eloquent detects the change
        if ($statusSlug !== null) {
            $this->status = $statusSlug;
        }
    }

    /**
     * Check if the lead has an active status.
     */
    public function isActive(): bool
    {
        // Try to get from relation first
        if ($this->status_id && $this->relationLoaded('leadStatus') && $this->leadStatus) {
            return $this->leadStatus->isActiveStatus();
        }

        // Fallback to enum for backward compatibility
        return $this->getStatusEnum()->isActive();
    }

    /**
     * Check if the lead has a final status.
     */
    public function isFinal(): bool
    {
        // Try to get from relation first
        if ($this->status_id && $this->relationLoaded('leadStatus') && $this->leadStatus) {
            return $this->leadStatus->isFinalStatus();
        }

        // Fallback to enum for backward compatibility
        return $this->getStatusEnum()->isFinal();
    }

    /**
     * Confirm the email.
     */
    public function confirmEmail(): void
    {
        $this->email_confirmed_at = now();
        
        // Set status to email_confirmed - this will trigger the observer
        // Try to get status from model first, fallback to string
        $status = \App\Models\LeadStatus::getBySlug('email_confirmed');
        if ($status) {
            $this->setStatus($status);
        } else {
            // Fallback: set status directly as string if model not found
            $this->status = 'email_confirmed';
        }
        
        // Use save() to trigger Observer
        $this->save();

        // Dispatch LeadEmailConfirmed event for webhooks
        event(new LeadEmailConfirmed($this));

        \Log::info('Lead email confirmed via confirmEmail()', [
            'lead_id' => $this->id,
            'status' => $this->status,
            'status_id' => $this->status_id,
            'call_center_id' => $this->call_center_id,
            'assigned_to' => $this->assigned_to,
        ]);
    }

    /**
     * Mark as pending call.
     */
    public function markAsPendingCall(): void
    {
        // Try to get status from model, fallback to enum
        $status = \App\Models\LeadStatus::getBySlug('pending_call');
        if ($status) {
            $this->setStatus($status);
        } else {
            $this->setStatus(LeadStatus::PendingCall);
        }
        $this->save();
    }

    /**
     * Update status after call.
     */
    public function updateAfterCall(LeadStatus|string|\App\Models\LeadStatus $status, ?string $comment = null): void
    {
        $oldStatus = $this->status;

        // Convert string to model if needed
        if (is_string($status)) {
            $statusModel = \App\Models\LeadStatus::getBySlug($status);
            if (! $statusModel) {
                // Fallback to enum for backward compatibility
                $statusEnum = LeadStatus::tryFrom($status);
                if (! $statusEnum) {
                    throw new \InvalidArgumentException("Invalid status: {$status}");
                }
                // Try to find model from enum
                $statusModel = \App\Models\LeadStatus::getBySlug($statusEnum->value);
                if (! $statusModel) {
                    throw new \InvalidArgumentException("Status model not found for: {$status}");
                }
            }
            $status = $statusModel;
        } elseif ($status instanceof LeadStatus) {
            // Convert enum to model
            $statusModel = \App\Models\LeadStatus::getBySlug($status->value);
            if (! $statusModel) {
                throw new \InvalidArgumentException("Status model not found for enum: {$status->value}");
            }
            $status = $statusModel;
        }

        // Validate that the status can be set after a call
        if (! $status->canBeSetAfterCallStatus()) {
            throw new \InvalidArgumentException("Status {$status->slug} cannot be set after a call");
        }

        $this->setStatus($status);
        $this->called_at = now();
        if ($comment) {
            $this->call_comment = $comment;
        }
        $this->save();

        // Dispatch LeadStatusUpdated event for webhooks
        event(new LeadStatusUpdated($this, $oldStatus, $status->slug));

        // Dispatch LeadConverted event if status is converted
        if ($status->slug === 'converted') {
            event(new LeadConverted($this));
        }

        // Log the status update
        try {
            $auditService = app(\App\Services\AuditService::class);
            $auditService->logLeadStatusUpdated($this, $oldStatus, $status->slug, $comment);
        } catch (\Exception $e) {
            // Silently fail if audit service is not available (e.g., in tests)
        }
    }

    /**
     * Get status history from activity logs.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ActivityLog>
     */
    public function getStatusHistory()
    {
        return \App\Models\ActivityLog::where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->where('action', 'lead.status_updated')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
