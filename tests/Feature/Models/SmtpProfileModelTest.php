<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\SmtpProfile;

describe('SmtpProfile Model - Password Encryption', function () {
    test('password is encrypted when set', function () {
        $profile = SmtpProfile::factory()->create([
            'password' => 'plain-password',
        ]);

        // Password should be accessible via accessor (decrypted)
        expect($profile->password)->toBe('plain-password');

        // But in database it should be encrypted
        $rawPassword = \DB::table('smtp_profiles')
            ->where('id', $profile->id)
            ->value('password');

        expect($rawPassword)->not->toBe('plain-password')
            ->and($rawPassword)->not->toBeNull();
    });

    test('password is hidden in serialization', function () {
        $profile = SmtpProfile::factory()->create([
            'password' => 'secret-password',
        ]);

        $array = $profile->toArray();
        expect($array)->not->toHaveKey('password');
    });
});

describe('SmtpProfile Model - Relationships', function () {
    test('has many forms', function () {
        $profile = SmtpProfile::factory()->create();
        Form::factory()->count(3)->create(['smtp_profile_id' => $profile->id]);

        expect($profile->forms)->toHaveCount(3);
    });

    test('forms relationship works correctly', function () {
        $profile = SmtpProfile::factory()->create();
        $form = Form::factory()->create(['smtp_profile_id' => $profile->id]);

        expect($profile->forms->first()->id)->toBe($form->id);
    });
});

describe('SmtpProfile Model - Attributes', function () {
    test('is_active is cast to boolean', function () {
        $profile = SmtpProfile::factory()->create(['is_active' => true]);

        expect($profile->is_active)->toBeTrue();
    });
});
