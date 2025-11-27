<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\User;
use Laravel\Fortify\Features;

describe('Authentication - Login Screen', function () {
    test('login screen can be rendered', function () {
        // Act
        $response = $this->get(route('login'));

        // Assert
        $response->assertStatus(200);
    });

    test('authenticated users are redirected from login page', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get(route('login'));

        // Assert
        $response->assertRedirect(route('dashboard', absolute: false));
    });
});

describe('Authentication - Successful Login', function () {
    test('users can authenticate using the login screen', function () {
        // Arrange
        $user = User::factory()->withoutTwoFactor()->create();

        // Act
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert
        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    });

    test('users with two factor enabled are redirected to two factor challenge', function () {
        // Arrange
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        // Act
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert
        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    });
});

describe('Authentication - Failed Login', function () {
    test('users can not authenticate with invalid password', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // Assert
        $response->assertSessionHasErrorsIn('email');
        $this->assertGuest();
    });

    test('users can not authenticate with invalid email', function () {
        // Arrange & Act
        $response = $this->post(route('login.store'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        // Assert
        $response->assertSessionHasErrorsIn('email');
        $this->assertGuest();
    });

    test('validates required fields on login', function () {
        // Act
        $response = $this->post(route('login.store'), []);

        // Assert
        $response->assertSessionHasErrors(['email', 'password']);
    });

    test('validates email format on login', function () {
        // Act
        $response = $this->post(route('login.store'), [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        // Assert
        $response->assertSessionHasErrors(['email']);
    });
});

describe('Authentication - Rate Limiting', function () {
    test('applies rate limiting after multiple failed login attempts', function () {
        // Arrange
        $user = User::factory()->create();
        $throttleKey = strtolower($user->email).'|127.0.0.1';

        // Act - Attempt 5 failed logins (limit is 5 per minute)
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // Assert - 6th attempt should be throttled
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429); // Too Many Requests
    });

    test('rate limiting is per email and IP combination', function () {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Act - Attempt 5 failed logins for user1
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $user1->email,
                'password' => 'wrong-password',
            ]);
        }

        // Assert - user2 should still be able to attempt login
        $response = $this->post(route('login.store'), [
            'email' => $user2->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email'); // Not throttled, just wrong password
    });

    test('rate limiting resets after successful login', function () {
        // Arrange
        $user = User::factory()->withoutTwoFactor()->create();

        // Act - Attempt 4 failed logins
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // Successful login should reset the rate limit
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert - Can attempt login again after successful login
        $this->post(route('logout'));
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email'); // Not throttled
    });
});

describe('Authentication - Audit Logging', function () {
    test('logs successful login attempt', function () {
        // Arrange
        $user = User::factory()->withoutTwoFactor()->create();

        // Act
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert
        $log = ActivityLog::where('action', 'auth.login')
            ->where('user_id', $user->id)
            ->where('properties->success', true)
            ->first();

        expect($log)->not->toBeNull();
    });

    test('logs failed login attempt', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // Assert
        $log = ActivityLog::where('action', 'auth.login_failed')
            ->where('properties->email', $user->email)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->properties['email'])->toBe($user->email);
    });

    test('logs failed login attempt for non-existent email', function () {
        // Act
        $this->post(route('login.store'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        // Assert
        $log = ActivityLog::where('action', 'auth.login_failed')
            ->where('properties->email', 'nonexistent@example.com')
            ->first();

        expect($log)->not->toBeNull();
    });
});

describe('Authentication - Logout', function () {
    test('users can logout', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->post(route('logout'));

        // Assert
        $response->assertRedirect(route('home'));
        $this->assertGuest();
    });

    test('logs logout action', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $this->actingAs($user)->post(route('logout'));

        // Assert
        $log = ActivityLog::where('action', 'auth.logout')
            ->where('user_id', $user->id)
            ->first();

        expect($log)->not->toBeNull();
    });
});
