<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Route;

describe('AuthenticateApiToken Middleware', function () {
    beforeEach(function () {
        Route::middleware('api.token')->get('/test-api-route', function () {
            return response()->json(['message' => 'Success']);
        });
    });

    test('allows access with valid bearer token', function () {
        $user = User::factory()->create();
        $apiToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$apiToken->token}")
            ->get('/test-api-route');

        $response->assertSuccessful()
            ->assertJson(['message' => 'Success']);
    });

    test('allows access with valid X-API-Token header', function () {
        $user = User::factory()->create();
        $apiToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('X-API-Token', $apiToken->token)
            ->get('/test-api-route');

        $response->assertSuccessful();
    });

    test('returns 401 when token is missing', function () {
        $response = $this->get('/test-api-route');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Token d\'authentification manquant']);
    });

    test('returns 401 when token is invalid', function () {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->get('/test-api-route');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Token invalide ou expiré']);
    });

    test('returns 401 when token is expired', function () {
        $user = User::factory()->create();
        $apiToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$apiToken->token}")
            ->get('/test-api-route');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Token invalide ou expiré']);
    });

    test('updates last_used_at timestamp', function () {
        $user = User::factory()->create();
        $apiToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
            'last_used_at' => null,
        ]);

        $this->withHeader('Authorization', "Bearer {$apiToken->token}")
            ->get('/test-api-route');

        expect($apiToken->fresh()->last_used_at)->not->toBeNull();
    });

    test('sets authenticated user on request', function () {
        $user = User::factory()->create();
        $apiToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        Route::middleware('api.token')->get('/test-user', function () {
            return response()->json(['user_id' => auth()->id()]);
        });

        $response = $this->withHeader('Authorization', "Bearer {$apiToken->token}")
            ->get('/test-user');

        $response->assertJson(['user_id' => $user->id]);
    });
});
