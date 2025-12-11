<?php

declare(strict_types=1);

use App\Models\EmailSubject;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\User;

describe('LeadEmail Model', function () {
    test('has correct fillable attributes', function () {
        $lead = Lead::factory()->create();
        $user = User::factory()->create();
        $emailSubject = EmailSubject::factory()->create();

        $leadEmail = LeadEmail::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'email_subject_id' => $emailSubject->id,
            'subject' => 'Test Subject',
            'body_html' => '<p>Test HTML</p>',
            'body_text' => 'Test Text',
            'attachment_path' => 'path/to/file.pdf',
            'attachment_name' => 'file.pdf',
            'attachment_mime' => 'application/pdf',
        ]);

        expect($leadEmail->subject)->toBe('Test Subject')
            ->and($leadEmail->body_html)->toBe('<p>Test HTML</p>')
            ->and($leadEmail->body_text)->toBe('Test Text')
            ->and($leadEmail->attachment_path)->toBe('path/to/file.pdf')
            ->and($leadEmail->attachment_name)->toBe('file.pdf')
            ->and($leadEmail->attachment_mime)->toBe('application/pdf');
    });

    test('sent_at is cast to datetime', function () {
        $leadEmail = LeadEmail::factory()->create(['sent_at' => now()]);

        expect($leadEmail->sent_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    test('belongs to lead', function () {
        $lead = Lead::factory()->create();
        $leadEmail = LeadEmail::factory()->create(['lead_id' => $lead->id]);

        expect($leadEmail->lead->id)->toBe($lead->id);
    });

    test('belongs to user', function () {
        $user = User::factory()->create();
        $leadEmail = LeadEmail::factory()->create(['user_id' => $user->id]);

        expect($leadEmail->user->id)->toBe($user->id);
    });

    test('belongs to email subject', function () {
        $emailSubject = EmailSubject::factory()->create();
        $leadEmail = LeadEmail::factory()->create(['email_subject_id' => $emailSubject->id]);

        expect($leadEmail->emailSubject->id)->toBe($emailSubject->id);
    });

    test('hasAttachment returns true when attachment exists', function () {
        $leadEmail = LeadEmail::factory()->create([
            'attachment_path' => 'path/to/file.pdf',
        ]);

        expect($leadEmail->hasAttachment())->toBeTrue();
    });

    test('hasAttachment returns false when no attachment', function () {
        $leadEmail = LeadEmail::factory()->create([
            'attachment_path' => null,
        ]);

        expect($leadEmail->hasAttachment())->toBeFalse();
    });

    test('can have null email_subject_id', function () {
        $leadEmail = LeadEmail::factory()->create(['email_subject_id' => null]);

        expect($leadEmail->email_subject_id)->toBeNull()
            ->and($leadEmail->emailSubject)->toBeNull();
    });
});

