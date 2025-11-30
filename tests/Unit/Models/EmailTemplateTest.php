<?php

declare(strict_types=1);

use App\Models\EmailTemplate;
use App\Models\Form;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('EmailTemplate Model - Basic Properties', function () {
    test('can be created with all required fields', function () {
        // Arrange & Act
        $template = EmailTemplate::factory()->create([
            'name' => 'Test Template',
            'subject' => 'Test Subject',
            'body_html' => '<p>Test HTML</p>',
            'body_text' => 'Test Text',
        ]);

        // Assert
        expect($template->name)->toBe('Test Template')
            ->and($template->subject)->toBe('Test Subject')
            ->and($template->body_html)->toBe('<p>Test HTML</p>')
            ->and($template->body_text)->toBe('Test Text');
    });

    test('can be created without body_text', function () {
        // Arrange & Act
        $template = EmailTemplate::factory()->create(['body_text' => null]);

        // Assert
        expect($template->body_text)->toBeNull();
    });
});

describe('EmailTemplate Model - Casts', function () {
    test('casts variables to array', function () {
        // Arrange
        $variables = ['name', 'email', 'phone', 'company'];
        $template = EmailTemplate::factory()->create(['variables' => $variables]);

        // Act & Assert
        expect($template->variables)->toBeArray()
            ->and($template->variables)->toBe($variables);
    });

    test('handles null variables gracefully', function () {
        // Arrange
        $template = EmailTemplate::factory()->create(['variables' => null]);

        // Act & Assert
        expect($template->variables)->toBeNull();
    });

    test('can store complex variables array', function () {
        // Arrange
        $variables = [
            'name' => '{{name}}',
            'email' => '{{email}}',
            'phone' => '{{phone}}',
            'custom' => [
                'field1' => '{{field1}}',
                'field2' => '{{field2}}',
            ],
        ];
        $template = EmailTemplate::factory()->create(['variables' => $variables]);

        // Act & Assert
        expect($template->variables)->toBeArray()
            ->and($template->variables)->toBe($variables)
            ->and($template->variables['custom'])->toBeArray();
    });
});

describe('EmailTemplate Model - Relationships', function () {
    test('has many forms', function () {
        // Arrange
        $template = EmailTemplate::factory()->create();
        Form::factory()->count(3)->create(['email_template_id' => $template->id]);

        // Act
        $forms = $template->forms;

        // Assert
        expect($forms)->toHaveCount(3);
    });

    test('returns empty collection when no forms assigned', function () {
        // Arrange
        $template = EmailTemplate::factory()->create();

        // Act
        $forms = $template->forms;

        // Assert
        expect($forms)->toBeEmpty();
    });
});

describe('EmailTemplate Model - HTML Content', function () {
    test('can store complex HTML content', function () {
        // Arrange
        $html = '<html><body><h1>Title</h1><p>Content with <strong>bold</strong> text</p></body></html>';
        $template = EmailTemplate::factory()->create(['body_html' => $html]);

        // Act & Assert
        expect($template->body_html)->toBe($html)
            ->and($template->body_html)->toContain('<h1>')
            ->and($template->body_html)->toContain('<strong>');
    });

    test('can store plain text content', function () {
        // Arrange
        $text = 'This is a plain text email template with no HTML formatting.';
        $template = EmailTemplate::factory()->create(['body_text' => $text]);

        // Act & Assert
        expect($template->body_text)->toBe($text);
    });
});

describe('EmailTemplate Model - Subject Line', function () {
    test('can store subject with variables', function () {
        // Arrange
        $subject = 'Welcome {{name}}!';
        $template = EmailTemplate::factory()->create(['subject' => $subject]);

        // Act & Assert
        expect($template->subject)->toBe($subject)
            ->and($template->subject)->toContain('{{name}}');
    });

    test('can store simple subject without variables', function () {
        // Arrange
        $subject = 'Welcome to our service';
        $template = EmailTemplate::factory()->create(['subject' => $subject]);

        // Act & Assert
        expect($template->subject)->toBe($subject);
    });
});

