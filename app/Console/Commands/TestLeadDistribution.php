<?php

namespace App\Console\Commands;

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Console\Command;

class TestLeadDistribution extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:test-distribution {lead_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test lead distribution for debugging';

    /**
     * Execute the console command.
     */
    public function handle(LeadDistributionService $distributionService): int
    {
        $leadId = $this->argument('lead_id');

        if ($leadId) {
            $lead = Lead::with(['form', 'callCenter', 'assignedAgent'])->find($leadId);
            if (! $lead) {
                $this->error("Lead #{$leadId} not found");

                return Command::FAILURE;
            }
            $this->testSingleLead($lead, $distributionService);
        } else {
            // Test all leads without assigned_to
            $leads = Lead::with(['form', 'callCenter'])
                ->whereNull('assigned_to')
                ->whereNotNull('call_center_id')
                ->whereIn('status', ['email_confirmed', 'pending_call'])
                ->limit(10)
                ->get();

            if ($leads->isEmpty()) {
                $this->info('No leads found to test distribution');
                $this->newLine();
                $this->info('Checking for leads with issues:');
                $this->checkLeadsIssues();

                return Command::SUCCESS;
            }

            $this->info("Found {$leads->count()} leads to test");
            $this->newLine();

            foreach ($leads as $lead) {
                $this->testSingleLead($lead, $distributionService);
                $this->newLine();
            }
        }

        return Command::SUCCESS;
    }

    protected function testSingleLead(Lead $lead, LeadDistributionService $distributionService): void
    {
        $this->info("Testing Lead #{$lead->id}");
        $this->line("  Email: {$lead->email}");
        $this->line("  Status: {$lead->status}");
        $this->line('  Call Center ID: '.($lead->call_center_id ?? 'NULL'));
        $this->line('  Form ID: '.($lead->form_id ?? 'NULL'));
        $this->line('  Form Call Center ID: '.($lead->form?->call_center_id ?? 'NULL'));
        $this->line('  Assigned To: '.($lead->assigned_to ?? 'NULL'));

        if (! $lead->call_center_id) {
            if ($lead->form && $lead->form->call_center_id) {
                $this->warn('  ⚠️  Lead has no call_center_id, but form has one. Updating...');
                $lead->call_center_id = $lead->form->call_center_id;
                $lead->save();
                $lead->refresh();
                $this->info("  ✅ Updated call_center_id to {$lead->call_center_id}");
            } else {
                $this->error('  ❌ Lead has no call_center_id and form has no call_center_id');
                $this->line('     Cannot distribute this lead.');

                return;
            }
        }

        $callCenter = CallCenter::with('users')->find($lead->call_center_id);
        if (! $callCenter) {
            $this->error("  ❌ Call Center #{$lead->call_center_id} not found");

            return;
        }

        $this->line("  Call Center: {$callCenter->name}");
        $this->line("  Distribution Method: {$callCenter->distribution_method}");

        // Check for agents
        $agents = User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->with('role')
            ->get()
            ->filter(fn ($user) => $user->isAgent());

        if ($agents->isEmpty()) {
            $this->error('  ❌ No agents found for this call center');
            $this->line('     Total users in call center: '.$callCenter->users->count());

            return;
        }

        $this->info("  ✅ Found {$agents->count()} agent(s)");
        foreach ($agents as $agent) {
            $this->line("     - Agent #{$agent->id}: {$agent->name} (Role: {$agent->role?->slug})");
        }

        // Try to distribute
        $this->newLine();
        $this->info('  Attempting distribution...');
        $agent = $distributionService->distributeLead($lead);

        if ($agent) {
            $this->info("  ✅ Agent selected: {$agent->name} (#{$agent->id})");

            // Try to assign
            if ($distributionService->assignToAgent($lead, $agent)) {
                $lead->refresh();
                $this->info('  ✅ Lead assigned successfully!');
                $this->line("     Assigned To: {$lead->assigned_to}");
                $this->line("     Status: {$lead->status}");
            } else {
                $this->error('  ❌ Failed to assign lead to agent');
            }
        } else {
            $this->error('  ❌ No agent returned from distribution service');
        }
    }

    protected function checkLeadsIssues(): void
    {
        // Leads without call_center_id
        $leadsWithoutCallCenter = Lead::whereNull('call_center_id')
            ->whereHas('form', function ($q) {
                $q->whereNotNull('call_center_id');
            })
            ->count();

        if ($leadsWithoutCallCenter > 0) {
            $this->warn("  - {$leadsWithoutCallCenter} leads without call_center_id (but form has one)");
        }

        // Leads with call_center_id but no agents
        $callCentersWithoutAgents = CallCenter::whereDoesntHave('users', function ($q) {
            $q->whereHas('role', fn ($r) => $r->where('slug', 'agent'));
        })->pluck('id');

        if ($callCentersWithoutAgents->isNotEmpty()) {
            $leadsWithoutAgents = Lead::whereIn('call_center_id', $callCentersWithoutAgents)
                ->whereNull('assigned_to')
                ->whereIn('status', ['email_confirmed', 'pending_call'])
                ->count();

            if ($leadsWithoutAgents > 0) {
                $this->warn("  - {$leadsWithoutAgents} leads in call centers without agents");
            }
        }

        // Leads with manual distribution
        $manualCallCenters = CallCenter::where('distribution_method', 'manual')->pluck('id');
        if ($manualCallCenters->isNotEmpty()) {
            $manualLeads = Lead::whereIn('call_center_id', $manualCallCenters)
                ->whereNull('assigned_to')
                ->whereIn('status', ['email_confirmed', 'pending_call'])
                ->count();

            if ($manualLeads > 0) {
                $this->info("  - {$manualLeads} leads in call centers with manual distribution (requires manual assignment)");
            }
        }
    }
}
