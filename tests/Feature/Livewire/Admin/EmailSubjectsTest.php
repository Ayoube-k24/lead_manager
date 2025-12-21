<?php

declare(strict_types=1);

use App\Models\EmailSubject;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

describe('Admin Email Subjects', function () {
    test('admin can view email subjects list', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        EmailSubject::factory()->count(5)->create();

        $response = $this->actingAs($user)->get(route('admin.email-subjects'));

        $response->assertSuccessful();
    });

    test('admin can create email subject', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('admin.email-subjects.create'));

        $response->assertSuccessful();
    });

    test('admin can store email subject', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        Volt::test('admin.email-subjects.create')
            ->actingAs($user)
            ->set('subject', 'Test Subject')
            ->set('default_template_html', '<p>Test</p>')
            ->set('is_active', true)
            ->set('order', 1)
            ->call('store')
            ->assertRedirect(route('admin.email-subjects'));

        expect(EmailSubject::where('subject', 'Test Subject')->exists())->toBeTrue();
    });

    test('admin can edit email subject', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $emailSubject = EmailSubject::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.email-subjects.edit', $emailSubject));

        $response->assertSuccessful();
    });

    test('admin can update email subject', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $emailSubject = EmailSubject::factory()->create(['subject' => 'Old Subject']);

        Volt::test('admin.email-subjects.edit', ['emailSubject' => $emailSubject])
            ->actingAs($user)
            ->set('subject', 'New Subject')
            ->call('update')
            ->assertRedirect(route('admin.email-subjects'));

        expect($emailSubject->fresh()->subject)->toBe('New Subject');
    });

    test('admin can delete email subject', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        $emailSubject = EmailSubject::factory()->create();

        Volt::test('admin.email-subjects')
            ->actingAs($user)
            ->call('delete', $emailSubject)
            ->assertDispatched('email-subject-deleted');

        expect(EmailSubject::find($emailSubject->id))->toBeNull();
    });

    test('non-admin cannot access email subjects', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('admin.email-subjects'));

        $response->assertForbidden();
    });

    test('email subjects search works', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->withoutTwoFactor()->create([
            'role_id' => $superAdminRole->id,
            'is_active' => true,
        ]);

        EmailSubject::factory()->create(['subject' => 'Devis mutuelle santé']);
        EmailSubject::factory()->create(['subject' => 'Proposition assurance']);

        Volt::test('admin.email-subjects')
            ->actingAs($user)
            ->set('search', 'mutuelle')
            ->assertSee('Devis mutuelle santé')
            ->assertDontSee('Proposition assurance');
    });
});






