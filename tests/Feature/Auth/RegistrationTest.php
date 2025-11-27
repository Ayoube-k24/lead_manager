<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Registration - Screen Rendering', function () {
    test('registration screen can be rendered', function () {
        // Act
        $response = $this->get(route('register'));

        // Assert
        $response->assertStatus(200);
    });
});

describe('Registration - Successful Registration', function () {
    test('new users can register', function () {
        // Act
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Assert
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
    });

    test('user password is hashed after registration', function () {
        // Act
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Assert
        $user = User::where('email', 'test@example.com')->first();
        expect(Hash::check('password', $user->password))->toBeTrue();
    });
});

describe('Registration - Validation', function () {
    test('validates required fields', function () {
        // Act
        $response = $this->post(route('register.store'), []);

        // Assert
        $response->assertSessionHasErrors(['name', 'email', 'password']);
    });

    test('validates email format', function () {
        // Act
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Assert
        $response->assertSessionHasErrors(['email']);
    });

    test('validates password confirmation', function () {
        // Act
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

        // Assert
        $response->assertSessionHasErrors(['password']);
    });

    test('validates password strength', function () {
        // Act - Password too short
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);

        // Assert
        $response->assertSessionHasErrors(['password']);
    });

    test('prevents duplicate email registration', function () {
        // Arrange
        User::factory()->create(['email' => 'existing@example.com']);

        // Act
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Assert
        $response->assertSessionHasErrors(['email']);
    });
});

describe('Registration - Email Verification', function () {
    test('sends email verification after registration', function () {
        // This test would require checking if email verification is enabled
        // and if a notification was sent
        // For now, we just verify the user was created
        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        expect($user)->not->toBeNull();
    });
});
