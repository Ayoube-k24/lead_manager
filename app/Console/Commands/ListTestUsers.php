<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all test users in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::with('role')->get();

        if ($users->isEmpty()) {
            $this->warn('Aucun utilisateur trouvé dans la base de données.');
            $this->info('Exécutez: php artisan db:seed --class=UserSeeder');

            return Command::FAILURE;
        }

        $this->info('Utilisateurs dans la base de données:');
        $this->newLine();

        $tableData = $users->map(function ($user) {
            return [
                'ID' => $user->id,
                'Nom' => $user->name,
                'Email' => $user->email,
                'Rôle' => $user->role?->name ?? 'Aucun',
                'Centre d\'Appels' => $user->call_center_id ? 'Oui' : 'Non',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Nom', 'Email', 'Rôle', 'Centre d\'Appels'],
            $tableData
        );

        return Command::SUCCESS;
    }
}
