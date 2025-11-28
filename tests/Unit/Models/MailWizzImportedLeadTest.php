<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\MailWizzImportedLead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create mailwizz imported lead', function () {
    $lead = Lead::factory()->create(['source' => 'mailwizz_seo']);
    $imported = MailWizzImportedLead::factory()->create([
        'lead_id' => $lead->id,
        'mailwizz_subscriber_id' => 'sub-123',
        'email' => $lead->email,
    ]);

    expect($imported)
        ->toBeInstanceOf(MailWizzImportedLead::class)
        ->and($imported->mailwizz_subscriber_id)->toBe('sub-123')
        ->and($imported->email)->toBe($lead->email);
});

test('has lead relationship', function () {
    $lead = Lead::factory()->create(['source' => 'mailwizz_seo']);
    $imported = MailWizzImportedLead::factory()->create([
        'lead_id' => $lead->id,
    ]);

    expect($imported->lead)->toBeInstanceOf(Lead::class)
        ->and($imported->lead->id)->toBe($lead->id);
});

test('can check if subscriber is already imported', function () {
    $subscriberId = 'sub-123';
    $lead = Lead::factory()->create(['source' => 'mailwizz_seo']);

    expect(MailWizzImportedLead::isAlreadyImported($subscriberId))->toBeFalse();

    MailWizzImportedLead::factory()->create([
        'mailwizz_subscriber_id' => $subscriberId,
        'lead_id' => $lead->id,
    ]);

    expect(MailWizzImportedLead::isAlreadyImported($subscriberId))->toBeTrue();
});

test('can check if email exists in leads', function () {
    $email = 'test@example.com';

    expect(MailWizzImportedLead::emailExistsInLeads($email))->toBeFalse();

    Lead::factory()->create(['email' => $email]);

    expect(MailWizzImportedLead::emailExistsInLeads($email))->toBeTrue();
});

test('email check is case insensitive', function () {
    Lead::factory()->create(['email' => 'Test@Example.com']);

    expect(MailWizzImportedLead::emailExistsInLeads('test@example.com'))->toBeTrue();
    expect(MailWizzImportedLead::emailExistsInLeads('TEST@EXAMPLE.COM'))->toBeTrue();
});
