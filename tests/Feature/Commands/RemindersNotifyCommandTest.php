<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\User;

describe('RemindersNotifyCommand', function () {
    test('sends notifications for upcoming reminders', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $user = User::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
        ]);

        LeadReminder::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'reminder_date' => now()->addHours(12),
            'is_completed' => false,
            'notified_at' => null,
        ]);

        $this->artisan('reminders:notify')
            ->assertSuccessful()
            ->expectsOutput('1 rappel(s) à notifier.');
    });

    test('returns success when no reminders to notify', function () {
        $this->artisan('reminders:notify')
            ->assertSuccessful()
            ->expectsOutput('Aucun rappel à notifier.');
    });

    test('marks reminders as notified', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $user = User::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
        ]);

        $reminder = LeadReminder::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'reminder_date' => now()->addHours(12),
            'is_completed' => false,
            'notified_at' => null,
        ]);

        $this->artisan('reminders:notify')
            ->assertSuccessful();

        expect($reminder->fresh()->notified_at)->not->toBeNull();
    });
});
