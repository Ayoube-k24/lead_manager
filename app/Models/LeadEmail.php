<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadEmail extends Model
{
    /** @use HasFactory<\Database\Factories\LeadEmailFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'email_subject_id',
        'subject',
        'body_html',
        'body_text',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'sent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the lead that owns the email.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who sent the email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the email subject template.
     */
    public function emailSubject(): BelongsTo
    {
        return $this->belongsTo(EmailSubject::class);
    }

    /**
     * Check if the email has an attachment.
     */
    public function hasAttachment(): bool
    {
        return ! empty($this->attachment_path);
    }
}
