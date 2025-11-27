<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    /** @use HasFactory<\Database\Factories\LeadNoteFactory> */
    use Auditable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'content',
        'is_private',
        'type',
        'attachments',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'attachments' => 'array',
        ];
    }

    /**
     * Get the lead that owns the note.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user that created the note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include public notes.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope a query to only include private notes.
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Check if note is visible to a user.
     */
    public function isVisibleTo(?User $user = null): bool
    {
        // Public notes are visible to everyone
        if (! $this->is_private) {
            return true;
        }

        // Private notes are only visible to the author and admins
        if (! $user) {
            return false;
        }

        return $this->user_id === $user->id || $user->isSuperAdmin();
    }
}
