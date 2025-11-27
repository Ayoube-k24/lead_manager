<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('ApiToken Model - Basic Properties', function () {
    test('can be created with all required fields', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Token',
            'token' => ApiToken::generate(),
        ]);

        // Assert
        expect($token->user_id)->toBe($user->id)
            ->and($token->name)->toBe('Test Token')
            ->and($token->token)->toBeString()
            ->and(strlen($token->token))->toBe(64);
    });

    test('can be created without expiration', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        // Assert
        expect($token->expires_at)->toBeNull();
    });
});

describe('ApiToken Model - Casts', function () {
    test('casts last_used_at to datetime', function () {
        // Arrange
        $user = User::factory()->create();
        $now = now();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'last_used_at' => $now,
        ]);

        // Act & Assert
        expect($token->last_used_at)->toBeInstanceOf(Carbon::class)
            ->and($token->last_used_at->timestamp)->toBe($now->timestamp);
    });

    test('casts expires_at to datetime', function () {
        // Arrange
        $user = User::factory()->create();
        $expiresAt = now()->addDays(30);
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
        ]);

        // Act & Assert
        expect($token->expires_at)->toBeInstanceOf(Carbon::class)
            ->and($token->expires_at->timestamp)->toBe($expiresAt->timestamp);
    });

    test('handles null last_used_at gracefully', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'last_used_at' => null,
        ]);

        // Act & Assert
        expect($token->last_used_at)->toBeNull();
    });
});

describe('ApiToken Model - Relationships', function () {
    test('belongs to user', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create(['user_id' => $user->id]);

        // Act
        $tokenUser = $token->user;

        // Assert
        expect($tokenUser)->toBeInstanceOf(User::class)
            ->and($tokenUser->id)->toBe($user->id);
    });
});

describe('ApiToken Model - Token Generation', function () {
    test('generate creates 64 character token', function () {
        // Act
        $token = ApiToken::generate();

        // Assert
        expect($token)->toBeString()
            ->and(strlen($token))->toBe(64);
    });

    test('generate creates unique tokens', function () {
        // Act
        $token1 = ApiToken::generate();
        $token2 = ApiToken::generate();
        $token3 = ApiToken::generate();

        // Assert
        expect($token1)->not->toBe($token2)
            ->and($token2)->not->toBe($token3)
            ->and($token1)->not->toBe($token3);
    });
});

describe('ApiToken Model - Expiration Checks', function () {
    test('isExpired returns false when expires_at is null', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        // Act & Assert
        expect($token->isExpired())->toBeFalse();
    });

    test('isExpired returns false when expires_at is in future', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        // Act & Assert
        expect($token->isExpired())->toBeFalse();
    });

    test('isExpired returns true when expires_at is in past', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        // Act & Assert
        expect($token->isExpired())->toBeTrue();
    });

    test('isValid returns true when token is not expired', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addDays(30),
        ]);

        // Act & Assert
        expect($token->isValid())->toBeTrue();
    });

    test('isValid returns false when token is expired', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        // Act & Assert
        expect($token->isValid())->toBeFalse();
    });

    test('isValid returns true when expires_at is null', function () {
        // Arrange
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        // Act & Assert
        expect($token->isValid())->toBeTrue();
    });
});

describe('ApiToken Model - Uniqueness', function () {
    test('token must be unique', function () {
        // Arrange
        $user = User::factory()->create();
        $tokenValue = ApiToken::generate();
        ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => $tokenValue,
        ]);

        // Act & Assert
        expect(fn () => ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => $tokenValue,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
