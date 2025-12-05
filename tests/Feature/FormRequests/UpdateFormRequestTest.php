<?php

declare(strict_types=1);

use App\Http\Requests\UpdateFormRequest;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;

describe('UpdateFormRequest', function () {
    test('authorizes super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new UpdateFormRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    test('denies non-super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new UpdateFormRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });

    test('allows partial updates with sometimes rules', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'name' => 'Updated Form Name',
            'call_center_id' => $callCenter->id, // call_center_id is always required
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('validates name when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'name' => str_repeat('a', 256),
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    test('validates fields when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'fields' => [],
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('fields'))->toBeTrue();
    });

    test('validates call_center_id is always required', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'name' => 'Updated Form',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('call_center_id'))->toBeTrue();
    });

    test('validates call_center_id exists when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'call_center_id' => 99999,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('call_center_id'))->toBeTrue();
    });

    test('passes validation with valid partial data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'name' => 'Updated Form',
            'description' => 'Updated Description',
            'call_center_id' => $callCenter->id, // call_center_id is always required
            'is_active' => false,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('passes validation with complete data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $request = UpdateFormRequest::create('/test', 'PUT', [
            'name' => 'Updated Form',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email'],
            ],
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});
