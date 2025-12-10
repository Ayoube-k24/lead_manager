<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleAlertType extends Model
{
    /** @use HasFactory<\Database\Factories\RoleAlertTypeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_slug',
        'alert_type',
        'name',
        'description',
        'is_enabled',
        'default_conditions',
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
            'is_enabled' => 'boolean',
            'default_conditions' => 'array',
            'order' => 'integer',
        ];
    }

    /**
     * Scope to get enabled alert types.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to get alert types for a specific role.
     */
    public function scopeForRole($query, string $roleSlug)
    {
        return $query->where('role_slug', $roleSlug);
    }

    /**
     * Get all enabled alert types for a role.
     */
    public static function getEnabledForRole(string $roleSlug): \Illuminate\Database\Eloquent\Collection
    {
        // Check if table exists, if not return empty collection
        if (! \Illuminate\Support\Facades\Schema::hasTable('role_alert_types')) {
            return collect();
        }

        return static::forRole($roleSlug)
            ->enabled()
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }
}
