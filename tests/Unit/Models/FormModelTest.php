<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;

describe('Form Model', function () {
    describe('Relations', function () {
        test('belongs to SMTP profile', function () {
            $smtpProfile = SmtpProfile::factory()->create();
            $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);

            expect($form->smtpProfile)->not->toBeNull()
                ->and($form->smtpProfile->id)->toBe($smtpProfile->id);
        });

        test('belongs to email template', function () {
            $emailTemplate = EmailTemplate::factory()->create();
            $form = Form::factory()->create(['email_template_id' => $emailTemplate->id]);

            expect($form->emailTemplate)->not->toBeNull()
                ->and($form->emailTemplate->id)->toBe($emailTemplate->id);
        });

        test('belongs to call center', function () {
            $callCenter = CallCenter::factory()->create();
            $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

            expect($form->callCenter)->not->toBeNull()
                ->and($form->callCenter->id)->toBe($callCenter->id);
        });

        test('has many leads', function () {
            $form = Form::factory()->create();
            Lead::factory()->count(5)->create(['form_id' => $form->id]);

            expect($form->leads->count())->toBe(5);
        });
    });

    describe('UID Generation', function () {
        test('generates unique UID on creation', function () {
            $form = Form::factory()->create();

            expect($form->uid)->not->toBeNull()
                ->and(strlen($form->uid))->toBe(12);
        });

        test('generates unique UIDs for multiple forms', function () {
            $form1 = Form::factory()->create();
            $form2 = Form::factory()->create();

            expect($form1->uid)->not->toBe($form2->uid);
        });

        test('uses existing UID if provided', function () {
            $form = Form::factory()->create(['uid' => 'CUSTOMUID123']);

            expect($form->uid)->toBe('CUSTOMUID123');
        });
    });

    describe('Casts', function () {
        test('casts fields to array', function () {
            $fields = [
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'name', 'type' => 'text', 'required' => true],
            ];

            $form = Form::factory()->create(['fields' => $fields]);

            expect($form->fields)->toBeArray()
                ->and($form->fields)->toBe($fields);
        });

        test('casts is_active to boolean', function () {
            $form = Form::factory()->create(['is_active' => 1]);

            expect($form->is_active)->toBeTrue();
        });
    });
});






