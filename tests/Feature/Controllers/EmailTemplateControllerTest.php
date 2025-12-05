<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\User;

describe('EmailTemplateController - Index', function () {
    test('lists all email templates', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        EmailTemplate::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson('/api/email-templates');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'subject',
                        'body_html',
                        'body_text',
                        'variables',
                    ],
                ],
            ]);
    });

    test('requires authentication', function () {
        $response = $this->getJson('/api/email-templates');

        $response->assertUnauthorized();
    });
});

describe('EmailTemplateController - Store', function () {
    test('creates email template successfully with required fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $data = [
            'name' => 'Test Template',
            'subject' => 'Welcome {{name}}',
            'body_html' => '<h1>Hello {{name}}</h1>',
            'body_text' => 'Hello {{name}}',
            'variables' => ['name', 'email'],
        ];

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/email-templates', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'subject',
                    'body_html',
                    'body_text',
                    'variables',
                ],
            ])
            ->assertJson([
                'message' => 'Template d\'email créé avec succès',
                'data' => [
                    'name' => 'Test Template',
                    'subject' => 'Welcome {{name}}',
                    'body_html' => '<h1>Hello {{name}}</h1>',
                    'body_text' => 'Hello {{name}}',
                    'variables' => ['name', 'email'],
                ],
            ]);

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Test Template',
            'subject' => 'Welcome {{name}}',
        ]);
    });

    test('creates template with only required fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $data = [
            'name' => 'Minimal Template',
            'subject' => 'Test Subject',
            'body_html' => '<p>Test body</p>',
        ];

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/email-templates', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Minimal Template',
        ]);
    });

    test('validates required fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->postJson('/api/email-templates', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'subject', 'body_html']);
    });
});

describe('EmailTemplateController - Show', function () {
    test('shows email template', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $template = EmailTemplate::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->getJson("/api/email-templates/{$template->id}");

        $response->assertSuccessful()
            ->assertJson([
                'data' => [
                    'id' => $template->id,
                    'name' => $template->name,
                ],
            ]);
    });
});

describe('EmailTemplateController - Update', function () {
    test('updates email template partially', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $template = EmailTemplate::factory()->create([
            'name' => 'Original Name',
            'subject' => 'Original Subject',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/email-templates/{$template->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Template d\'email mis à jour avec succès',
                'data' => [
                    'name' => 'Updated Name',
                    'subject' => 'Original Subject', // Should remain unchanged
                ],
            ]);

        $template->refresh();
        expect($template->name)->toBe('Updated Name')
            ->and($template->subject)->toBe('Original Subject');
    });

    test('updates all fields', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $template = EmailTemplate::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->putJson("/api/email-templates/{$template->id}", [
                'name' => 'Updated Name',
                'subject' => 'Updated Subject',
                'body_html' => '<p>Updated HTML</p>',
                'body_text' => 'Updated Text',
                'variables' => ['name', 'email', 'phone'],
            ]);

        $response->assertSuccessful();

        $template->refresh();
        expect($template->name)->toBe('Updated Name')
            ->and($template->subject)->toBe('Updated Subject')
            ->and($template->body_html)->toBe('<p>Updated HTML</p>')
            ->and($template->body_text)->toBe('Updated Text')
            ->and($template->variables)->toBe(['name', 'email', 'phone']);
    });
});

describe('EmailTemplateController - Destroy', function () {
    test('deletes email template if not used by forms', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $template = EmailTemplate::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/email-templates/{$template->id}");

        $response->assertSuccessful()
            ->assertJson([
                'message' => 'Template d\'email supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('email_templates', [
            'id' => $template->id,
        ]);
    });

    test('cannot delete email template if used by forms', function () {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'auth-token',
            'expires_at' => null,
        ]);

        $template = EmailTemplate::factory()->create();
        Form::factory()->create([
            'email_template_id' => $template->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer auth-token')
            ->deleteJson("/api/email-templates/{$template->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Ce template est utilisé par des formulaires et ne peut pas être supprimé',
            ]);

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
        ]);
    });
});
