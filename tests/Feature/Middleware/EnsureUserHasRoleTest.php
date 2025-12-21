<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

describe('EnsureUserHasRole Middleware', function () {
    beforeEach(function () {
        Route::middleware('role:super_admin')->get('/test-route', function () {
            return response()->json(['message' => 'Success']);
        });
    });

    test('allows access when user has required role', function () {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->get('/test-route');

        $response->assertSuccessful()
            ->assertJson(['message' => 'Success']);
    });

    test('denies access when user does not have required role', function () {
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->get('/test-route');

        $response->assertForbidden();
    });

    test('redirects unauthenticated users to login', function () {
        $response = $this->get('/test-route');

        $response->assertRedirect(route('login'));
    });

    test('returns JSON response for unauthenticated API requests', function () {
        $response = $this->getJson('/test-route');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    });

    test('returns JSON response for unauthorized API requests', function () {
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->getJson('/test-route');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized action.']);
    });

    test('handles multiple roles', function () {
        Route::middleware('role:super_admin,call_center_owner')->get('/test-multi-role', function () {
            return response()->json(['message' => 'Success']);
        });

        $role = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->get('/test-multi-role');

        $response->assertSuccessful();
    });

    test('denies access when user has no role', function () {
        $user = User::factory()->create(['role_id' => null]);

        $response = $this->actingAs($user)->get('/test-route');

        $response->assertForbidden();
    });
});






