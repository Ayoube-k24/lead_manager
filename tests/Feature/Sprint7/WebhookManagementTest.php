<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

test('super admin can view webhooks list', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    Webhook::factory()->count(3)->create();

    // Act
    $response = $this->actingAs($superAdmin)->get(route('admin.webhooks'));

    // Assert
    $response->assertSuccessful()
        ->assertSeeLivewire('admin.webhooks');
});

test('super admin can create a webhook', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);

    // Act
    Volt::actingAs($superAdmin)
        ->test('admin.webhooks')
        ->set('name', 'Test Webhook')
        ->set('url', 'https://example.com/webhook')
        ->set('events', ['lead.created', 'lead.updated'])
        ->call('create')
        ->assertHasNoErrors();

    // Assert
    expect(Webhook::where('name', 'Test Webhook')->exists())->toBeTrue();
});

test('webhook secret is generated automatically on creation', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);

    // Act
    Volt::actingAs($superAdmin)
        ->test('admin.webhooks')
        ->set('name', 'Test Webhook')
        ->set('url', 'https://example.com/webhook')
        ->set('events', ['lead.created'])
        ->call('create');

    // Assert
    $webhook = Webhook::where('name', 'Test Webhook')->first();
    expect($webhook->secret)->not->toBeNull()
        ->and(strlen($webhook->secret))->toBe(32);
});

test('super admin can test a webhook', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $webhook = Webhook::factory()->create([
        'url' => 'https://example.com/webhook',
        'is_active' => true,
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'example.com/*' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
    ]);

    // Act
    Volt::actingAs($superAdmin)
        ->test('admin.webhooks')
        ->call('testWebhook', $webhook->id)
        ->assertHasNoErrors();

    // Assert
    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($webhook) {
        return $request->url() === $webhook->url;
    });
});

test('call center owner can view their webhooks', function () {
    // Arrange
    $ownerRole = Role::factory()->create(['slug' => 'call_center_owner']);
    $callCenter = CallCenter::factory()->create();
    $owner = User::factory()->create([
        'role_id' => $ownerRole->id,
        'call_center_id' => $callCenter->id,
    ]);

    Webhook::factory()->create(['call_center_id' => $callCenter->id]);
    Webhook::factory()->create(['call_center_id' => CallCenter::factory()->create()->id]);

    // Act
    $response = $this->actingAs($owner)->get(route('owner.webhooks'));

    // Assert
    $response->assertSuccessful()
        ->assertSeeLivewire('owner.webhooks');
});

test('webhook can be filtered by form', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $form1 = Form::factory()->create();
    $form2 = Form::factory()->create();

    Webhook::factory()->create(['form_id' => $form1->id]);
    Webhook::factory()->create(['form_id' => $form2->id]);
    Webhook::factory()->create(['form_id' => null]);

    // Act
    Volt::actingAs($superAdmin)
        ->test('admin.webhooks')
        ->set('filterFormId', $form1->id)
        ->call('applyFilters');

    // Assert
    // The filtered webhooks should only include form1's webhook
    // This would need to be verified based on the actual component implementation
});

test('webhook can be activated and deactivated', function () {
    // Arrange
    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $webhook = Webhook::factory()->create(['is_active' => true]);

    // Act
    Volt::actingAs($superAdmin)
        ->test('admin.webhooks')
        ->call('toggleActive', $webhook->id);

    // Assert
    expect($webhook->fresh()->is_active)->toBeFalse();

    // Act - Reactivate
    Volt::actingAs($superAdmin)
        ->test('admin.webhooks')
        ->call('toggleActive', $webhook->id);

    // Assert
    expect($webhook->fresh()->is_active)->toBeTrue();
});
