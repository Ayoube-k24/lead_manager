<?php

declare(strict_types=1);

use App\Models\Lead;
use Illuminate\Support\Str;

test('lead can confirm email with valid token', function () {
    $lead = Lead::factory()->create([
        'status' => 'pending_email',
        'email_confirmation_token' => Str::random(64),
        'email_confirmation_token_expires_at' => now()->addHours(24),
        'email_confirmed_at' => null,
    ]);

    $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

    $response->assertStatus(200)
        ->assertViewIs('leads.confirmation-success');

    $lead->refresh();
    expect($lead->email_confirmed_at)->not->toBeNull()
        ->and($lead->status)->toBe('email_confirmed');
});

test('invalid token shows error page', function () {
    $response = $this->get(route('leads.confirm-email', 'invalid-token'));

    $response->assertStatus(200)
        ->assertViewIs('leads.confirmation-error');
});

test('expired token shows error page', function () {
    $lead = Lead::factory()->create([
        'status' => 'pending_email',
        'email_confirmation_token' => Str::random(64),
        'email_confirmation_token_expires_at' => now()->subHours(1),
        'email_confirmed_at' => null,
    ]);

    $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

    $response->assertStatus(200)
        ->assertViewIs('leads.confirmation-error');
});

test('already confirmed email shows success message', function () {
    $lead = Lead::factory()->create([
        'status' => 'email_confirmed',
        'email_confirmation_token' => Str::random(64),
        'email_confirmed_at' => now()->subHours(1),
    ]);

    $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

    $response->assertStatus(200)
        ->assertViewIs('leads.confirmation-success');
});
