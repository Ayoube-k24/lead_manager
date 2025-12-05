<?php

declare(strict_types=1);

describe('SeedStatisticsDemo Command', function () {
    test('calls db:seed with StatisticsDemoSeeder', function () {
        $this->artisan('seed:statistics-demo')
            ->assertSuccessful()
            ->expectsOutput('🌱 Seeding statistics demo data...');
    });
});
