<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper to ensure all migrations run correctly in tests
 * This fixes the issue where RefreshDatabase doesn't execute all migrations in SQLite
 */
function ensureMigrationsRun(): void
{
    // Fix roles table if incomplete
    if (Schema::hasTable('roles')) {
        $columns = DB::select('PRAGMA table_info(roles)');
        $columnNames = array_column($columns, 'name');

        if (count($columnNames) < 6) {
            Schema::dropIfExists('roles');
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    } else {
        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    // Fix users table if missing columns
    if (Schema::hasTable('users')) {
        $columns = DB::select('PRAGMA table_info(users)');
        $columnNames = array_column($columns, 'name');

        if (! in_array('role_id', $columnNames)) {
            Schema::table('users', function ($table) {
                $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (! in_array('call_center_id', $columnNames)) {
            Schema::table('users', function ($table) {
                $table->foreignId('call_center_id')->nullable()->after('role_id')->constrained('call_centers')->nullOnDelete();
            });
        }
    }

    // Ensure call_centers table exists
    if (! Schema::hasTable('call_centers')) {
        Schema::create('call_centers', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('distribution_method')->default('round_robin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    } else {
        $columns = DB::select('PRAGMA table_info(call_centers)');
        $columnNames = array_column($columns, 'name');

        if (count($columnNames) < 7) {
            Schema::dropIfExists('call_centers');
            Schema::create('call_centers', function ($table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('distribution_method')->default('round_robin');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }
}
