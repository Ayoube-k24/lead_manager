<?php

declare(strict_types=1);

use App\Http\Requests\StoreSmtpProfileRequest;
use App\Models\Role;
use App\Models\User;

describe('StoreSmtpProfileRequest', function () {
    test('authorizes super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new StoreSmtpProfileRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    test('denies non-super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new StoreSmtpProfileRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });

    test('validates required fields', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreSmtpProfileRequest::create('/test', 'POST', []);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue()
            ->and($validator->errors()->has('host'))->toBeTrue()
            ->and($validator->errors()->has('port'))->toBeTrue()
            ->and($validator->errors()->has('encryption'))->toBeTrue()
            ->and($validator->errors()->has('username'))->toBeTrue()
            ->and($validator->errors()->has('password'))->toBeTrue()
            ->and($validator->errors()->has('from_address'))->toBeTrue();
    });

    test('validates port is integer between 1 and 65535', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreSmtpProfileRequest::create('/test', 'POST', [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 0,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'test@example.com',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('port'))->toBeTrue();
    });

    test('validates port maximum is 65535', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreSmtpProfileRequest::create('/test', 'POST', [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 65536,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'test@example.com',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('port'))->toBeTrue();
    });

    test('validates encryption is in allowed values', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreSmtpProfileRequest::create('/test', 'POST', [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'invalid',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'test@example.com',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('encryption'))->toBeTrue();
    });

    test('validates from_address is valid email', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreSmtpProfileRequest::create('/test', 'POST', [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'invalid-email',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('from_address'))->toBeTrue();
    });

    test('passes validation with valid data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreSmtpProfileRequest::create('/test', 'POST', [
            'name' => 'Test SMTP Profile',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'testuser',
            'password' => 'testpass',
            'from_address' => 'test@example.com',
            'from_name' => 'Test Name',
            'is_active' => true,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('accepts all encryption types', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        foreach (['tls', 'ssl', 'none'] as $encryption) {
            $request = StoreSmtpProfileRequest::create('/test', 'POST', [
                'name' => 'Test SMTP',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => $encryption,
                'username' => 'user',
                'password' => 'pass',
                'from_address' => 'test@example.com',
            ]);
            $request->setUserResolver(fn () => $user);

            $validator = validator($request->all(), $request->rules());

            expect($validator->fails())->toBeFalse();
        }
    });
});
