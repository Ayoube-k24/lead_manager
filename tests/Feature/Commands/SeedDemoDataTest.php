<?php

declare(strict_types=1);

describe('SeedDemoData Command', function () {
    test('executes successfully', function () {
        $this->artisan('app:seed-demo-data')
            ->assertSuccessful();
    });
});
