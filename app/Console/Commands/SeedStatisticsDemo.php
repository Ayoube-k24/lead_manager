<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedStatisticsDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:statistics-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo data for statistics testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌱 Seeding statistics demo data...');

        $this->call('db:seed', [
            '--class' => 'StatisticsDemoSeeder',
        ]);

        $this->info('✅ Statistics demo data seeded successfully!');

        return Command::SUCCESS;
    }
}
