<?php

namespace Database\Seeders;

use App\Models\CallCenter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔧 Vérification de la structure des tables...');

        // Corriger la structure si nécessaire
        $this->fixTablesStructure();

        // Vérifier que les rôles existent
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $callCenterOwnerRole = Role::where('slug', 'call_center_owner')->first();
        $supervisorRole = Role::where('slug', 'supervisor')->first();
        $agentRole = Role::where('slug', 'agent')->first();

        if (! $superAdminRole || ! $callCenterOwnerRole || ! $supervisorRole || ! $agentRole) {
            $this->command->error('❌ Les rôles n\'existent pas. Exécutez d\'abord: php artisan db:seed --class=RoleSeeder');

            return;
        }

        if (! app()->environment('testing')) {
            $this->command->info('👤 Création des utilisateurs...');
        }

        // Créer le Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@leadmanager.com'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@leadmanager.com',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'email_verified_at' => now(),
            ]
        );

        if ($superAdmin->wasRecentlyCreated) {
            $this->command->info('  ✅ Super Admin créé');
        } else {
            $this->command->line('  ⏭️  Super Admin existe déjà');
        }

        // Créer le Propriétaire
        $callCenterOwner = User::firstOrCreate(
            ['email' => 'owner@leadmanager.com'],
            [
                'name' => 'Propriétaire Centre d\'Appels',
                'email' => 'owner@leadmanager.com',
                'password' => Hash::make('password'),
                'role_id' => $callCenterOwnerRole->id,
                'email_verified_at' => now(),
            ]
        );

        if ($callCenterOwner->wasRecentlyCreated) {
            $this->command->info('  ✅ Propriétaire créé');
        } else {
            $this->command->line('  ⏭️  Propriétaire existe déjà');
        }

        // Créer le Centre d'Appels
        $callCenter = CallCenter::firstOrCreate(
            ['owner_id' => $callCenterOwner->id],
            [
                'name' => 'Centre d\'Appels Principal',
                'description' => 'Centre d\'appels principal pour les tests',
                'owner_id' => $callCenterOwner->id,
                'distribution_method' => 'round_robin',
                'is_active' => true,
            ]
        );

        if ($callCenter->wasRecentlyCreated) {
            $this->command->info("  ✅ Centre d'Appels créé");
        } else {
            $this->command->line("  ⏭️  Centre d'Appels existe déjà");
        }

        // Associer le propriétaire au centre
        $callCenterOwner->update(['call_center_id' => $callCenter->id]);

        // Créer le Superviseur
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@leadmanager.com'],
            [
                'name' => 'Superviseur Centre d\'Appels',
                'email' => 'supervisor@leadmanager.com',
                'password' => Hash::make('password'),
                'role_id' => $supervisorRole->id,
                'call_center_id' => $callCenter->id,
                'email_verified_at' => now(),
            ]
        );

        if ($supervisor->wasRecentlyCreated) {
            $this->command->info('  ✅ Superviseur créé');
        } else {
            $this->command->line('  ⏭️  Superviseur existe déjà');
        }

        // Créer les agents
        $agents = [
            ['name' => 'Agent 1', 'email' => 'agent1@leadmanager.com'],
            ['name' => 'Agent 2', 'email' => 'agent2@leadmanager.com'],
            ['name' => 'Agent 3', 'email' => 'agent3@leadmanager.com'],
        ];

        foreach ($agents as $index => $agentData) {
            // Assigner les deux premiers agents au superviseur
            $supervisorId = ($index < 2) ? $supervisor->id : null;

            $agent = User::firstOrCreate(
                ['email' => $agentData['email']],
                [
                    'name' => $agentData['name'],
                    'email' => $agentData['email'],
                    'password' => Hash::make('password'),
                    'role_id' => $agentRole->id,
                    'call_center_id' => $callCenter->id,
                    'supervisor_id' => $supervisorId,
                    'email_verified_at' => now(),
                ]
            );

            // Si l'agent existe déjà mais n'a pas de superviseur, l'assigner
            if (! $agent->wasRecentlyCreated && $supervisorId && ! $agent->supervisor_id) {
                $agent->update(['supervisor_id' => $supervisorId]);
                $this->command->info("  ✅ {$agentData['name']} assigné au superviseur");
            }

            if ($agent->wasRecentlyCreated) {
                $supervisorInfo = $supervisorId ? " (supervisé par {$supervisor->name})" : '';
                $this->command->info("  ✅ {$agentData['name']} créé{$supervisorInfo}");
            } else {
                $supervisorInfo = $agent->supervisor_id ? " (supervisé par {$supervisor->name})" : '';
                $this->command->line("  ⏭️  {$agentData['name']} existe déjà{$supervisorInfo}");
            }
        }

        $this->command->newLine();
        $this->command->info('✅ Utilisateurs créés avec succès!');
        $this->command->newLine();
        $this->command->line('📋 Comptes disponibles:');
        $this->command->line('  • Super Admin: admin@leadmanager.com / password');
        $this->command->line('  • Propriétaire: owner@leadmanager.com / password');
        $this->command->line('  • Superviseur: supervisor@leadmanager.com / password');
        $this->command->line('  • Agents: agent1@leadmanager.com, agent2@leadmanager.com, agent3@leadmanager.com / password');
        $this->command->line('    (agent1 et agent2 sont supervisés par le superviseur)');
    }

    private function fixTablesStructure(): void
    {
        // Corriger la table users
        if (Schema::hasTable('users')) {
            try {
                $columns = DB::select('SHOW COLUMNS FROM users');
                $columnNames = array_column($columns, 'Field');

                if (! in_array('role_id', $columnNames)) {
                    DB::statement('ALTER TABLE users ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER id');
                    $this->command->line("  ✓ Colonne 'role_id' ajoutée à users");
                }

                if (! in_array('call_center_id', $columnNames)) {
                    DB::statement('ALTER TABLE users ADD COLUMN call_center_id BIGINT UNSIGNED NULL AFTER role_id');
                    $this->command->line("  ✓ Colonne 'call_center_id' ajoutée à users");
                }

                if (! in_array('supervisor_id', $columnNames)) {
                    DB::statement('ALTER TABLE users ADD COLUMN supervisor_id BIGINT UNSIGNED NULL AFTER call_center_id');
                    $this->command->line("  ✓ Colonne 'supervisor_id' ajoutée à users");
                }
            } catch (\Exception $e) {
                $this->command->warn('  ⚠️  Erreur users: '.$e->getMessage());
            }
        }

        // Corriger la table call_centers
        if (Schema::hasTable('call_centers')) {
            try {
                $columns = DB::select('SHOW COLUMNS FROM call_centers');
                $columnNames = array_column($columns, 'Field');

                $requiredColumns = [
                    'name' => 'VARCHAR(255) AFTER id',
                    'description' => 'TEXT NULL AFTER name',
                    'owner_id' => 'BIGINT UNSIGNED NOT NULL AFTER description',
                    'distribution_method' => "VARCHAR(255) DEFAULT 'round_robin' AFTER owner_id",
                    'is_active' => 'BOOLEAN DEFAULT 1 AFTER distribution_method',
                ];

                foreach ($requiredColumns as $column => $definition) {
                    if (! in_array($column, $columnNames)) {
                        DB::statement("ALTER TABLE call_centers ADD COLUMN {$column} {$definition}");
                        $this->command->line("  ✓ Colonne '{$column}' ajoutée à call_centers");
                    }
                }
            } catch (\Exception $e) {
                $this->command->warn('  ⚠️  Erreur call_centers: '.$e->getMessage());
            }
        }
    }
}
