<?php

use App\Models\Form;
use App\Services\FormValidationService;
use Illuminate\Validation\ValidationException;

test('form validation service validates required fields', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, []))
        ->toThrow(ValidationException::class);
});

test('form validation service validates email format', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['email' => 'invalid-email']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['email' => 'test@example.com']);
    expect($validated['email'])->toBe('test@example.com');
});

test('form validation service validates select field options', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'country',
                'type' => 'select',
                'label' => 'Country',
                'required' => true,
                'validation_rules' => [],
                'options' => ['FR', 'US', 'UK'],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['country' => 'INVALID']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['country' => 'FR']);
    expect($validated['country'])->toBe('FR');
});

test('form validation service validates optional fields', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'phone',
                'type' => 'tel',
                'label' => 'Phone',
                'required' => false,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    // Should not throw when field is missing
    $validated = $service->validate($form, []);
    expect($validated)->toBeArray();
});

test('form validation service validates min_length rule', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'name',
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
                'validation_rules' => ['min_length' => 3],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['name' => 'ab']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['name' => 'abc']);
    expect($validated['name'])->toBe('abc');
});

test('form validation service validates max_length rule', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'name',
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
                'validation_rules' => ['max_length' => 5],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['name' => 'abcdef']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['name' => 'abcde']);
    expect($validated['name'])->toBe('abcde');
});

test('form validation service validates min rule for numbers', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'age',
                'type' => 'number',
                'label' => 'Age',
                'required' => true,
                'validation_rules' => ['min' => 18],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['age' => 17]))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['age' => 18]);
    expect($validated['age'])->toBe(18);
});

test('form validation service validates max rule for numbers', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'age',
                'type' => 'number',
                'label' => 'Age',
                'required' => true,
                'validation_rules' => ['max' => 100],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['age' => 101]))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['age' => 100]);
    expect($validated['age'])->toBe(100);
});

test('form validation service validates regex rule', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'code',
                'type' => 'text',
                'label' => 'Code',
                'required' => true,
                'validation_rules' => ['regex' => '/^[A-Z]{3}$/'],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['code' => 'abc']))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->validate($form, ['code' => 'ABCD']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['code' => 'ABC']);
    expect($validated['code'])->toBe('ABC');
});

test('form validation service validates phone number format', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'phone',
                'type' => 'tel',
                'label' => 'Phone',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['phone' => 'invalid']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['phone' => '+33123456789']);
    expect($validated['phone'])->toBe('+33123456789');
});

test('form validation service validates date format', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'birthday',
                'type' => 'date',
                'label' => 'Birthday',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['birthday' => 'invalid-date']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['birthday' => '2024-01-01']);
    expect($validated['birthday'])->toBe('2024-01-01');
});

test('form validation service validates checkbox as boolean', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'agree',
                'type' => 'checkbox',
                'label' => 'I agree',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $service = new FormValidationService;

    expect(fn () => $service->validate($form, ['agree' => 'not-boolean']))
        ->toThrow(ValidationException::class);

    $validated = $service->validate($form, ['agree' => true]);
    expect($validated['agree'])->toBeTrue();
});

test('form validation service validates all field types', function () {
    $form = Form::factory()->create([
        'fields' => [
            ['name' => 'text_field', 'type' => 'text', 'label' => 'Text', 'required' => false, 'validation_rules' => [], 'options' => []],
            ['name' => 'email_field', 'type' => 'email', 'label' => 'Email', 'required' => false, 'validation_rules' => [], 'options' => []],
            ['name' => 'tel_field', 'type' => 'tel', 'label' => 'Phone', 'required' => false, 'validation_rules' => [], 'options' => []],
            ['name' => 'textarea_field', 'type' => 'textarea', 'label' => 'Message', 'required' => false, 'validation_rules' => [], 'options' => []],
            ['name' => 'number_field', 'type' => 'number', 'label' => 'Age', 'required' => false, 'validation_rules' => [], 'options' => []],
            ['name' => 'date_field', 'type' => 'date', 'label' => 'Date', 'required' => false, 'validation_rules' => [], 'options' => []],
            ['name' => 'select_field', 'type' => 'select', 'label' => 'Country', 'required' => false, 'validation_rules' => [], 'options' => ['FR', 'US']],
        ],
    ]);

    $service = new FormValidationService;

    $data = [
        'text_field' => 'Test',
        'email_field' => 'test@example.com',
        'tel_field' => '+33123456789',
        'textarea_field' => 'Long text',
        'number_field' => 25,
        'date_field' => '2024-01-01',
        'select_field' => 'FR',
    ];

    $validated = $service->validate($form, $data);
    expect($validated)->toHaveKeys(array_keys($data));
});
