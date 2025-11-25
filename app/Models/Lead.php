<?php

namespace App\Models;

use App\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'data',
        'email',
        'status',
        'email_confirmed_at',
        'email_confirmation_token',
        'email_confirmation_token_expires_at',
        'assigned_to',
        'call_center_id',
        'call_comment',
        'called_at',
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
     * Get the current status as LeadStatus enum.
     */
    public function getStatusEnum(): LeadStatus
    {
        return LeadStatus::tryFrom($this->attributes['status'] ?? 'pending_email') ?? LeadStatus::PendingEmail;
    }

    /**
     * Set the status from enum or string.
     */
    public function setStatus(LeadStatus|string $status): void
    {
        if ($status instanceof LeadStatus) {
            $this->attributes['status'] = $status->value;
        } else {
            $statusEnum = LeadStatus::tryFrom($status);
            if ($statusEnum) {
                $this->attributes['status'] = $statusEnum->value;
            } else {
                $this->attributes['status'] = $status;
            }
        }
    }

    /**
     * Check if the lead has an active status.
     */
    public function isActive(): bool
    {
        return $this->getStatusEnum()->isActive();
    }

    /**
     * Check if the lead has a final status.
     */
    public function isFinal(): bool
    {
        return $this->getStatusEnum()->isFinal();
    }

    /**
     * Confirm the email.
     */
    public function confirmEmail(): void
    {
        $this->email_confirmed_at = now();
        $this->setStatus(LeadStatus::EmailConfirmed);
        // Use save() to trigger Observer
        $this->save();

        \Log::info('Lead email confirmed via confirmEmail()', [
            'lead_id' => $this->id,
            'status' => $this->status,
            'call_center_id' => $this->call_center_id,
            'assigned_to' => $this->assigned_to,
        ]);
    }

    /**
     * Mark as pending call.
     */
    public function markAsPendingCall(): void
    {
        $this->setStatus(LeadStatus::PendingCall);
        $this->save();
    }

    /**
     * Update status after call.
     */
    public function updateAfterCall(LeadStatus|string $status, ?string $comment = null): void
    {
        $oldStatus = $this->status;

        // Convert string to enum if needed
        if (is_string($status)) {
            $statusEnum = LeadStatus::tryFrom($status);
            if (! $statusEnum) {
                throw new \InvalidArgumentException("Invalid status: {$status}");
            }
            $status = $statusEnum;
        }

        // Validate that the status can be set after a call
        if (! $status->canBeSetAfterCall()) {
            throw new \InvalidArgumentException("Status {$status->value} cannot be set after a call");
        }

        $this->setStatus($status);
        $this->called_at = now();
        if ($comment) {
            $this->call_comment = $comment;
        }
        $this->save();

        // Log the status update
        try {
            $auditService = app(\App\Services\AuditService::class);
            $auditService->logLeadStatusUpdated($this, $oldStatus, $status->value, $comment);
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
