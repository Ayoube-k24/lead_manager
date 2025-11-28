<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStatus extends Model
{
    /** @use HasFactory<\Database\Factories\LeadStatusFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'color',
        'description',
        'is_system',
        'is_active',
        'is_final',
        'can_be_set_after_call',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'is_final' => 'boolean',
            'can_be_set_after_call' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Get the leads with this status.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'status_id');
    }

    /**
     * Check if status can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->is_system;
    }

    /**
     * Get the color class for the status badge.
     */
    public function colorClass(): string
    {
        // Convert hex color to Tailwind classes
        // For now, return a default class structure
        return 'bg-opacity-20 text-opacity-100';
    }

    /**
     * Scope to get active statuses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get final statuses.
     */
    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    /**
     * Scope to get post-call statuses.
     */
    public function scopePostCall($query)
    {
        return $query->where('can_be_set_after_call', true);
    }

    /**
     * Get all statuses (replaces enum cases()).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LeadStatus>
     */
    public static function allStatuses()
    {
        return static::orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get active statuses (replaces enum activeStatuses()).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LeadStatus>
     */
    public static function getActiveStatuses()
    {
        return static::active()->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get final statuses (replaces enum finalStatuses()).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LeadStatus>
     */
    public static function getFinalStatuses()
    {
        return static::final()->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get post-call statuses (replaces enum postCallStatuses()).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LeadStatus>
     */
    public static function getPostCallStatuses()
    {
        return static::postCall()->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get beginner statuses (simplified list).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LeadStatus>
     */
    public static function getBeginnerStatuses()
    {
        // Return a simplified list of common statuses for beginners
        $beginnerSlugs = ['qualified', 'not_interested', 'callback_pending', 'no_answer'];
        return static::whereIn('slug', $beginnerSlugs)->orderBy('order')->orderBy('name')->get();
    }

    /**
     * Get status by slug.
     */
    public static function getBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get label for display (replaces enum label()).
     */
    public function getLabel(): string
    {
        return $this->name;
    }

    /**
     * Get color class for badge (converts hex to Tailwind classes).
     */
    public function getColorClass(): string
    {
        // Convert hex color to approximate Tailwind classes
        // This is a simplified mapping - you may want to enhance this
        $colorMap = [
            '#FCD34D' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            '#60A5FA' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            '#FB923C' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
            '#4ADE80' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            '#F87171' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            '#A78BFA' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
            '#9CA3AF' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
            '#FBBF24' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400',
            '#34D399' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400',
            '#818CF8' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400',
            '#2DD4BF' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/20 dark:text-teal-400',
            '#22D3EE' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/20 dark:text-cyan-400',
            '#94A3B8' => 'bg-slate-100 text-slate-800 dark:bg-slate-900/20 dark:text-slate-400',
        ];

        return $colorMap[$this->color] ?? 'bg-neutral-100 text-neutral-800 dark:bg-neutral-900/20 dark:text-neutral-400';
    }

    /**
     * Check if this status is active (replaces enum isActive()).
     */
    public function isActiveStatus(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if this status is final (replaces enum isFinal()).
     */
    public function isFinalStatus(): bool
    {
        return $this->is_final;
    }

    /**
     * Check if this status can be set after a call (replaces enum canBeSetAfterCall()).
     */
    public function canBeSetAfterCallStatus(): bool
    {
        return $this->can_be_set_after_call;
    }
}
