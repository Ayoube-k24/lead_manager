<?php

declare(strict_types=1);

use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\Role;
use App\Models\User;

describe('UpdateEmailTemplateRequest', function () {
    test('authorizes super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new UpdateEmailTemplateRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });

    test('denies non-super admin users', function () {
        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = new UpdateEmailTemplateRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeFalse();
    });

    test('allows partial updates with sometimes rules', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateEmailTemplateRequest::create('/test', 'PUT', [
            'name' => 'Updated Template',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('validates name when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateEmailTemplateRequest::create('/test', 'PUT', [
            'name' => str_repeat('a', 256),
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    test('validates subject when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateEmailTemplateRequest::create('/test', 'PUT', [
            'subject' => str_repeat('a', 256),
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('subject'))->toBeTrue();
    });

    test('validates body_html when provided', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateEmailTemplateRequest::create('/test', 'PUT', [
            'body_html' => '',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('body_html'))->toBeTrue();
    });

    test('passes validation with valid partial data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateEmailTemplateRequest::create('/test', 'PUT', [
            'name' => 'Updated Template Name',
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    test('passes validation with complete data', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $request = UpdateEmailTemplateRequest::create('/test', 'PUT', [
            'name' => 'Updated Template',
            'subject' => 'Updated Subject',
            'body_html' => '<p>Updated Body</p>',
            'body_text' => 'Updated Text',
            'variables' => ['name', 'email'],
        ]);
        $request->setUserResolver(fn () => $user);

        $validator = validator($request->all(), $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});






