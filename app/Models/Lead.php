<?php

namespace App\Models;

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
     * Confirm the email.
     */
    public function confirmEmail(): void
    {
        $this->email_confirmed_at = now();
        $this->status = 'email_confirmed';
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
        $this->status = 'pending_call';
        $this->save();
    }

    /**
     * Update status after call.
     */
    public function updateAfterCall(string $status, ?string $comment = null): void
    {
        $oldStatus = $this->status;
        $this->status = $status;
        $this->called_at = now();
        if ($comment) {
            $this->call_comment = $comment;
        }
        $this->save();

        // Log the status update
        try {
            $auditService = app(\App\Services\AuditService::class);
            $auditService->logLeadStatusUpdated($this, $oldStatus, $status, $comment);
        } catch (\Exception $e) {
            // Silently fail if audit service is not available (e.g., in tests)
        }
    }
}
