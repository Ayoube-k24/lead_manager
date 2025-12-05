<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

describe('SetupTestUsers Command', function () {
    test('creates test users successfully', function () {
        // Ensure roles table exists
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Ensure call_centers table exists
        if (! Schema::hasTable('call_centers')) {
            Schema::create('call_centers', function ($table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('owner_id')->constrained('users');
                $table->string('distribution_method')->default('round_robin');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $this->artisan('setup:test-users')
            ->assertSuccessful()
            ->expectsOutput('✅ Configuration terminée!');

        expect(DB::table('users')->where('email', 'admin@leadmanager.com')->exists())->toBeTrue();
        expect(DB::table('users')->where('email', 'owner@leadmanager.com')->exists())->toBeTrue();
        expect(DB::table('users')->where('email', 'agent1@leadmanager.com')->exists())->toBeTrue();
    });

    test('creates roles if they do not exist', function () {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        $this->artisan('setup:test-users')
            ->assertSuccessful();

        expect(DB::table('roles')->where('slug', 'super_admin')->exists())->toBeTrue();
        expect(DB::table('roles')->where('slug', 'call_center_owner')->exists())->toBeTrue();
        expect(DB::table('roles')->where('slug', 'agent')->exists())->toBeTrue();
    });

    test('does not duplicate existing users', function () {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('call_centers')) {
            Schema::create('call_centers', function ($table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('owner_id')->constrained('users');
                $table->string('distribution_method')->default('round_robin');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Run command twice
        $this->artisan('setup:test-users')->assertSuccessful();
        $this->artisan('setup:test-users')->assertSuccessful();

        // Should still have only one of each user
        expect(DB::table('users')->where('email', 'admin@leadmanager.com')->count())->toBe(1);
    });
});
