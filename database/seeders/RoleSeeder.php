<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip structure fixing in testing environment
        if (app()->environment('testing')) {
            $this->command?->info('📝 Création des rôles...');
        } else {
            $this->command->info('🔧 Vérification de la structure de la table roles...');
            // Corriger la structure si nécessaire
            $this->fixTableStructure();
            $this->command->info('📝 Création des rôles...');
        }

        $roles = [
            [
                'name' => 'Super Administrateur',
                'slug' => 'super_admin',
                'description' => 'Accès complet à toutes les fonctionnalités de la plateforme',
            ],
            [
                'name' => 'Propriétaire de Centre d\'Appels',
                'slug' => 'call_center_owner',
                'description' => 'Gère les agents au sein de son centre d\'appels et consulte les performances de son équipe',
            ],
            [
                'name' => 'Superviseur',
                'slug' => 'supervisor',
                'description' => 'Supervise les agents sous sa responsabilité, suit leurs performances et gère leurs leads',
            ],
            [
                'name' => 'Agent de Centre d\'Appels',
                'slug' => 'agent',
                'description' => 'Reçoit les leads attribués, les contacte par téléphone et met à jour leur statut',
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );

            if ($role->wasRecentlyCreated) {
                $this->command->info("  ✅ Rôle créé: {$role->name} (ID: {$role->id})");
            } else {
                $this->command->line("  ⏭️  Rôle existe déjà: {$role->name} (ID: {$role->id})");
            }
        }

        $this->command->info('✅ Rôles créés avec succès!');
    }

    private function fixTableStructure(): void
    {
        if (! Schema::hasTable('roles')) {
            $this->command->warn('  ⚠️  La table roles n\'existe pas. Exécutez: php artisan migrate');

            return;
        }

        try {
            $columns = DB::select('SHOW COLUMNS FROM roles');
            $columnNames = array_column($columns, 'Field');

            if (! in_array('name', $columnNames)) {
                DB::statement('ALTER TABLE roles ADD COLUMN name VARCHAR(255) UNIQUE AFTER id');
                $this->command->line("  ✓ Colonne 'name' ajoutée");
            }

            if (! in_array('slug', $columnNames)) {
                DB::statement('ALTER TABLE roles ADD COLUMN slug VARCHAR(255) UNIQUE AFTER name');
                $this->command->line("  ✓ Colonne 'slug' ajoutée");
            }

            if (! in_array('description', $columnNames)) {
                DB::statement('ALTER TABLE roles ADD COLUMN description TEXT NULL AFTER slug');
                $this->command->line("  ✓ Colonne 'description' ajoutée");
            }
        } catch (\Exception $e) {
            $this->command->error('  ❌ Erreur: '.$e->getMessage());
        }
    }
}
