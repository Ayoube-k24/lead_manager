<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Webhook extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookFactory> */
    use Auditable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'form_id',
        'call_center_id',
        'user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Webhook $webhook): void {
            if (! $webhook->secret) {
                $webhook->secret = static::generateSecret();
            }
        });
    }

    /**
     * Generate a secure secret for webhook signing.
     */
    public static function generateSecret(): string
    {
        return Str::random(32);
    }

    /**
     * Get the form that owns the webhook.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the call center that owns the webhook.
     */
    public function callCenter(): BelongsTo
    {
        return $this->belongsTo(CallCenter::class);
    }

    /**
     * Get the user that owns the webhook.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if webhook listens to a specific event.
     */
    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }

    /**
     * Check if webhook is active and should be triggered.
     */
    public function shouldTrigger(): bool
    {
        return $this->is_active;
    }
}
