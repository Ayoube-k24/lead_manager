<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CreateCacheTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:create-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée la table cache si elle n\'existe pas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Vérification de la table cache...');

        try {
            if (! Schema::hasTable('cache')) {
                Schema::create('cache', function ($table) {
                    $table->string('key')->primary();
                    $table->mediumText('value');
                    $table->integer('expiration');
                });
                $this->info('✓ Table cache créée.');
            } else {
                $this->warn('Table cache existe déjà.');
            }

            if (! Schema::hasTable('cache_locks')) {
                Schema::create('cache_locks', function ($table) {
                    $table->string('key')->primary();
                    $table->string('owner');
                    $table->integer('expiration');
                });
                $this->info('✓ Table cache_locks créée.');
            } else {
                $this->warn('Table cache_locks existe déjà.');
            }

            // S'assurer que l'entrée de migration existe
            $maxBatch = \DB::table('migrations')->max('batch') ?? 0;
            $newBatch = $maxBatch + 1;

            if (! \DB::table('migrations')->where('migration', '0001_01_01_000001_create_cache_table')->exists()) {
                \DB::table('migrations')->insert([
                    'migration' => '0001_01_01_000001_create_cache_table',
                    'batch' => $newBatch,
                ]);
                $this->info('✓ Entrée de migration ajoutée.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la création de la table: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
