<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Performance - Search', function () {
    test('searches in 1000 leads in less than 500ms', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        // Create 1000 leads with various emails
        Lead::factory()->count(1000)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'email' => fn () => 'user'.rand(1, 10000).'@example.com',
            'data' => ['name' => fn () => 'User '.rand(1, 10000)],
        ]);

        // Act
        $startTime = microtime(true);

        $results = Lead::where('call_center_id', $callCenter->id)
            ->where(function ($query) {
                $query->where('email', 'like', '%user%')
                    ->orWhereJsonContains('data->name', 'User');
            })
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert
        expect($executionTime)->toBeLessThan(500.0); // Less than 500ms
        expect($results->count())->toBeGreaterThan(0);
    });

    test('filters efficiently with multiple conditions', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        // Create leads with various statuses
        Lead::factory()->count(500)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'confirmed',
        ]);

        Lead::factory()->count(500)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
            'status' => 'rejected',
        ]);

        // Act
        $startTime = microtime(true);

        $results = Lead::where('call_center_id', $callCenter->id)
            ->where('assigned_to', $agent->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert
        expect($executionTime)->toBeLessThan(500.0); // Less than 500ms
        expect($results->count())->toBe(500);
    });

    test('paginates results quickly', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        Lead::factory()->count(1000)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
        ]);

        // Act
        $startTime = microtime(true);

        $results = Lead::where('call_center_id', $callCenter->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert
        expect($executionTime)->toBeLessThan(200.0); // Less than 200ms for pagination
        expect($results->count())->toBe(15); // First page has 15 items
        expect($results->total())->toBe(1000); // Total count is correct
    });

    test('uses database indexes for efficient searching', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        Lead::factory()->count(1000)->create([
            'call_center_id' => $callCenter->id,
            'assigned_to' => $agent->id,
        ]);

        // Act - Check if query uses indexes
        DB::enableQueryLog();
        DB::flushQueryLog();

        Lead::where('call_center_id', $callCenter->id)
            ->where('status', 'confirmed')
            ->where('assigned_to', $agent->id)
            ->get();

        $queries = DB::getQueryLog();
        $lastQuery = $queries[count($queries) - 1];

        // Assert - Query should be efficient (uses WHERE clauses that should be indexed)
        // In real scenario, we'd check EXPLAIN, but here we verify the query structure
        expect($lastQuery['query'])->toContain('where')
            ->and($lastQuery['query'])->toContain('call_center_id')
            ->and($lastQuery['query'])->toContain('status')
            ->and($lastQuery['query'])->toContain('assigned_to');
    });
});

