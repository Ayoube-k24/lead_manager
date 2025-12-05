<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Form;
use App\Models\SmtpProfile;
use App\Models\User;

describe('SmtpProfileController - Index', function () {
    test('lists all smtp profiles', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        SmtpProfile::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson('/api/smtp-profiles');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'host',
                        'port',
                        'encryption',
                        'username',
                        'from_address',
                        'from_name',
                        'is_active',
                    ],
                ],
            ]);
    });

    test('password is hidden in response', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson("/api/smtp-profiles/{$smtpProfile->id}");

        $response->assertSuccessful();
        $data = $response->json('data');
        expect($data)->not->toHaveKey('password');
    });

    test('requires authentication', function () {
        $response = $this->getJson('/api/smtp-profiles');

        $response->assertUnauthorized();
    });
});

describe('SmtpProfileController - Store', function () {
    test('creates smtp profile successfully with all required fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $data = [
            'name' => 'Test SMTP Profile',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'test@example.com',
            'password' => 'secret-password',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test Sender',
            'is_active' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/smtp-profiles', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'host',
                    'port',
                    'encryption',
                    'username',
                    'from_address',
                    'from_name',
                    'is_active',
                ],
            ])
            ->assertJson([
                'message' => 'Profil SMTP créé avec succès',
                'data' => [
                    'name' => 'Test SMTP Profile',
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'test@example.com',
                    'from_address' => 'noreply@example.com',
                    'from_name' => 'Test Sender',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('smtp_profiles', [
            'name' => 'Test SMTP Profile',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'test@example.com',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test Sender',
            'is_active' => true,
        ]);

        // Verify password is encrypted
        $profile = SmtpProfile::where('name', 'Test SMTP Profile')->first();
        expect($profile->password)->toBe('secret-password'); // Should decrypt automatically
        expect($profile->getAttributes()['password'])->not->toBe('secret-password'); // But encrypted in DB
    });

    test('validates required fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/smtp-profiles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name']);
    });

    test('validates port range 1-65535', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        // Test port too low
        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/smtp-profiles', [
                'name' => 'Test',
                'host' => 'smtp.example.com',
                'port' => 0,
                'encryption' => 'tls',
                'username' => 'test@example.com',
                'password' => 'password',
                'from_address' => 'test@example.com',
                'from_name' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['port']);

        // Test port too high
        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/smtp-profiles', [
                'name' => 'Test',
                'host' => 'smtp.example.com',
                'port' => 65536,
                'encryption' => 'tls',
                'username' => 'test@example.com',
                'password' => 'password',
                'from_address' => 'test@example.com',
                'from_name' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['port']);
    });

    test('validates encryption type', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/smtp-profiles', [
                'name' => 'Test',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'invalid',
                'username' => 'test@example.com',
                'password' => 'password',
                'from_address' => 'test@example.com',
                'from_name' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['encryption']);

        // Test valid encryption types
        foreach (['tls', 'ssl', 'none'] as $encryption) {
            $response = $this->withHeader('Authorization', 'Bearer auth-token')
                ->postJson('/api/smtp-profiles', [
                    'name' => "Test {$encryption}",
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'encryption' => $encryption,
                    'username' => 'test@example.com',
                    'password' => 'password',
                    'from_address' => 'test@example.com',
                    'from_name' => 'Test',
                ]);

            $response->assertStatus(201);
        }
    });

    test('validates from_address is email', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/smtp-profiles', [
                'name' => 'Test',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'test@example.com',
                'password' => 'password',
                'from_address' => 'invalid-email',
                'from_name' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from_address']);
    });
});

describe('SmtpProfileController - Show', function () {
    test('shows smtp profile', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson("/api/smtp-profiles/{$smtpProfile->id}");

        $response->assertSuccessful()
            ->assertJson([
                'data' => [
                    'id' => $smtpProfile->id,
                    'name' => $smtpProfile->name,
                ],
            ]);
    });
});

describe('SmtpProfileController - Update', function () {
    test('updates smtp profile partially', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create([
            'name' => 'Original Name',
            'host' => 'original.example.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/smtp-profiles/{$smtpProfile->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Profil SMTP mis à jour avec succès',
                'data' => [
                    'name' => 'Updated Name',
                    'host' => 'original.example.com', // Should remain unchanged
                ],
            ]);

        $smtpProfile->refresh();
        expect($smtpProfile->name)->toBe('Updated Name')
            ->and($smtpProfile->host)->toBe('original.example.com');
    });

    test('password is optional on update', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create();
        $originalPassword = $smtpProfile->password;

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/smtp-profiles/{$smtpProfile->id}", [
                'name' => 'Updated Name',
                // No password provided
            ]);

        $response->assertSuccessful();

        $smtpProfile->refresh();
        expect($smtpProfile->password)->toBe($originalPassword); // Should remain unchanged
    });

    test('password is encrypted when provided', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/smtp-profiles/{$smtpProfile->id}", [
                'password' => 'new-password',
            ]);

        $response->assertSuccessful();

        $smtpProfile->refresh();
        expect($smtpProfile->password)->toBe('new-password'); // Should decrypt automatically
        expect($smtpProfile->getAttributes()['password'])->not->toBe('new-password'); // But encrypted in DB
    });
});

describe('SmtpProfileController - Destroy', function () {
    test('deletes smtp profile if not used by forms', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/smtp-profiles/{$smtpProfile->id}");

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Profil SMTP supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('smtp_profiles', [
            'id' => $smtpProfile->id,
        ]);
    });

    test('cannot delete smtp profile if used by forms', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $smtpProfile = SmtpProfile::factory()->create();
        Form::factory()->create([
            'smtp_profile_id' => $smtpProfile->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/smtp-profiles/{$smtpProfile->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Ce profil SMTP est utilisé par des formulaires et ne peut pas être supprimé',
            ]);

        $this->assertDatabaseHas('smtp_profiles', [
            'id' => $smtpProfile->id,
        ]);
    });
});
