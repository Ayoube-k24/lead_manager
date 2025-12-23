<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\User;

describe('API Security', function () {
    test('requires authentication token for API requests', function () {
        $response = $this->getJson('/api/forms');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Token d\'authentification manquant',
            ]);
    });

    test('rejects invalid API token', function () {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/forms');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Token invalide ou expiré',
            ]);
    });

    test('rejects expired API token', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->getJson('/api/forms');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Token invalide ou expiré',
            ]);
    });

    test('accepts valid API token in Bearer header', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $callCenter = CallCenter::factory()->create();
        Form::factory()->create(['call_center_id' => $callCenter->id]);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->getJson('/api/forms');

        $response->assertSuccessful();
    });

    test('accepts valid API token in X-API-Token header', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $callCenter = CallCenter::factory()->create();
        Form::factory()->create(['call_center_id' => $callCenter->id]);

        $response = $this->withHeader('X-API-Token', $token->token)
            ->getJson('/api/forms');

        $response->assertSuccessful();
    });

    test('updates last_used_at when token is used', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
            'last_used_at' => null,
        ]);

        $callCenter = CallCenter::factory()->create();
        Form::factory()->create(['call_center_id' => $callCenter->id]);

        $this->withHeader('Authorization', "Bearer {$token->token}")
            ->getJson('/api/forms');

        $token->refresh();
        expect($token->last_used_at)->not->toBeNull();
    });

    test('authenticates user correctly with API token', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $callCenter = CallCenter::factory()->create();
        Form::factory()->create(['call_center_id' => $callCenter->id]);

        $response = $this->withHeader('Authorization', "Bearer {$token->token}")
            ->getJson('/api/forms');

        $response->assertSuccessful();
        expect(auth()->user()->id)->toBe($user->id);
    });

    test('prevents access to API with revoked token', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        // Revoke token by deleting it
        $tokenValue = $token->token;
        $token->delete();

        $response = $this->withHeader('Authorization', "Bearer {$tokenValue}")
            ->getJson('/api/forms');

        $response->assertStatus(401);
    });

    test('prevents API access with token from different user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token1 = ApiToken::factory()->create([
            'user_id' => $user1->id,
            'expires_at' => null,
        ]);

        // User2 should not be able to use user1's token
        // (This is already prevented by the token lookup, but we test it anyway)
        $response = $this->withHeader('Authorization', "Bearer {$token1->token}")
            ->getJson('/api/tokens');

        // Should authenticate as user1, not user2
        expect(auth()->user()->id)->toBe($user1->id);
    });

    test('requires authentication for API token management', function () {
        $response = $this->getJson('/api/tokens');

        $response->assertStatus(401);
    });

    test('allows users to manage only their own API tokens', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token1 = ApiToken::factory()->create([
            'user_id' => $user1->id,
            'expires_at' => null,
        ]);

        $token2 = ApiToken::factory()->create([
            'user_id' => $user2->id,
            'expires_at' => null,
        ]);

        // User1 should only see their own tokens
        $response = $this->withHeader('Authorization', "Bearer {$token1->token}")
            ->getJson('/api/tokens');

        $response->assertSuccessful();
        $tokens = $response->json('data');
        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['id'])->toBe($token1->id);
    });
});
