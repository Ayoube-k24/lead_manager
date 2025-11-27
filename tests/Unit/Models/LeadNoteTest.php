<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('LeadNote Model - Visibility', function () {
    test('returns true for isVisibleTo when note is public', function () {
        // Arrange
        $note = LeadNote::factory()->create(['is_private' => false]);
        $user = User::factory()->create();

        // Act & Assert
        expect($note->isVisibleTo($user))->toBeTrue();
    });

    test('returns true for isVisibleTo when note is public and no user provided', function () {
        // Arrange
        $note = LeadNote::factory()->create(['is_private' => false]);

        // Act & Assert
        expect($note->isVisibleTo(null))->toBeTrue();
    });

    test('returns true for isVisibleTo when note is private and user is author', function () {
        // Arrange
        $author = User::factory()->create();
        $note = LeadNote::factory()->create([
            'is_private' => true,
            'user_id' => $author->id,
        ]);

        // Act & Assert
        expect($note->isVisibleTo($author))->toBeTrue();
    });

    test('returns true for isVisibleTo when note is private and user is super admin', function () {
        // Arrange
        $author = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
        $superAdmin->role()->associate($superAdminRole);
        $superAdmin->save();

        $note = LeadNote::factory()->create([
            'is_private' => true,
            'user_id' => $author->id,
        ]);

        // Act & Assert
        expect($note->isVisibleTo($superAdmin))->toBeTrue();
    });

    test('returns false for isVisibleTo when note is private and user is not author or admin', function () {
        // Arrange
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $note = LeadNote::factory()->create([
            'is_private' => true,
            'user_id' => $author->id,
        ]);

        // Act & Assert
        expect($note->isVisibleTo($otherUser))->toBeFalse();
    });

    test('returns false for isVisibleTo when note is private and no user provided', function () {
        // Arrange
        $note = LeadNote::factory()->create(['is_private' => true]);

        // Act & Assert
        expect($note->isVisibleTo(null))->toBeFalse();
    });
});

describe('LeadNote Model - Scopes', function () {
    test('public scope returns only public notes', function () {
        // Arrange
        $public1 = LeadNote::factory()->create(['is_private' => false]);
        $public2 = LeadNote::factory()->create(['is_private' => false]);
        LeadNote::factory()->create(['is_private' => true]);

        // Act
        $publicNotes = LeadNote::public()->get();

        // Assert
        expect($publicNotes)->toHaveCount(2)
            ->and($publicNotes->pluck('id')->toArray())->toContain($public1->id, $public2->id);
    });

    test('private scope returns only private notes', function () {
        // Arrange
        $private1 = LeadNote::factory()->create(['is_private' => true]);
        $private2 = LeadNote::factory()->create(['is_private' => true]);
        LeadNote::factory()->create(['is_private' => false]);

        // Act
        $privateNotes = LeadNote::private()->get();

        // Assert
        expect($privateNotes)->toHaveCount(2)
            ->and($privateNotes->pluck('id')->toArray())->toContain($private1->id, $private2->id);
    });

    test('byType scope filters by note type', function () {
        // Arrange
        $callLog1 = LeadNote::factory()->create(['type' => 'call_log']);
        $callLog2 = LeadNote::factory()->create(['type' => 'call_log']);
        LeadNote::factory()->create(['type' => 'comment']);

        // Act
        $callLogs = LeadNote::byType('call_log')->get();

        // Assert
        expect($callLogs)->toHaveCount(2)
            ->and($callLogs->pluck('id')->toArray())->toContain($callLog1->id, $callLog2->id);
    });
});

describe('LeadNote Model - Relationships', function () {
    test('belongs to lead', function () {
        // Arrange
        $lead = Lead::factory()->create();
        $note = LeadNote::factory()->create(['lead_id' => $lead->id]);

        // Act
        $noteLead = $note->lead;

        // Assert
        expect($noteLead)->toBeInstanceOf(Lead::class)
            ->and($noteLead->id)->toBe($lead->id);
    });

    test('belongs to user', function () {
        // Arrange
        $user = User::factory()->create();
        $note = LeadNote::factory()->create(['user_id' => $user->id]);

        // Act
        $noteUser = $note->user;

        // Assert
        expect($noteUser)->toBeInstanceOf(User::class)
            ->and($noteUser->id)->toBe($user->id);
    });
});

describe('LeadNote Model - Casts', function () {
    test('casts is_private to boolean', function () {
        // Arrange
        $note = LeadNote::factory()->create(['is_private' => 1]);

        // Act & Assert
        expect($note->is_private)->toBeBool()
            ->and($note->is_private)->toBeTrue();
    });

    test('casts attachments to array', function () {
        // Arrange
        $attachments = [
            ['name' => 'file1.pdf', 'path' => '/uploads/file1.pdf'],
            ['name' => 'file2.jpg', 'path' => '/uploads/file2.jpg'],
        ];
        $note = LeadNote::factory()->create(['attachments' => $attachments]);

        // Act & Assert
        expect($note->attachments)->toBeArray()
            ->and($note->attachments)->toBe($attachments);
    });

    test('handles null attachments gracefully', function () {
        // Arrange
        $note = LeadNote::factory()->create(['attachments' => null]);

        // Act & Assert
        expect($note->attachments)->toBeNull();
    });
});
