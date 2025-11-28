<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\MailWizzConfig;
use App\Models\MailWizzImportedLead;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('mailwizz_configs table exists', function () {
    expect(Schema::hasTable('mailwizz_configs'))->toBeTrue();
});

test('mailwizz_imported_leads table exists', function () {
    expect(Schema::hasTable('mailwizz_imported_leads'))->toBeTrue();
});

test('leads table has source column', function () {
    expect(Schema::hasColumn('leads', 'source'))->toBeTrue();
});

test('can create mailwizz config in test database', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);

    expect($config)->toBeInstanceOf(MailWizzConfig::class)
        ->and($config->id)->not->toBeNull();
});

test('can create mailwizz imported lead in test database', function () {
    $lead = Lead::factory()->create(['source' => 'mailwizz_seo']);
    $imported = MailWizzImportedLead::factory()->create([
        'lead_id' => $lead->id,
    ]);

    expect($imported)->toBeInstanceOf(MailWizzImportedLead::class)
        ->and($imported->id)->not->toBeNull();
});

