<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

describe('ExportController', function () {
    describe('exportLeadsCsv', function () {
        test('exports leads to CSV for super admin', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            Lead::factory()->count(3)->create();

            $response = $this->actingAs($user)->get(route('admin.leads.export.csv'));

            $response->assertSuccessful()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Content-Disposition', function ($value) {
                    return str_contains($value, 'attachment') && str_contains($value, '.csv');
                });
        });

        test('filters leads by call center for non-admin users', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
            $user = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
            ]);

            $lead1 = Lead::factory()->create(['call_center_id' => $callCenter->id]);
            $lead2 = Lead::factory()->create(['call_center_id' => $callCenter->id]);
            $otherCallCenter = CallCenter::factory()->create();
            Lead::factory()->count(3)->create(['call_center_id' => $otherCallCenter->id]);

            $response = $this->actingAs($user)->get(route('owner.leads.export.csv'));

            $response->assertSuccessful();
            $content = $response->getContent();
            expect($content)->toContain((string) $lead1->id)
                ->and($content)->toContain((string) $lead2->id);
        });

        test('filters leads by status', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            $confirmedLead1 = Lead::factory()->create(['status' => 'confirmed']);
            $confirmedLead2 = Lead::factory()->create(['status' => 'confirmed']);
            Lead::factory()->count(3)->create(['status' => 'rejected']);

            $response = $this->actingAs($user)->get(route('admin.leads.export.csv', ['status' => 'confirmed']));

            $response->assertSuccessful();
            $content = $response->getContent();
            expect($content)->toContain((string) $confirmedLead1->id)
                ->and($content)->toContain((string) $confirmedLead2->id);
        });

        test('filters leads by date range', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            $oldLead = Lead::factory()->create(['created_at' => now()->subDays(5)]);
            $recentLead1 = Lead::factory()->create(['created_at' => now()->subDays(2)]);
            $recentLead2 = Lead::factory()->create(['created_at' => now()->subHours(2)]);

            $response = $this->actingAs($user)->get(route('admin.leads.export.csv', [
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

            $response->assertSuccessful();
            $content = $response->getContent();
            expect($content)->not->toContain((string) $oldLead->id)
                ->and($content)->toContain((string) $recentLead1->id)
                ->and($content)->toContain((string) $recentLead2->id);
        });

        test('includes all required CSV columns', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            $form = Form::factory()->create();
            $callCenter = CallCenter::factory()->create();
            $agent = User::factory()->create(['call_center_id' => $callCenter->id]);
            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'assigned_to' => $agent->id,
                'data' => ['name' => 'Test User', 'phone' => '123456789'],
            ]);

            $response = $this->actingAs($user)->get(route('admin.leads.export.csv'));

            $response->assertSuccessful();
            $content = $response->getContent();
            expect($content)->toContain('ID')
                ->and($content)->toContain('Email')
                ->and($content)->toContain('Nom')
                ->and($content)->toContain('Téléphone')
                ->and($content)->toContain('Formulaire')
                ->and($content)->toContain('Centre d\'appels')
                ->and($content)->toContain('Agent')
                ->and($content)->toContain('Statut')
                ->and($content)->toContain('Email confirmé le')
                ->and($content)->toContain('Appelé le')
                ->and($content)->toContain('Commentaire')
                ->and($content)->toContain('Créé le');
        });

        test('requires authentication', function () {
            $response = $this->get(route('admin.leads.export.csv'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('exportStatisticsCsv', function () {
        test('exports global statistics for super admin', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            Lead::factory()->count(5)->create();

            $response = $this->actingAs($user)->get(route('admin.statistics.export.csv'));

            $response->assertSuccessful()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Content-Disposition', function ($value) {
                    return str_contains($value, 'attachment') && str_contains($value, '.csv');
                });
        });

        test('exports call center statistics for owner', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
            $user = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
            ]);

            Lead::factory()->count(3)->create(['call_center_id' => $callCenter->id]);

            $response = $this->actingAs($user)->get(route('owner.statistics.export.csv'));

            $response->assertSuccessful()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        });

        test('returns 403 for users without call center', function () {
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $user = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => null,
            ]);

            $response = $this->actingAs($user)->get(route('admin.statistics.export.csv'));

            $response->assertForbidden();
        });

        test('includes all required statistics columns', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            $response = $this->actingAs($user)->get(route('admin.statistics.export.csv'));

            $response->assertSuccessful();
            $content = $response->getContent();
            expect($content)->toContain('Statistique')
                ->and($content)->toContain('Valeur')
                ->and($content)->toContain('Total Leads')
                ->and($content)->toContain('Leads Confirmés')
                ->and($content)->toContain('Leads Rejetés')
                ->and($content)->toContain('Leads en Attente')
                ->and($content)->toContain('Taux de Conversion')
                ->and($content)->toContain('Temps de Traitement Moyen');
        });

        test('requires authentication', function () {
            $response = $this->get(route('admin.statistics.export.csv'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('exportStatisticsPdf', function () {
        test('exports statistics to PDF for super admin', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);

            Lead::factory()->count(5)->create();

            $response = $this->actingAs($user)->get(route('admin.statistics.export.pdf'));

            $response->assertSuccessful()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', function ($value) {
                    return str_contains($value, 'attachment') && str_contains($value, '.pdf');
                });
        });

        test('exports statistics to PDF for call center owner', function () {
            $callCenter = CallCenter::factory()->create();
            $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
            $user = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => $callCenter->id,
            ]);

            Lead::factory()->count(3)->create(['call_center_id' => $callCenter->id]);

            $response = $this->actingAs($user)->get(route('owner.statistics.export.pdf'));

            $response->assertSuccessful()
                ->assertHeader('Content-Type', 'application/pdf');
        });

        test('returns 403 for users without call center', function () {
            $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $user = User::factory()->create([
                'role_id' => $role->id,
                'call_center_id' => null,
            ]);

            $response = $this->actingAs($user)->get(route('admin.statistics.export.pdf'));

            $response->assertForbidden();
        });

        test('requires authentication', function () {
            $response = $this->get(route('admin.statistics.export.pdf'));

            $response->assertRedirect(route('login'));
        });
    });
});
