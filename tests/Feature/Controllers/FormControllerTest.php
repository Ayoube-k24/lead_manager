<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;

describe('FormController - Index', function () {
    test('super admin sees all forms with relations', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $superAdminRole->id]);
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        Form::factory()->count(2)->create(['call_center_id' => $callCenter1->id]);
        Form::factory()->count(3)->create(['call_center_id' => $callCenter2->id]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson('/api/forms');

        $response->assertSuccessful()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'smtp_profile',
                        'email_template',
                        'call_center',
                    ],
                ],
            ]);
    });

    test('call center owner sees only their forms', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);
        $token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token' => 'auth-token',
        ]);

        Form::factory()->count(2)->create(['call_center_id' => $callCenter1->id]);
        Form::factory()->count(3)->create(['call_center_id' => $callCenter2->id]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson('/api/forms');

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data');
    });

    test('requires authentication', function () {
        $response = $this->getJson('/api/forms');

        $response->assertUnauthorized();
    });
});

describe('FormController - Store', function () {
    test('creates form successfully with all required fields', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $superAdminRole->id]);
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $data = [
            'name' => 'Test Form',
            'description' => 'Test Description',
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
            ],
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'is_active' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/forms', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'smtp_profile',
                    'email_template',
                    'call_center',
                ],
            ])
            ->assertJson([
                'message' => 'Formulaire créé avec succès',
                'data' => [
                    'name' => 'Test Form',
                ],
            ]);

        $this->assertDatabaseHas('forms', [
            'name' => 'Test Form',
            'call_center_id' => $callCenter->id,
        ]);
    });

    test('validates required fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/forms', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'fields', 'call_center_id']);
    });

    test('validates fields structure', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $callCenter = CallCenter::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/forms', [
                'name' => 'Test',
                'fields' => [
                    [
                        'name' => 'test',
                        // Missing type and label
                    ],
                ],
                'call_center_id' => $callCenter->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.type', 'fields.0.label']);
    });

    test('validates field types', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $callCenter = CallCenter::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/forms', [
                'name' => 'Test',
                'fields' => [
                    [
                        'name' => 'test',
                        'type' => 'invalid_type',
                        'label' => 'Test',
                    ],
                ],
                'call_center_id' => $callCenter->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.type']);
    });

    test('super admin can create form for any call center', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $superAdminRole->id]);
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/forms', [
                'name' => 'Test Form',
                'fields' => [
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ],
                'call_center_id' => $callCenter->id,
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

        $response->assertStatus(201);
    });

    test('owner can only create form for their call center', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);
        $token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token' => 'auth-token',
        ]);

        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        // Try to create form for different call center
        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/forms', [
                'name' => 'Test Form',
                'fields' => [
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ],
                'call_center_id' => $callCenter2->id,
                'smtp_profile_id' => $smtpProfile->id,
                'email_template_id' => $emailTemplate->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Vous n\'avez pas l\'autorisation de créer un formulaire pour ce centre d\'appels',
            ]);
    });
});

describe('FormController - Show', function () {
    test('shows form with relations loaded', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $form = Form::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson("/api/forms/{$form->id}");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'smtp_profile',
                    'email_template',
                    'call_center',
                ],
            ]);
    });

    test('owner cannot see form from another call center', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);
        $token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token' => 'auth-token',
        ]);

        $form = Form::factory()->create(['call_center_id' => $callCenter2->id]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson("/api/forms/{$form->id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Formulaire non trouvé',
            ]);
    });
});

describe('FormController - Update', function () {
    test('updates form partially', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $form = Form::factory()->create(['name' => 'Original Name']);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/forms/{$form->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Formulaire mis à jour avec succès',
                'data' => [
                    'name' => 'Updated Name',
                ],
            ]);

        $form->refresh();
        expect($form->name)->toBe('Updated Name');
    });

    test('validates fields when updating', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $form = Form::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/forms/{$form->id}", [
                'fields' => [
                    [
                        'name' => 'test',
                        // Missing type and label
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.type', 'fields.0.label']);
    });

    test('validates field validation_rules structure', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $form = Form::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/forms/{$form->id}", [
                'fields' => [
                    [
                        'name' => 'age',
                        'type' => 'number',
                        'label' => 'Age',
                        'validation_rules' => [
                            'min' => -1, // Invalid: min must be >= 0
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.validation_rules.min']);
    });

    test('owner cannot change call center to another', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);
        $token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token' => 'auth-token',
        ]);

        $form = Form::factory()->create(['call_center_id' => $callCenter1->id]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/forms/{$form->id}", [
                'call_center_id' => $callCenter2->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Vous n\'avez pas l\'autorisation de modifier le centre d\'appels',
            ]);
    });

    test('super admin can change call center', function () {
        $superAdminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $superAdminRole->id]);
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter1->id]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/forms/{$form->id}", [
                'call_center_id' => $callCenter2->id,
            ]);

        $response->assertSuccessful();

        $form->refresh();
        expect($form->call_center_id)->toBe($callCenter2->id);
    });
});

describe('FormController - Destroy', function () {
    test('deletes form successfully', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null, // No expiration
        ]);

        $form = Form::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/forms/{$form->id}");

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Formulaire supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('forms', [
            'id' => $form->id,
        ]);
    });

    test('owner cannot delete form from another call center', function () {
        $ownerRole = Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
        $callCenter1 = CallCenter::factory()->create();
        $callCenter2 = CallCenter::factory()->create();

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter1->id,
        ]);
        $token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token' => 'auth-token',
        ]);

        $form = Form::factory()->create(['call_center_id' => $callCenter2->id]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/forms/{$form->id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Formulaire non trouvé',
            ]);
    });
});
