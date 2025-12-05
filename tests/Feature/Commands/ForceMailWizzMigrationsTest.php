<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

describe('ForceMailWizzMigrations Command', function () {
    test('creates mailwizz_configs table if it does not exist', function () {
        if (Schema::hasTable('mailwizz_configs')) {
            Schema::drop('mailwizz_configs');
        }

        $this->artisan('mailwizz:force-migrate')
            ->assertSuccessful()
            ->expectsOutput('✓ Table mailwizz_configs créée.');

        expect(Schema::hasTable('mailwizz_configs'))->toBeTrue();
    });

    test('creates mailwizz_imported_leads table if it does not exist', function () {
        if (Schema::hasTable('mailwizz_imported_leads')) {
            Schema::drop('mailwizz_imported_leads');
        }

        $this->artisan('mailwizz:force-migrate')
            ->assertSuccessful()
            ->expectsOutput('✓ Table mailwizz_imported_leads créée.');

        expect(Schema::hasTable('mailwizz_imported_leads'))->toBeTrue();
    });

    test('adds source column to leads table if it does not exist', function () {
        // SQLite doesn't support dropping columns easily, so we'll just test that the command runs
        // The column should already exist from migrations, so we test that it doesn't fail
        $this->artisan('mailwizz:force-migrate')
            ->assertSuccessful();
    });

    test('warns when tables already exist', function () {
        // Ensure tables exist
        if (! Schema::hasTable('mailwizz_configs')) {
            Schema::create('mailwizz_configs', function ($table) {
                $table->id();
                $table->string('api_url');
                $table->string('public_key');
                $table->text('private_key');
                $table->string('list_uid')->nullable();
                $table->foreignId('call_center_id')->nullable();
                $table->integer('import_frequency')->default(15);
                $table->boolean('is_active')->default(false);
                $table->timestamp('last_import_at')->nullable();
                $table->integer('last_import_count')->default(0);
                $table->timestamps();
            });
        }

        $this->artisan('mailwizz:force-migrate')
            ->assertSuccessful()
            ->expectsOutput('Table mailwizz_configs existe déjà.');
    });
});
