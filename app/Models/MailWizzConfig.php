<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class MailWizzConfig extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mailwizz_configs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'api_url',
        'public_key',
        'private_key',
        'list_uid',
        'call_center_id',
        'import_frequency',
        'is_active',
        'last_import_at',
        'last_import_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'import_frequency' => 'integer',
            'last_import_at' => 'datetime',
            'last_import_count' => 'integer',
        ];
    }

    /**
     * Get the call center for this config.
     */
    public function callCenter(): BelongsTo
    {
        return $this->belongsTo(CallCenter::class);
    }

    /**
     * Set the private key attribute (encrypt it).
     */
    public function setPrivateKeyAttribute(string $value): void
    {
        $this->attributes['private_key'] = Crypt::encryptString($value);
    }

    /**
     * Get the private key attribute (decrypt it).
     */
    public function getPrivateKeyAttribute(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Get frequency description for display.
     */
    public function getFrequencyDescription(): string
    {
        return match ($this->import_frequency) {
            15 => __('Toutes les 15 minutes'),
            30 => __('Toutes les 30 minutes'),
            60 => __('Toutes les heures'),
            120 => __('Toutes les 2 heures'),
            240 => __('Toutes les 4 heures'),
            1440 => __('Une fois par jour'),
            default => __("Toutes les {$this->import_frequency} minutes"),
        };
    }
}
