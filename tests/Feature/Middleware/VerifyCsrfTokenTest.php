<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

describe('VerifyCsrfToken Middleware', function () {
    beforeEach(function () {
        Route::post('/test-csrf', function () {
            return response()->json(['message' => 'Success']);
        });

        Route::post('/forms/test-uid/submit', function () {
            return response()->json(['message' => 'Form submitted']);
        });
    });

    test('requires CSRF token for protected routes', function () {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post('/test-csrf');

        $response->assertStatus(419); // CSRF token mismatch
    });

    test('allows form submission without CSRF token', function () {
        $response = $this->postJson('/forms/test-uid/submit', [
            'email' => 'test@example.com',
        ]);

        // Should not return 419 (CSRF error)
        expect($response->status())->not->toBe(419);
    });

    test('allows form submission with wildcard pattern', function () {
        $response = $this->postJson('/forms/another-uid/submit', [
            'email' => 'test@example.com',
        ]);

        expect($response->status())->not->toBe(419);
    });
});
