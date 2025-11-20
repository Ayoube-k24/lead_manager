<?php

declare(strict_types=1);

use App\Models\Form;

it('generates a unique 12 character uid for each form', function () {
    $form = Form::factory()->create();

    expect($form->uid)
        ->toHaveLength(12)
        ->toMatch('/^[A-Z0-9]{12}$/');

    $otherForm = Form::factory()->create();

    expect($form->uid)->not->toEqual($otherForm->uid);
});

