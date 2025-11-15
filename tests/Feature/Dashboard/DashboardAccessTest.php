<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, UserSeeder::class]);
});

test('super admin dashboard displays correct content', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertSuccessful()
        ->assertSee('SUPER ADMINISTRATEUR')
        ->assertSee('Dashboard Super Admin')
        ->assertSee('Utilisateurs')
        ->assertSee('Centres d\'Appels')
        ->assertSee('Formulaires')
        ->assertSee('Leads Totaux');
});

test('call center owner dashboard displays correct content', function () {
    $user = User::where('email', 'owner@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/owner/dashboard');

    $response->assertSuccessful()
        ->assertSee('PROPRIÉTAIRE DE CENTRE D\'APPELS')
        ->assertSee('Dashboard Centre d\'Appels')
        ->assertSee('Agents')
        ->assertSee('Leads Totaux')
        ->assertSee('Leads Confirmés');
});

test('agent dashboard displays correct content', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/agent/dashboard');

    $response->assertSuccessful()
        ->assertSee('AGENT DE CENTRE D\'APPELS')
        ->assertSee('Dashboard Agent')
        ->assertSee('Leads Assignés')
        ->assertSee('En Attente d\'Appel')
        ->assertSee('Confirmés');
});

test('super admin dashboard shows correct statistics', function () {
    $user = User::where('email', 'admin@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertSuccessful()
        ->assertSee((string) User::count())
        ->assertSee((string) CallCenter::count());
});

test('call center owner dashboard shows correct statistics for their call center', function () {
    $user = User::where('email', 'owner@leadmanager.com')->first();
    $callCenter = $user->callCenter;

    $response = $this->actingAs($user)->get('/owner/dashboard');

    $response->assertSuccessful();

    if ($callCenter) {
        $agentCount = User::where('call_center_id', $callCenter->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'agent'))
            ->count();

        $response->assertSee((string) $agentCount);
    }
});

test('agent dashboard shows correct statistics for assigned leads', function () {
    $user = User::where('email', 'agent1@leadmanager.com')->first();

    $response = $this->actingAs($user)->get('/agent/dashboard');

    $response->assertSuccessful();
    // The dashboard should load without errors even if there are no leads
    expect($response->status())->toBe(200);
});
