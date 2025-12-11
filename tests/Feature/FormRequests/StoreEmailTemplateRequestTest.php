<?php

declare(strict_types=1);

use App\Http\Requests\StoreEmailTemplateRequest;
use App\Models\Role;
use App\Models\User;

describe('StoreEmailTemplateRequest', function () {
    test('authorizes super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new StoreEmailTemplateRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    test('denies non-super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new StoreEmailTemplateRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });

    test('validates required fields', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', []);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue()
            ->and($validator->errors()->has('subject'))->toBeTrue()
            ->and($validator->errors()->has('body_html'))->toBeTrue();
    });

    test('validates name is string and max 255 characters', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', [
            'name' => str_repeat('a', 256),
            'subject' => 'Test Subject',
            'body_html' => '<p>Test Body</p>',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    test('validates subject is string and max 255 characters', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', [
            'name' => 'Test Template',
            'subject' => str_repeat('a', 256),
            'body_html' => '<p>Test Body</p>',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('subject'))->toBeTrue();
    });

    test('validates body_html is required', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', [
            'name' => 'Test Template',
            'subject' => 'Test Subject',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('body_html'))->toBeTrue();
    });

    test('allows body_text to be nullable', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', [
            'name' => 'Test Template',
            'subject' => 'Test Subject',
            'body_html' => '<p>Test Body</p>',
            'body_text' => null,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('allows variables to be nullable array', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', [
            'name' => 'Test Template',
            'subject' => 'Test Subject',
            'body_html' => '<p>Test Body</p>',
            'variables' => null,
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('passes validation with valid data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = StoreEmailTemplateRequest::create('/test', 'POST', [
            'name' => 'Test Email Template',
            'subject' => 'Welcome {{name}}',
            'body_html' => '<h1>Welcome {{name}}</h1><p>Your email is {{email}}</p>',
            'body_text' => 'Welcome {{name}}. Your email is {{email}}',
            'variables' => ['name', 'email'],
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});

