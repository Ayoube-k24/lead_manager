<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     *
     * This mutator encrypts plain text passwords. Values from forms should always be plain text.
     * If a value is already encrypted and can be decrypted, we store it as-is to avoid double encryption.
     */
    public function setPasswordAttribute(?string $value): void
    {
        if (empty($value)) {
            // Don't update password if empty
            unset($this->attributes['password']);

            return;
        }

        // Check if value is already encrypted with current key
        // Laravel encrypted strings are base64 JSON starting with "eyJ" and > 100 chars
        $isAlreadyEncrypted = false;
        if (Str::startsWith($value, 'eyJ') && strlen($value) > 100) {
            try {
                // Try to decrypt - if successful, it's already encrypted with current key
                Crypt::decryptString($value);
                $isAlreadyEncrypted = true;
                Log::debug('Password already encrypted with current key', [
                    'smtp_profile_id' => $this->id ?? null,
                ]);
            } catch (DecryptException $e) {
                // Cannot decrypt - might be from old key or corrupted
                // Will encrypt as plain text
                Log::debug('Value looks encrypted but cannot decrypt, will encrypt as plain text', [
                    'smtp_profile_id' => $this->id ?? null,
                ]);
            }
        }

        // If already encrypted with current key, store as-is
        if ($isAlreadyEncrypted) {
            $this->attributes['password'] = $value;

            return;
        }

        // Encrypt the plain text password
        try {
            $encrypted = Crypt::encryptString($value);

            // Immediately verify encryption works
            $decrypted = Crypt::decryptString($encrypted);
            if ($decrypted !== $value) {
                Log::error('Password encryption failed - decrypted value does not match', [
                    'smtp_profile_id' => $this->id ?? null,
                ]);
                throw new \RuntimeException('Password encryption verification failed');
            }

            $this->attributes['password'] = $encrypted;

            // Verify the value was actually set in attributes
            $storedValue = $this->attributes['password'] ?? null;
            if ($storedValue !== $encrypted) {
                Log::error('Password mutator: encrypted value was not stored correctly in attributes', [
                    'smtp_profile_id' => $this->id ?? null,
                    'encrypted_length' => strlen($encrypted),
                    'stored_length' => $storedValue ? strlen($storedValue) : 0,
                    'encrypted_preview' => substr($encrypted, 0, 20).'...',
                    'stored_preview' => $storedValue ? substr($storedValue, 0, 20).'...' : 'null',
                ]);
            }

            Log::debug('Password encrypted successfully', [
                'smtp_profile_id' => $this->id ?? null,
                'value_length' => strlen($value),
                'encrypted_length' => strlen($encrypted),
                'stored_in_attributes' => isset($this->attributes['password']),
                'stored_value_matches' => ($this->attributes['password'] ?? null) === $encrypted,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to encrypt SMTP password', [
                'smtp_profile_id' => $this->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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

    /**
     * Check if the password can be decrypted.
     */
    public function canDecryptPassword(): bool
    {
        // Get raw password directly from database to avoid accessor/getOriginal issues
        // This ensures we always get the current value, even if model was loaded via relation
        $rawPassword = DB::table('smtp_profiles')
            ->where('id', $this->id)
            ->value('password');

        if (empty($rawPassword)) {
            return false;
        }

        try {
            Crypt::decryptString($rawPassword);

            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }

    /**
     * Check if password exists in database but cannot be decrypted.
     */
    public function hasEncryptedPasswordButCannotDecrypt(): bool
    {
        // Get raw password directly from database to avoid accessor/getOriginal issues
        // This ensures we always get the current value, even if model was loaded via relation
        $rawPassword = DB::table('smtp_profiles')
            ->where('id', $this->id)
            ->value('password');

        return ! empty($rawPassword) && ! $this->canDecryptPassword();
    }
}
