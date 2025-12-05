<?php

declare(strict_types=1);

use App\Jobs\SendLeadReminderEmail;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use Illuminate\Support\Facades\Queue;

describe('SendLeadReminders Command', function () {
    beforeEach(function () {
        Queue::fake();
    });

    test('sends reminders for pending_email leads', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'email_confirmed_at' => null,
            'assigned_to' => null,
        ]);
        // Set created_at manually
        $lead->created_at = now()->subHours(25);
        $lead->saveQuietly();

        $this->artisan('leads:send-reminders', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutput('1 lead(s) inactif(s) trouvé(s).');

        Queue::assertPushed(SendLeadReminderEmail::class, function ($job) use ($lead) {
            return $job->lead->id === $lead->id;
        });
    });

    test('sends reminders for email_confirmed leads not called', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'called_at' => null,
            'assigned_to' => null,
        ]);
        // Set email_confirmed_at manually
        $lead->email_confirmed_at = now()->subHours(25);
        $lead->saveQuietly();

        $this->artisan('leads:send-reminders', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutput('1 lead(s) inactif(s) trouvé(s).');

        Queue::assertPushed(SendLeadReminderEmail::class, function ($job) use ($lead) {
            return $job->lead->id === $lead->id;
        });
    });

    test('respects hours option', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        // Lead created 12 hours ago (should not be included with --hours=24)
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'email_confirmed_at' => null,
            'assigned_to' => null,
        ]);
        // Set created_at manually
        $lead->created_at = now()->subHours(12);
        $lead->saveQuietly();

        $this->artisan('leads:send-reminders', ['--hours' => 24])
            ->assertSuccessful()
            ->expectsOutput('Aucun lead inactif trouvé.');
    });

    test('dry-run mode shows leads without sending', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'email_confirmed_at' => null,
            'assigned_to' => null,
        ]);
        // Set created_at manually
        $lead->created_at = now()->subHours(25);
        $lead->saveQuietly();

        $this->artisan('leads:send-reminders', ['--hours' => 24, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutput('Mode dry-run activé - aucun email ne sera envoyé.');

        Queue::assertNothingPushed();
    });

    test('returns success when no inactive leads found', function () {
        $this->artisan('leads:send-reminders')
            ->assertSuccessful()
            ->expectsOutput('Aucun lead inactif trouvé.');
    });
});
