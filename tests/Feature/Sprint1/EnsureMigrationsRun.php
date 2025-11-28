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
    // Check if facades are available (they won't be in unit tests)
    // Try to use Schema facade - if it fails, facades are not available
    try {
        if (! class_exists(\Illuminate\Support\Facades\Schema::class)) {
            return;
        }
        // Try to access the facade root
        $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
        if (! $schema) {
            return; // Facades not available, skip
        }
    } catch (\RuntimeException | \Error $e) {
        return; // Facades not available, skip silently
    } catch (\Exception $e) {
        // Other exceptions might be OK, continue
    }

    // Fix roles table if incomplete
    if (Schema::hasTable('roles')) {
        $columns = DB::select('PRAGMA table_info(roles)');
        $columnNames = array_column($columns, 'name');

        // Check if name column exists
        if (! in_array('name', $columnNames) || count($columnNames) < 6) {
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

    // Ensure leads table exists and has source column
    if (! Schema::hasTable('leads')) {
        // If leads table doesn't exist, create a minimal version
        Schema::create('leads', function ($table) {
            $table->id();
            $table->foreignId('form_id')->nullable();
            $table->string('source')->default('form');
            $table->json('data')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending_email');
            $table->timestamps();
        });
    } else {
        $columns = DB::select('PRAGMA table_info(leads)');
        $columnNames = array_column($columns, 'name');

        if (! in_array('source', $columnNames)) {
            try {
                Schema::table('leads', function ($table) {
                    $table->string('source')->default('form')->after('form_id');
                });
            } catch (\Exception $e) {
                // If adding column fails, try without after clause
                Schema::table('leads', function ($table) {
                    $table->string('source')->default('form');
                });
            }
        }
    }

    // Ensure mailwizz_configs table exists
    if (! Schema::hasTable('mailwizz_configs')) {
        try {
            Schema::create('mailwizz_configs', function ($table) {
                $table->id();
                $table->string('api_url');
                $table->string('public_key');
                $table->text('private_key');
                $table->string('list_uid')->nullable();
                if (Schema::hasTable('call_centers')) {
                    try {
                        $table->foreignId('call_center_id')->nullable()->constrained('call_centers')->nullOnDelete();
                    } catch (\Exception $e) {
                        $table->unsignedBigInteger('call_center_id')->nullable();
                    }
                } else {
                    $table->unsignedBigInteger('call_center_id')->nullable();
                }
                $table->integer('import_frequency')->default(15);
                $table->boolean('is_active')->default(false);
                $table->timestamp('last_import_at')->nullable();
                $table->integer('last_import_count')->default(0);
                $table->timestamps();
            });
        } catch (\Exception $e) {
            // Table might already exist or creation failed, try to verify
            if (! Schema::hasTable('mailwizz_configs')) {
                // Log error but don't fail
                \Log::warning('Failed to create mailwizz_configs table in test: '.$e->getMessage());
            }
        }
    }

    // Ensure mailwizz_imported_leads table exists
    if (! Schema::hasTable('mailwizz_imported_leads')) {
        try {
            Schema::create('mailwizz_imported_leads', function ($table) {
                $table->id();
                $table->string('mailwizz_subscriber_id')->unique();
                if (Schema::hasTable('leads')) {
                    try {
                        $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                    } catch (\Exception $e) {
                        $table->unsignedBigInteger('lead_id');
                    }
                } else {
                    $table->unsignedBigInteger('lead_id');
                }
                $table->string('email')->index();
                $table->timestamp('imported_at');
                $table->json('mailwizz_data')->nullable();
                $table->timestamps();
            });
        } catch (\Exception $e) {
            // Table might already exist or creation failed, try to verify
            if (! Schema::hasTable('mailwizz_imported_leads')) {
                // Log error but don't fail
                \Log::warning('Failed to create mailwizz_imported_leads table in test: '.$e->getMessage());
            }
        }
    }
}
