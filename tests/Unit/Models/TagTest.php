<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Tag Model - Deletion', function () {
    test('returns true for canBeDeleted when tag is not system', function () {
        // Arrange
        $tag = Tag::factory()->create(['is_system' => false]);

        // Act & Assert
        expect($tag->canBeDeleted())->toBeTrue();
    });

    test('returns false for canBeDeleted when tag is system', function () {
        // Arrange
        $tag = Tag::factory()->create(['is_system' => true]);

        // Act & Assert
        expect($tag->canBeDeleted())->toBeFalse();
    });
});

describe('Tag Model - Relationships', function () {
    test('belongs to category', function () {
        // Arrange
        $category = Category::factory()->create();
        $tag = Tag::factory()->create(['category_id' => $category->id]);

        // Act
        $tagCategory = $tag->category;

        // Assert
        expect($tagCategory)->toBeInstanceOf(Category::class)
            ->and($tagCategory->id)->toBe($category->id);
    });

    test('belongs to many leads', function () {
        // Arrange
        $tag = Tag::factory()->create();
        $lead1 = Lead::factory()->create();
        $lead2 = Lead::factory()->create();

        $tag->leads()->attach([$lead1->id, $lead2->id]);

        // Act
        $tagLeads = $tag->leads;

        // Assert
        expect($tagLeads)->toHaveCount(2)
            ->and($tagLeads->pluck('id')->toArray())->toContain($lead1->id, $lead2->id);
    });

    test('includes pivot data when attaching to leads', function () {
        // Arrange
        $tag = Tag::factory()->create();
        $lead = Lead::factory()->create();
        $user = User::factory()->create();

        $tag->leads()->attach($lead->id, ['user_id' => $user->id]);

        // Act
        $pivot = $tag->leads()->where('lead_id', $lead->id)->first()->pivot;

        // Assert
        expect($pivot->user_id)->toBe($user->id);
    });
});

describe('Tag Model - Casts', function () {
    test('casts is_system to boolean', function () {
        // Arrange
        $tag = Tag::factory()->create(['is_system' => 1]);

        // Act & Assert
        expect($tag->is_system)->toBeBool()
            ->and($tag->is_system)->toBeTrue();
    });
});

describe('Tag Model - Scopes', function () {
    test('system scope returns only system tags', function () {
        // Arrange
        $system1 = Tag::factory()->create(['is_system' => true]);
        $system2 = Tag::factory()->create(['is_system' => true]);
        Tag::factory()->create(['is_system' => false]);

        // Act
        $systemTags = Tag::system()->get();

        // Assert
        expect($systemTags)->toHaveCount(2)
            ->and($systemTags->pluck('id')->toArray())->toContain($system1->id, $system2->id);
    });

    test('userDefined scope returns only user-defined tags', function () {
        // Arrange
        $user1 = Tag::factory()->create(['is_system' => false]);
        $user2 = Tag::factory()->create(['is_system' => false]);
        Tag::factory()->create(['is_system' => true]);

        // Act
        $userTags = Tag::userDefined()->get();

        // Assert
        expect($userTags)->toHaveCount(2)
            ->and($userTags->pluck('id')->toArray())->toContain($user1->id, $user2->id);
    });
});
