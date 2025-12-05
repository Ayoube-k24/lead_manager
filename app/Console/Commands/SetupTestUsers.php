<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SetupTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:test-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige la structure de la base de données et crée les utilisateurs de test';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Correction de la structure et création des données ===');
        $this->newLine();

        try {
            // 1. Corriger la table roles
            $this->info('1. Vérification de la table roles...');
            $this->fixRolesTable();

            // 2. Corriger la table users
            $this->info('2. Vérification de la table users...');
            $this->fixUsersTable();

            // 3. Corriger la table call_centers
            $this->info('3. Vérification de la table call_centers...');
            $this->fixCallCentersTable();

            // 4. Créer les rôles
            $this->newLine();
            $this->info('4. Création des rôles...');
            $roleIds = $this->createRoles();

            // 5. Créer les utilisateurs
            $this->newLine();
            $this->info('5. Création des utilisateurs...');
            $this->createUsers($roleIds);

            // Résumé
            $this->newLine();
            $this->info('=== Résumé ===');
            $this->line('Rôles: '.DB::table('roles')->count());
            $this->line('Utilisateurs: '.DB::table('users')->count());
            $this->line('Centres d\'Appels: '.DB::table('call_centers')->count());

            $this->newLine();
            $this->info('✅ Configuration terminée!');
            $this->newLine();
            $this->line('Comptes disponibles:');
            $this->line('  • Super Admin: admin@leadmanager.com / password');
            $this->line('  • Propriétaire: owner@leadmanager.com / password');
            $this->line('  • Agents: agent1@leadmanager.com, agent2@leadmanager.com, agent3@leadmanager.com / password');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur: '.$e->getMessage());
            $this->error('Fichier: '.$e->getFile().':'.$e->getLine());

            return Command::FAILURE;
        }
    }

    private function fixRolesTable(): void
    {
        if (! Schema::hasTable('roles')) {
            $this->warn('  ⚠️  La table roles n\'existe pas. Exécutez d\'abord: php artisan migrate');

            return;
        }

        if (! Schema::hasColumn('roles', 'name')) {
            $sql = $this->getAddColumnSql('roles', 'name', 'VARCHAR(255)');
            DB::statement($sql);
            $this->line("  ✓ Colonne 'name' ajoutée");
        }

        if (! Schema::hasColumn('roles', 'slug')) {
            $sql = $this->getAddColumnSql('roles', 'slug', 'VARCHAR(255)');
            DB::statement($sql);
            $this->line("  ✓ Colonne 'slug' ajoutée");
        }

        if (! Schema::hasColumn('roles', 'description')) {
            $sql = $this->getAddColumnSql('roles', 'description', 'TEXT NULL');
            DB::statement($sql);
            $this->line("  ✓ Colonne 'description' ajoutée");
        }
    }

    private function fixUsersTable(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            $sql = $this->getAddColumnSql('users', 'role_id', 'BIGINT UNSIGNED NULL');
            DB::statement($sql);
            $this->line("  ✓ Colonne 'role_id' ajoutée");
        }

        if (! Schema::hasColumn('users', 'call_center_id')) {
            $sql = $this->getAddColumnSql('users', 'call_center_id', 'BIGINT UNSIGNED NULL');
            DB::statement($sql);
            $this->line("  ✓ Colonne 'call_center_id' ajoutée");
        }
    }

    private function fixCallCentersTable(): void
    {
        if (! Schema::hasTable('call_centers')) {
            return;
        }

        $requiredColumns = [
            'name' => 'VARCHAR(255)',
            'description' => 'TEXT NULL',
            'owner_id' => 'BIGINT UNSIGNED NOT NULL',
            'distribution_method' => "VARCHAR(255) DEFAULT 'round_robin'",
            'is_active' => 'BOOLEAN DEFAULT 1',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (! Schema::hasColumn('call_centers', $column)) {
                $sql = $this->getAddColumnSql('call_centers', $column, $definition);
                DB::statement($sql);
                $this->line("  ✓ Colonne '{$column}' ajoutée");
            }
        }
    }

    /**
     * Get SQL for adding a column, compatible with both MySQL and SQLite.
     */
    private function getAddColumnSql(string $table, string $column, string $definition): string
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        if ($connection === 'sqlite') {
            // SQLite doesn't support AFTER clause, and has different syntax
            $definition = str_replace(['BIGINT UNSIGNED', 'VARCHAR(255)', 'BOOLEAN'], ['INTEGER', 'TEXT', 'INTEGER'], $definition);
            $definition = str_replace('DEFAULT 1', 'DEFAULT 1', $definition);
            $definition = preg_replace('/\s+AFTER\s+\w+/i', '', $definition);

            return "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
        }

        // MySQL syntax
        return "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}";
    }

    private function createRoles(): array
    {
        $roles = [
            ['name' => 'Super Administrateur', 'slug' => 'super_admin', 'description' => 'Accès complet'],
            ['name' => 'Propriétaire de Centre d\'Appels', 'slug' => 'call_center_owner', 'description' => 'Gère les agents'],
            ['name' => 'Agent de Centre d\'Appels', 'slug' => 'agent', 'description' => 'Gère les leads'],
        ];

        $roleIds = [];

        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('slug', $role['slug'])->exists();
            if (! $exists) {
                $id = DB::table('roles')->insertGetId([
                    'name' => $role['name'],
                    'slug' => $role['slug'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $roleIds[$role['slug']] = $id;
                $this->line("  ✓ {$role['name']} créé (ID: {$id})");
            } else {
                $id = DB::table('roles')->where('slug', $role['slug'])->value('id');
                $roleIds[$role['slug']] = $id;
                $this->line("  - {$role['name']} existe déjà (ID: {$id})");
            }
        }

        return $roleIds;
    }

    private function createUsers(array $roleIds): void
    {
        // Super Admin
        $superAdminExists = DB::table('users')->where('email', 'admin@leadmanager.com')->exists();
        if (! $superAdminExists) {
            DB::table('users')->insert([
                'name' => 'Super Admin',
                'email' => 'admin@leadmanager.com',
                'password' => Hash::make('password'),
                'role_id' => $roleIds['super_admin'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->line('  ✓ Super Admin créé');
        } else {
            $this->line('  - Super Admin existe déjà');
        }

        // Propriétaire
        $ownerExists = DB::table('users')->where('email', 'owner@leadmanager.com')->exists();
        if (! $ownerExists) {
            $ownerId = DB::table('users')->insertGetId([
                'name' => 'Propriétaire Centre d\'Appels',
                'email' => 'owner@leadmanager.com',
                'password' => Hash::make('password'),
                'role_id' => $roleIds['call_center_owner'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->line('  ✓ Propriétaire créé');
        } else {
            $ownerId = DB::table('users')->where('email', 'owner@leadmanager.com')->value('id');
            $this->line('  - Propriétaire existe déjà');
        }

        // Centre d'Appels
        $callCenter = DB::table('call_centers')->where('owner_id', $ownerId)->first();
        if (! $callCenter) {
            $callCenterId = DB::table('call_centers')->insertGetId([
                'name' => 'Centre d\'Appels Principal',
                'description' => 'Centre d\'appels principal pour les tests',
                'owner_id' => $ownerId,
                'distribution_method' => 'round_robin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->line("  ✓ Centre d'Appels créé");
        } else {
            $callCenterId = $callCenter->id;
            $this->line("  - Centre d'Appels existe déjà");
        }

        // Associer le propriétaire au centre
        DB::table('users')->where('id', $ownerId)->update(['call_center_id' => $callCenterId]);

        // Agents
        $agents = [
            ['name' => 'Agent 1', 'email' => 'agent1@leadmanager.com'],
            ['name' => 'Agent 2', 'email' => 'agent2@leadmanager.com'],
            ['name' => 'Agent 3', 'email' => 'agent3@leadmanager.com'],
        ];

        foreach ($agents as $agent) {
            $agentExists = DB::table('users')->where('email', $agent['email'])->exists();
            if (! $agentExists) {
                DB::table('users')->insert([
                    'name' => $agent['name'],
                    'email' => $agent['email'],
                    'password' => Hash::make('password'),
                    'role_id' => $roleIds['agent'],
                    'call_center_id' => $callCenterId,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("  ✓ {$agent['name']} créé");
            } else {
                $this->line("  - {$agent['name']} existe déjà");
            }
        }
    }
}
