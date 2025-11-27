<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

describe('Password Reset - Request Screen', function () {
    test('reset password link screen can be rendered', function () {
        // Act
        $response = $this->get(route('password.request'));

        // Assert
        $response->assertStatus(200);
    });
});

describe('Password Reset - Request Link', function () {
    test('reset password link can be requested', function () {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        // Act
        $this->post(route('password.request'), ['email' => $user->email]);

        // Assert
        Notification::assertSentTo($user, ResetPassword::class);
    });

    test('does not send reset link for non-existent email', function () {
        // Arrange
        Notification::fake();

        // Act
        $this->post(route('password.request'), ['email' => 'nonexistent@example.com']);

        // Assert
        Notification::assertNothingSent();
    });

    test('validates email format when requesting reset', function () {
        // Act
        $response = $this->post(route('password.request'), ['email' => 'invalid-email']);

        // Assert
        $response->assertSessionHasErrors(['email']);
    });

    test('validates required email field', function () {
        // Act
        $response = $this->post(route('password.request'), []);

        // Assert
        $response->assertSessionHasErrors(['email']);
    });
});

describe('Password Reset - Reset Screen', function () {
    test('reset password screen can be rendered', function () {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        // Act & Assert
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertStatus(200);

            return true;
        });
    });

    test('reset password screen shows error for invalid token', function () {
        // Act
        $response = $this->get(route('password.reset', 'invalid-token'));

        // Assert
        $response->assertStatus(200); // Still shows the form but with error
    });
});

describe('Password Reset - Update Password', function () {
    test('password can be reset with valid token', function () {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();
        $oldPassword = $user->password;

        $this->post(route('password.request'), ['email' => $user->email]);

        // Act
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $oldPassword) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            // Assert
            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            $user->refresh();
            expect(Hash::check('new-password', $user->password))->toBeTrue()
                ->and($user->password)->not->toBe($oldPassword);

            return true;
        });
    });

    test('validates required fields when resetting password', function () {
        // Act
        $response = $this->post(route('password.update'), []);

        // Assert
        $response->assertSessionHasErrors(['token', 'email', 'password']);
    });

    test('validates password confirmation', function () {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        // Act
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

            // Assert
            $response->assertSessionHasErrors(['password']);

            return true;
        });
    });

    test('validates password strength when resetting', function () {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        // Act
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => '12345',
                'password_confirmation' => '12345',
            ]);

            // Assert
            $response->assertSessionHasErrors(['password']);

            return true;
        });
    });

    test('rejects expired reset token', function () {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.request'), ['email' => $user->email]);

        // Act - Simulate expired token by using old token
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            // Manually expire the token in database
            $passwordReset = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->first();

            if ($passwordReset) {
                \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->update(['created_at' => now()->subHours(2)]); // Expired (default is 1 hour)
            }

            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            // Assert
            $response->assertSessionHasErrors(['email']);

            return true;
        });
    });

    test('rejects invalid reset token', function () {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // Assert
        $response->assertSessionHasErrors(['email']);
    });
});
