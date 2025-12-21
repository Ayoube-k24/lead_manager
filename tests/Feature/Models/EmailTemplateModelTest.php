<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;

describe('EmailTemplate Model - Relationships', function () {
    test('has many forms', function () {
        $template = EmailTemplate::factory()->create();
        Form::factory()->count(3)->create(['email_template_id' => $template->id]);

        expect($template->forms)->toHaveCount(3);
    });

    test('forms relationship works correctly', function () {
        $template = EmailTemplate::factory()->create();
        $form = Form::factory()->create(['email_template_id' => $template->id]);

        expect($template->forms->first()->id)->toBe($form->id);
    });
});

describe('EmailTemplate Model - Attributes', function () {
    test('variables is cast to array', function () {
        $template = EmailTemplate::factory()->create([
            'variables' => ['name', 'email', 'phone'],
        ]);

        expect($template->variables)->toBeArray()
            ->and($template->variables)->toBe(['name', 'email', 'phone']);
    });

    test('can update variables', function () {
        $template = EmailTemplate::factory()->create([
            'variables' => ['name'],
        ]);

        $template->variables = ['name', 'email', 'phone', 'company'];
        $template->save();

        $template->refresh();
        expect($template->variables)->toHaveCount(4);
    });
});






