<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ForceMailWizzMigrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailwizz:force-migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force l\'exécution des migrations MailWizz même si elles sont déjà marquées comme exécutées';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Création des tables MailWizz...');

        try {
            // Vérifier et créer la colonne source dans leads si nécessaire
            if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'source')) {
                Schema::table('leads', function ($table) {
                    $table->string('source')->default('form')->after('form_id')
                        ->comment('Source du lead: form, leads_seo, etc.');
                    $table->index('source');
                });
                $this->info('✓ Colonne source ajoutée à la table leads.');
            }

            // Créer la table mailwizz_configs
            if (! Schema::hasTable('mailwizz_configs')) {
                Schema::create('mailwizz_configs', function ($table) {
                    $table->id();
                    $table->string('api_url');
                    $table->string('public_key');
                    $table->text('private_key')->comment('Encrypted private key');
                    $table->string('list_uid')->nullable()->comment('MailWizz list UID');
                    $table->foreignId('call_center_id')->nullable()->constrained('call_centers')->nullOnDelete();
                    $table->integer('import_frequency')->default(15)->comment('Fréquence en minutes (15, 30, 60, etc.)');
                    $table->boolean('is_active')->default(false);
                    $table->timestamp('last_import_at')->nullable();
                    $table->integer('last_import_count')->default(0);
                    $table->timestamps();
                });
                $this->info('✓ Table mailwizz_configs créée.');
            } else {
                $this->warn('Table mailwizz_configs existe déjà.');
            }

            // Créer la table mailwizz_imported_leads
            if (! Schema::hasTable('mailwizz_imported_leads')) {
                Schema::create('mailwizz_imported_leads', function ($table) {
                    $table->id();
                    $table->string('mailwizz_subscriber_id')->unique()->comment('ID unique du subscriber MailWizz');
                    $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                    $table->string('email')->index();
                    $table->timestamp('imported_at');
                    $table->json('mailwizz_data')->nullable()->comment('Données brutes de MailWizz');
                    $table->timestamps();

                    $table->index(['email', 'mailwizz_subscriber_id']);
                });
                $this->info('✓ Table mailwizz_imported_leads créée.');
            } else {
                $this->warn('Table mailwizz_imported_leads existe déjà.');
            }

            // S'assurer que les entrées de migrations existent
            $maxBatch = \DB::table('migrations')->max('batch') ?? 0;
            $newBatch = $maxBatch + 1;

            if (! \DB::table('migrations')->where('migration', '2025_11_27_235413_create_mailwizz_configs_table')->exists()) {
                \DB::table('migrations')->insert([
                    'migration' => '2025_11_27_235413_create_mailwizz_configs_table',
                    'batch' => $newBatch,
                ]);
            }

            if (! \DB::table('migrations')->where('migration', '2025_11_27_235420_create_mailwizz_imported_leads_table')->exists()) {
                \DB::table('migrations')->insert([
                    'migration' => '2025_11_27_235420_create_mailwizz_imported_leads_table',
                    'batch' => $newBatch,
                ]);
            }

            $this->info('✓ Tables MailWizz créées avec succès!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la création des tables: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
