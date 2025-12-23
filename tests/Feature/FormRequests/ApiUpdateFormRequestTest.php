<?php

declare(strict_types=1);

use App\Http\Requests\Api\UpdateFormRequest;

describe('Api\UpdateFormRequest', function () {
    test('denies authorization by default', function () {
        $request = new UpdateFormRequest;

        expect($request->authorize())->toBeFalse();
    });

    test('has empty validation rules', function () {
        $request = new UpdateFormRequest;

        expect($request->rules())->toBeArray()
            ->and($request->rules())->toBeEmpty();
    });
});
