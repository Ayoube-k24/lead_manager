<?php

declare(strict_types=1);

use App\Models\EmailSubject;
use App\Models\LeadEmail;

describe('EmailSubject Model', function () {
    test('has correct fillable attributes', function () {
        $subject = EmailSubject::factory()->create([
            'subject' => 'Test Subject',
            'default_template_html' => '<p>Test</p>',
            'is_active' => true,
            'order' => 5,
        ]);

        expect($subject->subject)->toBe('Test Subject')
            ->and($subject->default_template_html)->toBe('<p>Test</p>')
            ->and($subject->is_active)->toBeTrue()
            ->and($subject->order)->toBe(5);
    });

    test('is_active is cast to boolean', function () {
        $subject = EmailSubject::factory()->create(['is_active' => true]);

        expect($subject->is_active)->toBeBool()
            ->and($subject->is_active)->toBeTrue();
    });

    test('has many lead emails', function () {
        $subject = EmailSubject::factory()->create();
        LeadEmail::factory()->count(3)->create(['email_subject_id' => $subject->id]);

        expect($subject->leadEmails)->toHaveCount(3);
    });

    test('scope active filters only active subjects', function () {
        EmailSubject::factory()->create(['is_active' => true]);
        EmailSubject::factory()->create(['is_active' => false]);
        EmailSubject::factory()->create(['is_active' => true]);

        $activeSubjects = EmailSubject::active()->get();

        expect($activeSubjects)->toHaveCount(2)
            ->and($activeSubjects->every(fn ($subject) => $subject->is_active))->toBeTrue();
    });

    test('scope ordered orders by order field then subject', function () {
        $subject1 = EmailSubject::factory()->create(['order' => 2, 'subject' => 'B']);
        $subject2 = EmailSubject::factory()->create(['order' => 1, 'subject' => 'C']);
        $subject3 = EmailSubject::factory()->create(['order' => 1, 'subject' => 'A']);

        $ordered = EmailSubject::ordered()->get();

        expect($ordered->first()->id)->toBe($subject3->id)
            ->and($ordered->skip(1)->first()->id)->toBe($subject2->id)
            ->and($ordered->last()->id)->toBe($subject1->id);
    });
});
