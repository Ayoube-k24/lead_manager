<?php

declare(strict_types=1);

use App\Http\Requests\UpdateSmtpProfileRequest;
use App\Models\Role;
use App\Models\User;

describe('UpdateSmtpProfileRequest', function () {
    test('authorizes super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new UpdateSmtpProfileRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    test('denies non-super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new UpdateSmtpProfileRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });

    test('allows partial updates with sometimes rules', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'name' => 'Updated SMTP Profile',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('validates name when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'name' => str_repeat('a', 256),
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    test('validates port when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'port' => 0,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('port'))->toBeTrue();
    });

    test('validates encryption when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'encryption' => 'invalid',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('encryption'))->toBeTrue();
    });

    test('validates from_address when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'from_address' => 'invalid-email',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('from_address'))->toBeTrue();
    });

    test('passes validation with valid partial data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'name' => 'Updated Name',
            'is_active' => false,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('passes validation with complete data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateSmtpProfileRequest::create('/test', 'PUT', [
            'name' => 'Updated SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'test@example.com',
            'from_name' => 'Test Name',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});






