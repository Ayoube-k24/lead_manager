<?php

declare(strict_types=1);

use App\Models\Form;
use App\Services\FormValidationService;
use Illuminate\Validation\ValidationException;

describe('FormValidationService', function () {
    beforeEach(function () {
        $this->service = new FormValidationService();
    });

    describe('validate', function () {
        test('validates required fields', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, []))
                ->toThrow(ValidationException::class);
        });

        test('validates email format', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, ['email' => 'invalid-email']))
                ->toThrow(ValidationException::class);
        });

        test('validates numeric fields', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'age',
                        'type' => 'number',
                        'label' => 'Age',
                        'required' => true,
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, ['age' => 'not-a-number']))
                ->toThrow(ValidationException::class);
        });

        test('validates date fields', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'birthdate',
                        'type' => 'date',
                        'label' => 'Birthdate',
                        'required' => true,
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, ['birthdate' => 'invalid-date']))
                ->toThrow(ValidationException::class);
        });

        test('validates phone number format', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'phone',
                        'type' => 'tel',
                        'label' => 'Phone',
                        'required' => true,
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, ['phone' => 'invalid']))
                ->toThrow(ValidationException::class);
        });

        test('validates select options', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'country',
                        'type' => 'select',
                        'label' => 'Country',
                        'required' => true,
                        'options' => [
                            ['label' => 'France', 'value' => 'FR'],
                            ['label' => 'United States', 'value' => 'US'],
                            ['label' => 'United Kingdom', 'value' => 'UK'],
                        ],
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, ['country' => 'INVALID']))
                ->toThrow(ValidationException::class);
        });

        test('validates custom validation rules', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'name',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                        'validation_rules' => [
                            'min' => 3,
                            'max' => 50,
                        ],
                    ],
                ],
            ]);

            expect(fn () => $this->service->validate($form, ['name' => 'ab']))
                ->toThrow(ValidationException::class);
        });

        test('allows nullable optional fields', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => false,
                    ],
                ],
            ]);

            $result = $this->service->validate($form, []);

            expect($result)->toBeArray();
        });

        test('returns validated data on success', function () {
            $form = Form::factory()->create([
                'fields' => [
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                    ],
                ],
            ]);

            $result = $this->service->validate($form, ['email' => 'test@example.com']);

            expect($result['email'])->toBe('test@example.com');
        });
    });
});
