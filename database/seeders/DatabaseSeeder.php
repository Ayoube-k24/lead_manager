<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('  🚀 Configuration de la base de données');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->newLine();

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('  ✅ Configuration terminée avec succès!');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
