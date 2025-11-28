<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\MailWizzConfig;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    // Force run migrations if tables don't exist
    if (! \Illuminate\Support\Facades\Schema::hasTable('mailwizz_configs')) {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    }
});

test('command fails when config id does not exist', function () {
    $exitCode = Artisan::call('mailwizz:import-leads', [
        '--config-id' => 99999,
    ]);

    expect($exitCode)->toBe(1);
});

test('command succeeds when no active configs exist', function () {
    $exitCode = Artisan::call('mailwizz:import-leads');

    expect($exitCode)->toBe(0);
});

test('command can import with specific config id', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'is_active' => true,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    // Mock HTTP responses to avoid actual API calls
    \Illuminate\Support\Facades\Http::fake([
        '*/authenticate' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => [],
        ], 200),
    ]);

    $exitCode = Artisan::call('mailwizz:import-leads', [
        '--config-id' => $config->id,
    ]);

    expect($exitCode)->toBe(0);
});

test('command respects force flag for inactive configs', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'is_active' => false,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    // Mock HTTP responses
    \Illuminate\Support\Facades\Http::fake([
        '*/authenticate' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => [],
        ], 200),
    ]);

    $exitCode = Artisan::call('mailwizz:import-leads', [
        '--config-id' => $config->id,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
});

test('command imports multiple active configs', function () {
    $callCenter = CallCenter::factory()->create();
    $config1 = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'is_active' => true,
        'api_url' => 'https://test1.mailwizz.com',
    ]);
    $config2 = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'is_active' => true,
        'api_url' => 'https://test2.mailwizz.com',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/authenticate' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => [],
        ], 200),
    ]);

    $exitCode = Artisan::call('mailwizz:import-leads');

    expect($exitCode)->toBe(0);
});

test('command handles import errors gracefully', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
        'is_active' => true,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'invalid',
        'private_key' => 'invalid',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/authenticate' => \Illuminate\Support\Facades\Http::response([
            'status' => 'error',
            'message' => 'Invalid credentials',
        ], 401),
    ]);

    $exitCode = Artisan::call('mailwizz:import-leads', [
        '--config-id' => $config->id,
    ]);

    // Command should still return success but log the error
    expect($exitCode)->toBe(0);
});
