<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailWizzImportedLead extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mailwizz_imported_leads';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mailwizz_subscriber_id',
        'lead_id',
        'email',
        'imported_at',
        'mailwizz_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'mailwizz_data' => 'array',
        ];
    }

    /**
     * Get the lead for this imported lead.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Check if a subscriber has already been imported.
     */
    public static function isAlreadyImported(string $subscriberId): bool
    {
        return self::where('mailwizz_subscriber_id', $subscriberId)->exists();
    }

    /**
     * Check if an email already exists in leads table.
     */
    public static function emailExistsInLeads(string $email): bool
    {
        return Lead::where('email', strtolower(trim($email)))->exists();
    }
}
