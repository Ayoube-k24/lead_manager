<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\MailWizzConfig;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    // Force run migrations if tables don't exist
    if (! \Illuminate\Support\Facades\Schema::hasTable('mailwizz_configs')) {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    }

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->role()->associate(Role::factory()->create([
        'name' => 'Super Admin',
        'slug' => 'super_admin',
    ]));
    $this->superAdmin->save();
});

test('super admin can view mailwizz configs list', function () {
    MailWizzConfig::factory()->count(3)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.mailwizz.index'));

    $response->assertOk()
        ->assertSee('Configuration MailWizz');
});

test('super admin can create mailwizz config', function () {
    $callCenter = CallCenter::factory()->create();

    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.create')
        ->set('api_url', 'https://test.mailwizz.com')
        ->set('public_key', 'test-public-key')
        ->set('private_key', 'test-private-key')
        ->set('list_uid', 'test-list-uid')
        ->set('call_center_id', $callCenter->id)
        ->set('import_frequency', 15)
        ->set('is_active', true)
        ->call('store')
        ->assertRedirect(route('admin.mailwizz.index'));

    expect(MailWizzConfig::where('api_url', 'https://test.mailwizz.com')->exists())->toBeTrue();
});

test('super admin can update mailwizz config', function () {
    $config = MailWizzConfig::factory()->create();
    $newCallCenter = CallCenter::factory()->create();

    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.edit', ['config' => $config])
        ->set('api_url', 'https://updated.mailwizz.com')
        ->set('import_frequency', 30)
        ->set('call_center_id', $newCallCenter->id)
        ->call('update')
        ->assertRedirect(route('admin.mailwizz.index'));

    $config->refresh();

    expect($config->api_url)->toBe('https://updated.mailwizz.com')
        ->and($config->import_frequency)->toBe(30)
        ->and($config->call_center_id)->toBe($newCallCenter->id);
});

test('super admin can toggle config active status', function () {
    $config = MailWizzConfig::factory()->create(['is_active' => true]);

    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.index')
        ->call('toggleActive', $config->id);

    expect($config->fresh()->is_active)->toBeFalse();
});

test('super admin can delete mailwizz config', function () {
    $config = MailWizzConfig::factory()->create();

    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.index')
        ->call('delete', $config->id);

    expect(MailWizzConfig::find($config->id))->toBeNull();
});

test('super admin can trigger manual import', function () {
    $config = MailWizzConfig::factory()->create([
        'is_active' => true,
        'api_url' => 'https://test.mailwizz.com',
        'public_key' => 'test-public',
        'private_key' => 'test-private',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        '*/authenticate' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => ['access_token' => 'test-token'],
        ], 200),
        '*/subscribers*' => \Illuminate\Support\Facades\Http::response([
            'status' => 'success',
            'data' => [],
        ], 200),
    ]);

    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.index')
        ->call('importNow', $config->id)
        ->assertHasNoErrors();
});

test('validation fails for invalid api url', function () {
    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.create')
        ->set('api_url', 'not-a-valid-url')
        ->set('public_key', 'test-public')
        ->set('private_key', 'test-private')
        ->set('import_frequency', 15)
        ->call('store')
        ->assertHasErrors(['api_url']);
});

test('validation fails for invalid import frequency', function () {
    $callCenter = CallCenter::factory()->create();

    Volt::actingAs($this->superAdmin)
        ->test('admin.mailwizz.create')
        ->set('api_url', 'https://test.mailwizz.com')
        ->set('public_key', 'test-public')
        ->set('private_key', 'test-private')
        ->set('call_center_id', $callCenter->id)
        ->set('import_frequency', 99) // Invalid frequency
        ->call('store')
        ->assertHasErrors(['import_frequency']);
});

test('non-admin cannot access mailwizz configs', function () {
    $user = User::factory()->create();
    $user->role()->associate(Role::factory()->create([
        'name' => 'Agent',
        'slug' => 'agent',
    ]));
    $user->save();

    $response = $this->actingAs($user)
        ->get(route('admin.mailwizz.index'));

    $response->assertForbidden();
});
