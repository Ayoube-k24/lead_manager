<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use Auditable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'name',
        'description',
        'fields',
        'smtp_profile_id',
        'email_template_id',
        'is_active',
    ];

    /**
     * Bootstrap the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Form $form): void {
            if (! $form->uid) {
                $form->uid = static::generateUid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the SMTP profile for this form.
     */
    public function smtpProfile(): BelongsTo
    {
        return $this->belongsTo(SmtpProfile::class);
    }

    /**
     * Get the email template for this form.
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * Get the leads for this form.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Generate a unique 12-character UID.
     */
    public static function generateUid(): string
    {
        do {
            $uid = Str::upper(Str::random(12));
        } while (static::where('uid', $uid)->exists());

        return $uid;
    }
}
