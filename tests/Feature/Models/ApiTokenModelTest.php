<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\User;

describe('ApiToken Model - Token Generation', function () {
    test('generate creates 64 character token', function () {
        $token = ApiToken::generate();

        expect($token)->toBeString()
            ->toHaveLength(64);
    });

    test('generated tokens are unique', function () {
        $token1 = ApiToken::generate();
        $token2 = ApiToken::generate();

        expect($token1)->not->toBe($token2);
    });
});

describe('ApiToken Model - Validation', function () {
    test('isExpired returns false when no expiration', function () {
        $token = ApiToken::factory()->create([
            'expires_at' => null,
        ]);

        expect($token->isExpired())->toBeFalse();
    });

    test('isExpired returns false when not expired', function () {
        $token = ApiToken::factory()->create([
            'expires_at' => now()->addDays(30),
        ]);

        expect($token->isExpired())->toBeFalse();
    });

    test('isExpired returns true when expired', function () {
        $token = ApiToken::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        expect($token->isExpired())->toBeTrue();
    });

    test('isValid returns true for valid token', function () {
        $token = ApiToken::factory()->create([
            'expires_at' => now()->addDays(30),
        ]);

        expect($token->isValid())->toBeTrue();
    });

    test('isValid returns false for expired token', function () {
        $token = ApiToken::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        expect($token->isValid())->toBeFalse();
    });
});

describe('ApiToken Model - Relationships', function () {
    test('belongs to user', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create(['user_id' => $user->id]);

        expect($token->user)->not->toBeNull()
            ->and($token->user->id)->toBe($user->id);
    });
});

describe('ApiToken Model - Attributes', function () {
    test('dates are cast correctly', function () {
        $token = ApiToken::factory()->create([
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        expect($token->last_used_at)->toBeInstanceOf(\Carbon\Carbon::class)
            ->and($token->expires_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });
});

