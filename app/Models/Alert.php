<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    /** @use HasFactory<\Database\Factories\AlertFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'role_slug',
        'name',
        'type',
        'conditions',
        'threshold',
        'is_active',
        'notification_channels',
        'last_triggered_at',
        'is_system',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'threshold' => 'decimal:2',
            'is_active' => 'boolean',
            'notification_channels' => 'array',
            'last_triggered_at' => 'datetime',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the alert.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the role that owns the alert.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_slug', 'slug');
    }

    /**
     * Scope a query to only include alerts for a specific role.
     */
    public function scopeForRole($query, string $roleSlug)
    {
        return $query->where('role_slug', $roleSlug);
    }

    /**
     * Check if alert can be triggered (cooldown check).
     */
    public function canBeTriggered(int $cooldownMinutes = 60): bool
    {
        if (! $this->last_triggered_at) {
            return true;
        }

        return $this->last_triggered_at->addMinutes($cooldownMinutes)->isPast();
    }

    /**
     * Mark alert as triggered.
     */
    public function markAsTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Get alert type label.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'lead_stale' => __('Lead inactif'),
            'agent_performance' => __('Performance agent'),
            'conversion_rate' => __('Taux de conversion'),
            'high_volume' => __('Volume élevé'),
            'low_volume' => __('Volume faible'),
            'form_performance' => __('Performance formulaire'),
            'status_threshold' => __('Seuil de statut'),
            'smtp_failure' => __('Échec SMTP'),
            default => $this->type,
        };
    }
}
