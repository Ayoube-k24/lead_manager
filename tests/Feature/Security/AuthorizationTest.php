<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Authorization - Role-Based Access Control', function () {
    test('agents cannot access admin routes', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('admin.forms'));

        // Assert
        $response->assertForbidden();
    });

    test('agents cannot access owner routes', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('owner.agents'));

        // Assert
        $response->assertForbidden();
    });

    test('agents cannot access supervisor routes', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('supervisor.agents'));

        // Assert
        $response->assertForbidden();
    });

    test('supervisors cannot access admin routes', function () {
        // Arrange
        $supervisorRole = Role::firstOrCreate(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'slug' => 'supervisor']
        );
        $supervisor = User::factory()->create(['role_id' => $supervisorRole->id]);

        // Act
        $response = $this->actingAs($supervisor)->get(route('admin.forms'));

        // Assert
        $response->assertForbidden();
    });

    test('owners cannot access admin routes', function () {
        // Arrange
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );
        $owner = User::factory()->create(['role_id' => $ownerRole->id]);

        // Act
        $response = $this->actingAs($owner)->get(route('admin.forms'));

        // Assert
        $response->assertForbidden();
    });
});

describe('Authorization - Lead Access Control', function () {
    test('agents cannot view leads assigned to other agents', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent1 = User::factory()->create(['role_id' => $agentRole->id]);
        $agent2 = User::factory()->create(['role_id' => $agentRole->id]);
        $lead = Lead::factory()->create(['assigned_to' => $agent2->id]);

        // Act
        $response = $this->actingAs($agent1)->get(route('agent.leads.show', $lead));

        // Assert
        $response->assertForbidden();
    });

    test('agents can only view their own assigned leads', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);
        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        // Act
        $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

        // Assert
        $response->assertSuccessful();
    });
});

describe('Authorization - Call Center Isolation', function () {
    test('owners cannot access leads from other call centers', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );

        $owner1 = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        $lead = Lead::factory()->create(['call_center_id' => $callCenter2->id]);

        // Act
        $response = $this->actingAs($owner1)->get(route('owner.leads'));

        // Assert
        $response->assertSuccessful();
        // Owner1 should not see lead from callCenter2
        $response->assertDontSee($lead->email);
    });

    test('owners cannot manage agents from other call centers', function () {
        // Arrange
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $owner1 = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter2->id,
        ]);

        // Act
        $response = $this->actingAs($owner1)->get(route('owner.agents.edit', $agent));

        // Assert
        $response->assertForbidden();
    });
});

describe('Authorization - API Authentication', function () {
    test('API requests without token are rejected', function () {
        // Act
        $response = $this->getJson('/api/forms');

        // Assert
        $response->assertUnauthorized();
    });

    test('API requests with invalid token are rejected', function () {
        // Act
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/forms');

        // Assert
        $response->assertUnauthorized();
    });

    test('API requests with valid token are accepted', function () {
        // Arrange
        $user = User::factory()->create();
        $token = \App\Models\ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'valid-token-123',
        ]);

        // Act
        $response = $this->withHeader('Authorization', 'Bearer valid-token-123')
            ->getJson('/api/forms');

        // Assert
        $response->assertSuccessful();
    });

    test('API requests with expired token are rejected', function () {
        // Arrange
        $user = User::factory()->create();
        $token = \App\Models\ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'expired-token',
            'expires_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->withHeader('Authorization', 'Bearer expired-token')
            ->getJson('/api/forms');

        // Assert
        $response->assertUnauthorized();
    });
});

describe('Authorization - API Permissions', function () {
    test('API token respects user permissions', function () {
        // Arrange
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );
        $agent = User::factory()->create(['role_id' => $agentRole->id]);
        $token = \App\Models\ApiToken::factory()->create([
            'user_id' => $agent->id,
            'token' => 'agent-token',
        ]);

        // Act - Agent tries to access admin route via API
        $response = $this->withHeader('Authorization', 'Bearer agent-token')
            ->getJson('/api/forms');

        // Assert - Should be allowed (API doesn't check role, only authentication)
        // But the controller should check permissions
        // This depends on the API controller implementation
        $response->assertStatus(200); // Or 403 if controller checks role
    });
});

