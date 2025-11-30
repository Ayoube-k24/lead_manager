<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\SmtpProfile;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('SmtpProfile Model - Basic Properties', function () {
    test('can be created with all required fields', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create([
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'secret_password',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test Name',
        ]);

        // Assert
        expect($smtpProfile->name)->toBe('Test SMTP')
            ->and($smtpProfile->host)->toBe('smtp.example.com')
            ->and($smtpProfile->port)->toBe(587)
            ->and($smtpProfile->encryption)->toBe('tls')
            ->and($smtpProfile->username)->toBe('user@example.com')
            ->and($smtpProfile->from_address)->toBe('noreply@example.com')
            ->and($smtpProfile->from_name)->toBe('Test Name');
    });

    test('can be created without from_name', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['from_name' => null]);

        // Assert
        expect($smtpProfile->from_name)->toBeNull();
    });
});

describe('SmtpProfile Model - Password Encryption', function () {
    test('encrypts password when setting attribute', function () {
        // Arrange
        $plainPassword = 'my_secret_password';
        $smtpProfile = SmtpProfile::factory()->make(['password' => $plainPassword]);

        // Act
        $smtpProfile->save();

        // Assert
        $savedProfile = SmtpProfile::find($smtpProfile->id);
        expect($savedProfile->attributes['password'])->not->toBe($plainPassword)
            ->and($savedProfile->attributes['password'])->toContain('eyJpdiI6'); // Encrypted string prefix
    });

    test('decrypts password when getting attribute', function () {
        // Arrange
        $plainPassword = 'my_secret_password';
        $smtpProfile = SmtpProfile::factory()->create(['password' => $plainPassword]);

        // Act
        $decryptedPassword = $smtpProfile->password;

        // Assert
        expect($decryptedPassword)->toBe($plainPassword);
    });

    test('password is hidden from serialization', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['password' => 'secret']);

        // Act
        $array = $smtpProfile->toArray();

        // Assert
        expect($array)->not->toHaveKey('password');
    });
});

describe('SmtpProfile Model - Casts', function () {
    test('casts is_active to boolean', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create(['is_active' => 1]);

        // Act & Assert
        expect($smtpProfile->is_active)->toBeBool()
            ->and($smtpProfile->is_active)->toBeTrue();
    });

    test('defaults is_active to true', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create();

        // Assert
        expect($smtpProfile->is_active)->toBeTrue();
    });
});

describe('SmtpProfile Model - Relationships', function () {
    test('has many forms', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();
        Form::factory()->count(3)->create(['smtp_profile_id' => $smtpProfile->id]);

        // Act
        $forms = $smtpProfile->forms;

        // Assert
        expect($forms)->toHaveCount(3);
    });

    test('returns empty collection when no forms assigned', function () {
        // Arrange
        $smtpProfile = SmtpProfile::factory()->create();

        // Act
        $forms = $smtpProfile->forms;

        // Assert
        expect($forms)->toBeEmpty();
    });
});

describe('SmtpProfile Model - Encryption Types', function () {
    test('can use tls encryption', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['encryption' => 'tls']);

        // Assert
        expect($smtpProfile->encryption)->toBe('tls');
    });

    test('can use ssl encryption', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['encryption' => 'ssl']);

        // Assert
        expect($smtpProfile->encryption)->toBe('ssl');
    });

    test('can use none encryption', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['encryption' => 'none']);

        // Assert
        expect($smtpProfile->encryption)->toBe('none');
    });
});

describe('SmtpProfile Model - Port Validation', function () {
    test('can use port 587', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['port' => 587]);

        // Assert
        expect($smtpProfile->port)->toBe(587);
    });

    test('can use port 465', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['port' => 465]);

        // Assert
        expect($smtpProfile->port)->toBe(465);
    });

    test('can use port 25', function () {
        // Arrange & Act
        $smtpProfile = SmtpProfile::factory()->create(['port' => 25]);

        // Assert
        expect($smtpProfile->port)->toBe(25);
    });
});

