<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;

describe('AlertsCheckCommand', function () {
    test('checks and triggers alerts', function () {
        $callCenter = CallCenter::factory()->create();
        $user = User::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create(['call_center_id' => $callCenter->id]);

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'role_slug' => $user->role?->slug,
            'is_active' => true,
            'type' => 'lead_stale',
            'threshold' => 0,
        ]);

        // Create a lead to trigger the alert
        Lead::factory()->create(['call_center_id' => $callCenter->id]);

        $this->artisan('alerts:check')
            ->assertSuccessful();
    });

    test('returns success when no alerts triggered', function () {
        $this->artisan('alerts:check')
            ->assertSuccessful()
            ->expectsOutput('Aucune alerte déclenchée.');
    });
});
