<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\MailWizzConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create mailwizz config', function () {
    $config = MailWizzConfig::factory()->create([
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public-key',
        'private_key' => 'test-private-key',
    ]);

    expect($config)
        ->toBeInstanceOf(MailWizzConfig::class)
        ->and($config->api_url)->toBe('https://test.mailwizz.com')
        ->and($config->public_key)->toBe('test-public-key');
});

test('private key is encrypted when stored', function () {
    $privateKey = 'test-private-key-123';
    $config = MailWizzConfig::factory()->create([
        'private_key' => $privateKey,
    ]);

    // The stored value should be encrypted
    $storedValue = $config->getRawOriginal('private_key');
    expect($storedValue)->not->toBe($privateKey)
        ->and($storedValue)->not->toBeNull();

    // But when accessed, it should be decrypted
    expect($config->private_key)->toBe($privateKey);
});

test('can get frequency description', function () {
    $config = MailWizzConfig::factory()->create(['import_frequency' => 15]);
    expect($config->getFrequencyDescription())->toContain('15 minutes');

    $config = MailWizzConfig::factory()->create(['import_frequency' => 60]);
    expect($config->getFrequencyDescription())->toContain('heures');

    $config = MailWizzConfig::factory()->create(['import_frequency' => 1440]);
    expect($config->getFrequencyDescription())->toContain('jour');
});

test('has call center relationship', function () {
    $callCenter = CallCenter::factory()->create();
    $config = MailWizzConfig::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);

    expect($config->callCenter)->toBeInstanceOf(CallCenter::class)
        ->and($config->callCenter->id)->toBe($callCenter->id);
});
