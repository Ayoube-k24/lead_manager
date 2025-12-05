<?php

declare(strict_types=1);

use App\Http\Requests\StoreFormRequest;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;

describe('StoreFormRequest', function () {
    test('authorizes super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new StoreFormRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    test('denies non-super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new StoreFormRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });

    test('denies unauthenticated users', function () {
        $request = new StoreFormRequest;
        $request->setUserResolver(fn () => null);

        expect($request->authorize())->toBeFalse();
    });

    test('validates required fields', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreFormRequest::create('/test', 'POST', []);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue()
            ->and($validator->errors()->has('fields'))->toBeTrue()
            ->and($validator->errors()->has('call_center_id'))->toBeTrue();
    });

    test('validates name is string and max 255 characters', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => str_repeat('a', 256),
            'fields' => [['name' => 'email', 'type' => 'email', 'label' => 'Email']],
            'call_center_id' => $callCenter->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    test('validates fields is array with minimum 1 item', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [],
            'call_center_id' => $callCenter->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('fields'))->toBeTrue();
    });

    test('validates field name, type, and label are required', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [
                ['name' => 'email'],
            ],
            'call_center_id' => $callCenter->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('fields.0.type'))->toBeTrue()
            ->and($validator->errors()->has('fields.0.label'))->toBeTrue();
    });

    test('validates field type is in allowed values', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [
                ['name' => 'email', 'type' => 'invalid', 'label' => 'Email'],
            ],
            'call_center_id' => $callCenter->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('fields.0.type'))->toBeTrue();
    });

    test('validates options are required when field type is select', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [
                ['name' => 'country', 'type' => 'select', 'label' => 'Country'],
            ],
            'call_center_id' => $callCenter->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('fields.0.options'))->toBeTrue();
    });

    test('validates smtp_profile_id exists', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [['name' => 'email', 'type' => 'email', 'label' => 'Email']],
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => 99999,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('smtp_profile_id'))->toBeTrue();
    });

    test('validates email_template_id exists', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [['name' => 'email', 'type' => 'email', 'label' => 'Email']],
            'call_center_id' => $callCenter->id,
            'email_template_id' => 99999,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email_template_id'))->toBeTrue();
    });

    test('validates call_center_id exists', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'fields' => [['name' => 'email', 'type' => 'email', 'label' => 'Email']],
            'call_center_id' => 99999,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('call_center_id'))->toBeTrue();
    });

    test('passes validation with valid data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $request = StoreFormRequest::create('/test', 'POST', [
            'name' => 'Test Form',
            'description' => 'Test Description',
            'fields' => [
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'placeholder' => 'Enter your email',
                    'required' => true,
                ],
                [
                    'name' => 'country',
                    'type' => 'select',
                    'label' => 'Country',
                    'options' => ['US', 'CA', 'FR'],
                ],
            ],
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});
