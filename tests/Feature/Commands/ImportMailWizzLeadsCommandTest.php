<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\MailWizzConfig;
use App\Services\MailWizzService;
use App\Services\TagService;

describe('ImportMailWizzLeadsCommand', function () {
    test('imports leads from active MailWizz configs', function () {
        $callCenter = CallCenter::factory()->create();
        $config = MailWizzConfig::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true,
            'list_uid' => 'test-list-uid',
        ]);

        // Verify config exists and is active
        expect(MailWizzConfig::where('is_active', true)->count())->toBe(1);

        // Mock TagService first (required by MailWizzService constructor)
        $this->mock(TagService::class, function ($mock) {
            $mock->shouldReceive('attachTag')->zeroOrMoreTimes()->andReturn(true);
        });

        $this->mock(MailWizzService::class, function ($mock) {
            $mock->shouldReceive('importLeads')
                ->once()
                ->andReturn([
                    'imported' => 5,
                    'skipped_duplicate' => 2,
                    'skipped_has_form' => 1,
                    'errors' => 0,
                ]);
        });

        $this->artisan('mailwizz:import-leads')
            ->assertSuccessful();
    });

    test('imports from specific config when --config-id is provided', function () {
        $callCenter = CallCenter::factory()->create();
        $config = MailWizzConfig::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true, // Active config to test --config-id option
            'list_uid' => 'test-list-uid',
        ]);

        // Mock TagService first (required by MailWizzService constructor)
        $this->mock(TagService::class, function ($mock) {
            $mock->shouldReceive('attachTag')->zeroOrMoreTimes()->andReturn(true);
        });

        $this->mock(MailWizzService::class, function ($mock) {
            $mock->shouldReceive('importLeads')
                ->once()
                ->andReturn([
                    'imported' => 3,
                    'skipped_duplicate' => 0,
                    'skipped_has_form' => 0,
                    'errors' => 0,
                ]);
        });

        $this->artisan('mailwizz:import-leads', ['--config-id' => $config->id])
            ->assertSuccessful();
    });

    test('fails when config-id does not exist', function () {
        $this->artisan('mailwizz:import-leads', ['--config-id' => 999])
            ->assertFailed()
            ->expectsOutput('Configuration MailWizz #999 introuvable.');
    });

    test('skips inactive configs unless --force is used', function () {
        $callCenter = CallCenter::factory()->create();
        $config = MailWizzConfig::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => false,
            'list_uid' => 'test-list-uid',
        ]);

        $this->artisan('mailwizz:import-leads')
            ->assertSuccessful()
            ->expectsOutput('Aucune configuration MailWizz active trouvée.');
    });

    test('imports inactive config when --force is used', function () {
        $callCenter = CallCenter::factory()->create();
        $config = MailWizzConfig::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => false,
            'list_uid' => 'test-list-uid',
        ]);

        // Mock TagService first (required by MailWizzService constructor)
        $this->mock(TagService::class, function ($mock) {
            $mock->shouldReceive('attachTag')->zeroOrMoreTimes()->andReturn(true);
        });

        $this->mock(MailWizzService::class, function ($mock) {
            $mock->shouldReceive('importLeads')
                ->once()
                ->andReturn([
                    'imported' => 2,
                    'skipped_duplicate' => 0,
                    'skipped_has_form' => 0,
                    'errors' => 0,
                ]);
        });

        // Use --config-id to specify the inactive config, and --force to import it
        $this->artisan('mailwizz:import-leads', ['--config-id' => $config->id, '--force' => true])
            ->assertSuccessful();
    });

    test('handles errors gracefully', function () {
        $callCenter = CallCenter::factory()->create();
        $config = MailWizzConfig::factory()->create([
            'call_center_id' => $callCenter->id,
            'is_active' => true,
            'list_uid' => 'test-list-uid',
        ]);

        // Mock TagService first (required by MailWizzService constructor)
        $this->mock(TagService::class, function ($mock) {
            $mock->shouldReceive('attachTag')->zeroOrMoreTimes()->andReturn(true);
        });

        $this->mock(MailWizzService::class, function ($mock) {
            $mock->shouldReceive('importLeads')
                ->once()
                ->andThrow(new \Exception('API Error'));
        });

        $this->artisan('mailwizz:import-leads')
            ->assertSuccessful();
    });
});
