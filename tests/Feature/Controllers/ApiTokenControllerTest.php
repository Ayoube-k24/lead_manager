<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\User;

describe('ApiTokenController - Index', function () {
    test('lists only tokens of authenticated user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token1 = ApiToken::factory()->create([
            'user_id' => $user1->id,
            'token' => 'token-user1-1',
        ]);
        $token2 = ApiToken::factory()->create([
            'user_id' => $user1->id,
            'token' => 'token-user1-2',
        ]);
        $token3 = ApiToken::factory()->create([
            'user_id' => $user2->id,
            'token' => 'token-user2-1',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer token-user1-1')
            ->getJson('/api/tokens');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'last_used_at',
                        'expires_at',
                        'created_at',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $tokenIds = collect($data)->pluck('id')->toArray();
        expect($tokenIds)->toContain($token1->id)
            ->toContain($token2->id)
            ->not->toContain($token3->id);
    });

    test('response format does not include token value', function () {
        $user = User::factory()->create();
        $apiToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'test-token-123',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer test-token-123')
            ->getJson('/api/tokens');

        $response->assertSuccessful();
        $data = $response->json('data.0');
        expect($data)->not->toHaveKey('token');
    });

    test('requires authentication', function () {
        $response = $this->getJson('/api/tokens');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Token d\'authentification manquant']);
    });
});

describe('ApiTokenController - Store', function () {
    test('creates token successfully with required name', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/tokens', [
                'name' => 'My API Token',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'token',
                    'expires_at',
                    'created_at',
                ],
            ])
            ->assertJson([
                'message' => 'Token API créé avec succès',
                'data' => [
                    'name' => 'My API Token',
                ],
            ]);

        expect($response->json('data.token'))->toBeString()
            ->toHaveLength(64);

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'My API Token',
        ]);
    });

    test('creates token with optional expiration date', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $expiresAt = now()->addDays(30)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/tokens', [
                'name' => 'Token with expiration',
                'expires_at' => $expiresAt,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Token with expiration',
                    'expires_at' => $expiresAt,
                ],
            ]);

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'Token with expiration',
        ]);
    });

    test('validates name is required', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/tokens', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('validates expiration date must be after now', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/tokens', [
                'name' => 'Test Token',
                'expires_at' => now()->subDay()->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    });

    test('generated token is returned only once in response', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/tokens', [
                'name' => 'Test Token',
            ]);

        $response->assertStatus(201);
        $generatedToken = $response->json('data.token');

        // Token should be 64 characters (from ApiToken::generate())
        expect($generatedToken)->toBeString()
            ->toHaveLength(64);

        // Verify token is stored in database
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'Test Token',
            'token' => $generatedToken,
        ]);

        // When listing tokens, token value should not be included
        $listResponse = $this->withHeader('Authorization', 'Bearer '.$generatedToken)
            ->getJson('/api/tokens');

        $listResponse->assertSuccessful();
        $tokenData = collect($listResponse->json('data'))->firstWhere('name', 'Test Token');
        expect($tokenData)->not->toHaveKey('token');
    });
});

describe('ApiTokenController - Destroy', function () {
    test('deletes own token successfully', function () {
        $user = User::factory()->create();
        $authToken = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);
        $tokenToDelete = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'token-to-delete',
            'expires_at' => null,
        ]);

        // Refresh to ensure token is persisted
        $tokenToDelete->refresh();

        // Verify token exists before deletion
        $this->assertDatabaseHas('api_tokens', [
            'id' => $tokenToDelete->id,
            'user_id' => $user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/tokens/{$tokenToDelete->id}");

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Token supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('api_tokens', [
            'id' => $tokenToDelete->id,
        ]);
    });

    test('cannot delete token of another user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $authToken = ApiToken::factory()->create([
            'user_id' => $user1->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);
        $otherUserToken = ApiToken::factory()->create([
            'user_id' => $user2->id,
            'token' => 'other-token',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/tokens/{$otherUserToken->id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Token non trouvé',
            ]);

        $this->assertDatabaseHas('api_tokens', [
            'id' => $otherUserToken->id,
        ]);
    });

    test('requires authentication to delete token', function () {
        $token = ApiToken::factory()->create();

        $response = $this->deleteJson("/api/tokens/{$token->id}");

        $response->assertUnauthorized();
    });
});
