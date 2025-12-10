<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CreateRoleAlertTypesTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:create-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée la table role_alert_types si elle n\'existe pas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Vérification de la table role_alert_types...');

        try {
            if (! Schema::hasTable('role_alert_types')) {
                Schema::create('role_alert_types', function ($table) {
                    $table->id();
                    $table->string('role_slug');
                    $table->string('alert_type');
                    $table->string('name');
                    $table->text('description')->nullable();
                    $table->boolean('is_enabled')->default(true);
                    $table->json('default_conditions')->nullable();
                    $table->integer('order')->default(0);
                    $table->timestamps();

                    $table->unique(['role_slug', 'alert_type']);
                    $table->index('role_slug');
                    $table->index('is_enabled');
                });
                $this->info('✓ Table role_alert_types créée.');

                // S'assurer que l'entrée de migration existe
                $maxBatch = \DB::table('migrations')->max('batch') ?? 0;
                $newBatch = $maxBatch + 1;

                if (! \DB::table('migrations')->where('migration', '2025_12_10_151401_create_role_alert_types_table')->exists()) {
                    \DB::table('migrations')->insert([
                        'migration' => '2025_12_10_151401_create_role_alert_types_table',
                        'batch' => $newBatch,
                    ]);
                    $this->info('✓ Entrée de migration ajoutée.');
                }

                // Exécuter le seeder
                $this->info('Exécution du seeder...');
                $this->call('db:seed', ['--class' => 'RoleAlertTypeSeeder']);

                return Command::SUCCESS;
            } else {
                $this->warn('Table role_alert_types existe déjà.');

                return Command::SUCCESS;
            }
        } catch (\Exception $e) {
            $this->error('Erreur lors de la création de la table: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
