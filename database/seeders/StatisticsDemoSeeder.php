<?php

namespace Database\Seeders;

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StatisticsDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Création des données de démonstration pour les statistiques...');

        // Get roles
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $ownerRole = Role::where('slug', 'call_center_owner')->first();
        $agentRole = Role::where('slug', 'agent')->first();

        // Create call centers
        $callCenter1 = CallCenter::factory()->create([
            'name' => 'Centre d\'Appels Paris',
            'distribution_method' => 'round_robin',
        ]);

        $callCenter2 = CallCenter::factory()->create([
            'name' => 'Centre d\'Appels Lyon',
            'distribution_method' => 'weighted',
        ]);

        // Create forms
        $form1 = Form::factory()->create(['name' => 'Formulaire Démo 1']);
        $form2 = Form::factory()->create(['name' => 'Formulaire Démo 2']);

        // Create agents for call center 1
        $agents1 = [];
        for ($i = 1; $i <= 5; $i++) {
            $agent = User::factory()->create([
                'name' => "Agent Paris {$i}",
                'email' => "agent.paris{$i}@demo.com",
                'call_center_id' => $callCenter1->id,
            ]);
            $agent->role()->associate($agentRole);
            $agent->save();
            $agents1[] = $agent;
        }

        // Create agents for call center 2
        $agents2 = [];
        for ($i = 1; $i <= 3; $i++) {
            $agent = User::factory()->create([
                'name' => "Agent Lyon {$i}",
                'email' => "agent.lyon{$i}@demo.com",
                'call_center_id' => $callCenter2->id,
            ]);
            $agent->role()->associate($agentRole);
            $agent->save();
            $agents2[] = $agent;
        }

        $this->command->info('✅ Centres d\'appels et agents créés');

        // Create leads over the last 60 days with various statuses
        $this->createLeadsForAgent($agents1[0], $form1, $callCenter1, 30, 0.7); // 70% conversion rate
        $this->createLeadsForAgent($agents1[1], $form1, $callCenter1, 25, 0.5); // 50% conversion rate
        $this->createLeadsForAgent($agents1[2], $form2, $callCenter1, 20, 0.3); // 30% conversion rate (underperforming)
        $this->createLeadsForAgent($agents1[3], $form1, $callCenter1, 15, 0.6); // 60% conversion rate
        $this->createLeadsForAgent($agents1[4], $form2, $callCenter1, 10, 0.4); // 40% conversion rate

        $this->createLeadsForAgent($agents2[0], $form1, $callCenter2, 28, 0.65); // 65% conversion rate
        $this->createLeadsForAgent($agents2[1], $form2, $callCenter2, 22, 0.45); // 45% conversion rate
        $this->createLeadsForAgent($agents2[2], $form1, $callCenter2, 18, 0.55); // 55% conversion rate

        // Create some unassigned leads
        $this->createUnassignedLeads($form1, $callCenter1, 10);
        $this->createUnassignedLeads($form2, $callCenter2, 8);

        // Create some leads needing attention (confirmed email but not called)
        $this->createLeadsNeedingAttention($form1, $callCenter1, 5);
        $this->createLeadsNeedingAttention($form2, $callCenter2, 3);

        $this->command->info('✅ Leads créés avec différents statuts et dates');
        $this->command->info('📊 Statistiques de démonstration prêtes !');
    }

    /**
     * Create leads for an agent with specified conversion rate.
     */
    protected function createLeadsForAgent(
        User $agent,
        Form $form,
        CallCenter $callCenter,
        int $totalLeads,
        float $conversionRate
    ): void {
        $confirmedCount = (int) round($totalLeads * $conversionRate);
        $rejectedCount = (int) round($totalLeads * (1 - $conversionRate) * 0.7);
        $pendingCount = $totalLeads - $confirmedCount - $rejectedCount;

        // Create confirmed leads
        for ($i = 0; $i < $confirmedCount; $i++) {
            $daysAgo = rand(1, 60);
            $emailConfirmedAt = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 24));
            $calledAt = $emailConfirmedAt->copy()->addHours(rand(1, 48));

            Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => $agent->id,
                'status' => 'confirmed',
                'email_confirmed_at' => $emailConfirmedAt,
                'called_at' => $calledAt,
                'created_at' => Carbon::now()->subDays($daysAgo),
            ]);
        }

        // Create rejected leads
        for ($i = 0; $i < $rejectedCount; $i++) {
            $daysAgo = rand(1, 60);
            $emailConfirmedAt = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 24));
            $calledAt = $emailConfirmedAt->copy()->addHours(rand(1, 48));

            Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => $agent->id,
                'status' => 'rejected',
                'email_confirmed_at' => $emailConfirmedAt,
                'called_at' => $calledAt,
                'call_comment' => 'Lead non intéressé',
                'created_at' => Carbon::now()->subDays($daysAgo),
            ]);
        }

        // Create pending leads
        for ($i = 0; $i < $pendingCount; $i++) {
            $daysAgo = rand(1, 30);
            $status = ['pending_email', 'email_confirmed', 'pending_call'][rand(0, 2)];

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => $agent->id,
                'status' => $status,
                'created_at' => Carbon::now()->subDays($daysAgo),
            ]);

            if ($status === 'email_confirmed' || $status === 'pending_call') {
                $lead->email_confirmed_at = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 24));
                $lead->save();
            }
        }
    }

    /**
     * Create unassigned leads.
     */
    protected function createUnassignedLeads(Form $form, CallCenter $callCenter, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $daysAgo = rand(1, 30);
            $status = ['pending_email', 'email_confirmed'][rand(0, 1)];

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => null,
                'status' => $status,
                'created_at' => Carbon::now()->subDays($daysAgo),
            ]);

            if ($status === 'email_confirmed') {
                $lead->email_confirmed_at = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 24));
                $lead->save();
            }
        }
    }

    /**
     * Create leads needing attention (confirmed email but not called for > 48h).
     */
    protected function createLeadsNeedingAttention(Form $form, CallCenter $callCenter, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $daysAgo = rand(3, 10);
            $emailConfirmedAt = Carbon::now()->subDays($daysAgo)->subHours(rand(50, 100));

            Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => null,
                'status' => 'email_confirmed',
                'email_confirmed_at' => $emailConfirmedAt,
                'called_at' => null,
                'created_at' => Carbon::now()->subDays($daysAgo),
            ]);
        }
    }
}
