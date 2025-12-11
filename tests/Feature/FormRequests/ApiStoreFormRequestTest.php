<?php

declare(strict_types=1);

use App\Http\Requests\Api\StoreFormRequest;

describe('Api\StoreFormRequest', function () {
    test('denies authorization by default', function () {
        $request = new StoreFormRequest;

        expect($request->authorize())->toBeFalse();
    });

    test('has empty validation rules', function () {
        $request = new StoreFormRequest;

        expect($request->rules())->toBeArray()
            ->and($request->rules())->toBeEmpty();
    });
});

