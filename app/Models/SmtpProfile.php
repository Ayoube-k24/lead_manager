<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SmtpProfile extends Model
{
    /** @use HasFactory<\Database\Factories\SmtpProfileFactory> */
    use Auditable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
        ];
    }

    /**
     * Get the forms using this SMTP profile.
     */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /**
     * Set the password attribute (encrypt it).
     */
    public function setPasswordAttribute(?string $value): void
    {
        if (empty($value)) {
            // Don't update password if empty
            unset($this->attributes['password']);

            return;
        }

        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Get the password attribute (decrypt it).
     */
    public function getPasswordAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // If decryption fails (e.g., invalid MAC, wrong key), log and return null
            // This can happen if the encryption key changed or data is corrupted
            Log::error('Failed to decrypt SMTP password', [
                'smtp_profile_id' => $this->id,
                'smtp_profile_name' => $this->name,
                'error' => $e->getMessage(),
                'raw_password_length' => strlen($value),
                'raw_password_preview' => substr($value, 0, 20).'...',
                'possible_causes' => [
                    'APP_KEY may have changed',
                    'Password may have been corrupted',
                    'Password may not have been encrypted when stored',
                ],
            ]);

            return null;
        }
    }
}
