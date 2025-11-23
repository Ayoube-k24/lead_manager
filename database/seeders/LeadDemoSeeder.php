<?php

namespace Database\Seeders;

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeadDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('  📊 Création des données de démonstration pour les leads');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->newLine();

        // Vérifier que les données de base existent
        $forms = Form::all();
        $callCenters = CallCenter::all();
        $agentRole = Role::where('slug', 'agent')->first();

        if ($forms->isEmpty()) {
            $this->command->warn('⚠️  Aucun formulaire trouvé. Créez d\'abord des formulaires.');
            $this->command->info('   Exécutez: php artisan db:seed --class=FormSeeder');

            return;
        }

        if ($callCenters->isEmpty()) {
            $this->command->warn('⚠️  Aucun centre d\'appels trouvé. Créez d\'abord des centres d\'appels.');
            $this->command->info('   Exécutez: php artisan db:seed --class=DemoDataSeeder');

            return;
        }

        $agents = User::whereHas('role', fn ($q) => $q->where('slug', 'agent'))->get();

        if ($agents->isEmpty()) {
            $this->command->warn('⚠️  Aucun agent trouvé. Créez d\'abord des agents.');
            $this->command->info('   Exécutez: php artisan db:seed --class=UserSeeder');

            return;
        }

        $this->command->info('📋 Données de base trouvées:');
        $this->command->line("   • Formulaires: {$forms->count()}");
        $this->command->line("   • Centres d'appels: {$callCenters->count()}");
        $this->command->line("   • Agents: {$agents->count()}");
        $this->command->newLine();

        // Répartir les agents par centre d'appels
        $agentsByCenter = [];
        foreach ($agents as $agent) {
            if ($agent->call_center_id) {
                $agentsByCenter[$agent->call_center_id][] = $agent;
            }
        }

        $totalLeads = 0;

        // Créer des leads pour chaque centre d'appels
        foreach ($callCenters as $callCenter) {
            $this->command->info("🏢 Création des leads pour: {$callCenter->name}");

            $centerAgents = $agentsByCenter[$callCenter->id] ?? [];

            if (empty($centerAgents)) {
                $this->command->warn("   ⚠️  Aucun agent trouvé pour ce centre d'appels, création de leads non assignés");
            }

            // Leads confirmés (40% du total)
            $confirmedCount = 40;
            $this->createLeadsWithStatus(
                $forms,
                $callCenter,
                $centerAgents,
                'confirmed',
                $confirmedCount,
                '✅ Leads confirmés'
            );
            $totalLeads += $confirmedCount;

            // Leads rejetés (20% du total)
            $rejectedCount = 20;
            $this->createLeadsWithStatus(
                $forms,
                $callCenter,
                $centerAgents,
                'rejected',
                $rejectedCount,
                '❌ Leads rejetés'
            );
            $totalLeads += $rejectedCount;

            // Leads en attente d'email (15% du total)
            $pendingEmailCount = 15;
            $this->createLeadsWithStatus(
                $forms,
                $callCenter,
                [],
                'pending_email',
                $pendingEmailCount,
                '📧 Leads en attente de confirmation email'
            );
            $totalLeads += $pendingEmailCount;

            // Leads email confirmé mais pas encore appelé (10% du total)
            $emailConfirmedCount = 10;
            $this->createLeadsWithStatus(
                $forms,
                $callCenter,
                $centerAgents,
                'email_confirmed',
                $emailConfirmedCount,
                '✉️  Leads email confirmé'
            );
            $totalLeads += $emailConfirmedCount;

            // Leads en attente d'appel (10% du total)
            $pendingCallCount = 10;
            $this->createLeadsWithStatus(
                $forms,
                $callCenter,
                $centerAgents,
                'pending_call',
                $pendingCallCount,
                '📞 Leads en attente d\'appel'
            );
            $totalLeads += $pendingCallCount;

            // Leads en attente de rappel (5% du total)
            $callbackPendingCount = 5;
            $this->createLeadsWithStatus(
                $forms,
                $callCenter,
                $centerAgents,
                'callback_pending',
                $callbackPendingCount,
                '🔄 Leads en attente de rappel'
            );
            $totalLeads += $callbackPendingCount;

            $this->command->newLine();
        }

        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info("  ✅ {$totalLeads} leads créés avec succès!");
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->newLine();

        $this->displaySummary();
    }

    /**
     * Create leads with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Form>  $forms
     * @param  array<User>  $agents
     */
    protected function createLeadsWithStatus(
        $forms,
        CallCenter $callCenter,
        array $agents,
        string $status,
        int $count,
        string $label
    ): void {
        $this->command->line("   {$label}: {$count}");

        for ($i = 0; $i < $count; $i++) {
            $form = $forms->random();
            $daysAgo = rand(1, 90);
            $createdAt = Carbon::now()->subDays($daysAgo);

            // Sélectionner un agent aléatoirement si disponible
            $agent = ! empty($agents) ? collect($agents)->random() : null;

            // Générer des données réalistes basées sur les champs du formulaire
            $data = $this->generateLeadData($form);

            // Créer le lead avec le statut approprié
            $leadAttributes = [
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'data' => $data,
                'email' => $data['email'] ?? $data['email_address'] ?? fake()->safeEmail(),
                'assigned_to' => $agent?->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            // Ajouter les attributs spécifiques selon le statut
            match ($status) {
                'pending_email' => $leadAttributes = array_merge($leadAttributes, [
                    'status' => 'pending_email',
                    'email_confirmed_at' => null,
                    'email_confirmation_token' => Str::random(64),
                    'email_confirmation_token_expires_at' => $createdAt->copy()->addHours(24),
                    'assigned_to' => null,
                    'called_at' => null,
                ]),
                'email_confirmed' => $leadAttributes = array_merge($leadAttributes, [
                    'status' => 'email_confirmed',
                    'email_confirmed_at' => $createdAt->copy()->subHours(rand(1, 48)),
                    'email_confirmation_token' => null,
                    'email_confirmation_token_expires_at' => null,
                    'called_at' => null,
                ]),
                'pending_call' => $leadAttributes = array_merge($leadAttributes, [
                    'status' => 'pending_call',
                    'email_confirmed_at' => $createdAt->copy()->subHours(rand(1, 24)),
                    'email_confirmation_token' => null,
                    'email_confirmation_token_expires_at' => null,
                    'called_at' => null,
                ]),
                'confirmed' => $leadAttributes = array_merge($leadAttributes, [
                    'status' => 'confirmed',
                    'email_confirmed_at' => $emailConfirmedAt = $createdAt->copy()->subHours(rand(1, 24)),
                    'email_confirmation_token' => null,
                    'email_confirmation_token_expires_at' => null,
                    'called_at' => $emailConfirmedAt->copy()->addHours(rand(1, 48)),
                    'call_comment' => fake()->optional(0.3)->randomElement([
                        'Client très intéressé',
                        'Rendez-vous pris',
                        'Devis envoyé',
                        'Suivi dans 1 semaine',
                        'Excellent prospect',
                    ]),
                ]),
                'rejected' => $leadAttributes = array_merge($leadAttributes, [
                    'status' => 'rejected',
                    'email_confirmed_at' => $emailConfirmedAt = $createdAt->copy()->subHours(rand(1, 24)),
                    'email_confirmation_token' => null,
                    'email_confirmation_token_expires_at' => null,
                    'called_at' => $emailConfirmedAt->copy()->addHours(rand(1, 48)),
                    'call_comment' => fake()->randomElement([
                        'Lead non intéressé',
                        'Prix trop élevé',
                        'Déjà client de la concurrence',
                        'Pas de budget disponible',
                        'Besoin non prioritaire',
                        'Ne répond pas au téléphone',
                        'Email invalide',
                        'Numéro de téléphone incorrect',
                    ]),
                ]),
                'callback_pending' => $leadAttributes = array_merge($leadAttributes, [
                    'status' => 'callback_pending',
                    'email_confirmed_at' => $emailConfirmedAt = $createdAt->copy()->subHours(rand(1, 24)),
                    'email_confirmation_token' => null,
                    'email_confirmation_token_expires_at' => null,
                    'called_at' => $emailConfirmedAt->copy()->addHours(rand(1, 24)),
                    'call_comment' => fake()->randomElement([
                        'Rappel demandé pour demain',
                        'Client occupé, rappel dans 2h',
                        'Pas disponible, rappel dans 1 semaine',
                        'Rappel programmé pour la semaine prochaine',
                    ]),
                ]),
                default => $leadAttributes['status'] = $status,
            };

            Lead::create($leadAttributes);
        }
    }

    /**
     * Generate realistic lead data based on form fields.
     *
     * @return array<string, mixed>
     */
    protected function generateLeadData(Form $form): array
    {
        $fields = $form->fields ?? [];
        $data = [];
        $email = fake()->safeEmail();

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'text';

            $value = match ($fieldType) {
                'email' => $email,
                'tel', 'phone' => fake()->phoneNumber(),
                'text' => $this->generateTextValue($fieldName),
                'textarea' => fake()->paragraph(),
                'select' => collect($field['options'] ?? [])->random(),
                'number' => fake()->numberBetween(0, 100),
                'date' => fake()->date(),
                'checkbox' => fake()->boolean(),
                default => fake()->word(),
            };

            $data[$fieldName] = $value;
        }

        // Si aucun champ email n'existe, ajouter l'email généré
        if (! isset($data['email']) && ! isset($data['email_address'])) {
            $data['email'] = $email;
        }

        return $data;
    }

    /**
     * Generate text value based on field name.
     */
    protected function generateTextValue(string $fieldName): string
    {
        return match (strtolower($fieldName)) {
            'first_name', 'prenom' => fake()->firstName(),
            'last_name', 'nom' => fake()->lastName(),
            'name', 'nom_complet' => fake()->name(),
            'company', 'company_name', 'entreprise', 'societe' => fake()->company(),
            'address', 'adresse' => fake()->address(),
            'city', 'ville' => fake()->city(),
            'postal_code', 'code_postal' => fake()->postcode(),
            'country', 'pays' => fake()->country(),
            default => fake()->words(rand(2, 4), true),
        };
    }

    /**
     * Display summary of created leads.
     */
    protected function displaySummary(): void
    {
        $this->command->info('📊 Résumé des leads créés:');
        $this->command->newLine();

        $this->command->line('  📈 Répartition par statut:');
        $statuses = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->pluck('count', 'status')
            ->toArray();

        $statusLabels = [
            'pending_email' => 'En attente de confirmation email',
            'email_confirmed' => 'Email confirmé',
            'pending_call' => 'En attente d\'appel',
            'confirmed' => 'Confirmé',
            'rejected' => 'Rejeté',
            'callback_pending' => 'En attente de rappel',
        ];

        foreach ($statuses as $status => $count) {
            $label = $statusLabels[$status] ?? $status;
            $this->command->line("    • {$label}: {$count}");
        }

        $this->command->newLine();
        $this->command->line('  📊 Répartition par centre d\'appels:');
        $callCenterStats = Lead::selectRaw('call_centers.name, COUNT(leads.id) as count')
            ->join('call_centers', 'leads.call_center_id', '=', 'call_centers.id')
            ->groupBy('call_centers.name')
            ->orderByDesc('count')
            ->pluck('count', 'name')
            ->toArray();

        foreach ($callCenterStats as $name => $count) {
            $this->command->line("    • {$name}: {$count}");
        }

        $this->command->newLine();
        $this->command->line('  📝 Répartition par formulaire:');
        $formStats = Lead::selectRaw('forms.name, COUNT(leads.id) as count')
            ->join('forms', 'leads.form_id', '=', 'forms.id')
            ->groupBy('forms.name')
            ->orderByDesc('count')
            ->pluck('count', 'name')
            ->toArray();

        foreach ($formStats as $name => $count) {
            $this->command->line("    • {$name}: {$count}");
        }

        $this->command->newLine();
        $assignedCount = Lead::whereNotNull('assigned_to')->count();
        $unassignedCount = Lead::whereNull('assigned_to')->count();
        $this->command->line("  👤 Leads assignés: {$assignedCount}");
        $this->command->line("  👤 Leads non assignés: {$unassignedCount}");

        $this->command->newLine();
        $this->command->info('🎉 Les données de démonstration sont prêtes!');
    }
}
