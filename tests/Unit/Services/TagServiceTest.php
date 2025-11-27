<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Category;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(TagService::class);
});

test('can create a tag', function () {
    $tag = $this->service->createTag('Hot Lead', '#FF0000', 'High priority leads');

    expect($tag)
        ->toBeInstanceOf(Tag::class)
        ->and($tag->name)->toBe('Hot Lead')
        ->and($tag->color)->toBe('#FF0000')
        ->and($tag->description)->toBe('High priority leads')
        ->and($tag->is_system)->toBeFalse();
});

test('can create a tag with category', function () {
    $category = Category::factory()->create();
    $tag = $this->service->createTag('VIP', '#FF0000', null, $category->id);

    expect($tag->category_id)->toBe($category->id);
});

test('can attach a tag to a lead', function () {
    $lead = Lead::factory()->create();
    $tag = Tag::factory()->create();
    $user = User::factory()->create();

    $this->service->attachTag($lead, $tag, $user);

    expect($lead->tags)->toHaveCount(1)
        ->and($lead->tags->first()->id)->toBe($tag->id);
});

test('does not attach duplicate tag to lead', function () {
    $lead = Lead::factory()->create();
    $tag = Tag::factory()->create();

    $this->service->attachTag($lead, $tag);
    $this->service->attachTag($lead, $tag);

    expect($lead->tags)->toHaveCount(1);
});

test('can detach a tag from a lead', function () {
    $lead = Lead::factory()->create();
    $tag = Tag::factory()->create();

    $lead->tags()->attach($tag->id);
    expect($lead->tags)->toHaveCount(1);

    $this->service->detachTag($lead, $tag);

    expect($lead->fresh()->tags)->toHaveCount(0);
});

test('can get tags for a lead', function () {
    $lead = Lead::factory()->create();
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();

    $lead->tags()->attach([$tag1->id, $tag2->id]);

    $tags = $this->service->getTagsForLead($lead);

    expect($tags)->toHaveCount(2)
        ->and($tags->pluck('id')->toArray())->toContain($tag1->id, $tag2->id);
});

test('can get popular tags', function () {
    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();
    $tag3 = Tag::factory()->create();

    Lead::factory()->count(5)->create()->each(function ($lead) use ($tag1) {
        $lead->tags()->attach($tag1->id);
    });

    Lead::factory()->count(3)->create()->each(function ($lead) use ($tag2) {
        $lead->tags()->attach($tag2->id);
    });

    Lead::factory()->count(1)->create()->each(function ($lead) use ($tag3) {
        $lead->tags()->attach($tag3->id);
    });

    $popular = $this->service->getPopularTags(null, 10);

    expect($popular->first()->id)->toBe($tag1->id)
        ->and($popular->pluck('id')->toArray())->toContain($tag1->id, $tag2->id, $tag3->id);
});

test('can get popular tags filtered by call center', function () {
    $callCenter1 = CallCenter::factory()->create();
    $callCenter2 = CallCenter::factory()->create();

    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();

    Lead::factory()->count(5)->create(['call_center_id' => $callCenter1->id])
        ->each(function ($lead) use ($tag1) {
            $lead->tags()->attach($tag1->id);
        });

    Lead::factory()->count(3)->create(['call_center_id' => $callCenter2->id])
        ->each(function ($lead) use ($tag2) {
            $lead->tags()->attach($tag2->id);
        });

    $popular = $this->service->getPopularTags($callCenter1, 10);

    expect($popular->first()->id)->toBe($tag1->id)
        ->and($popular->pluck('id')->toArray())->not->toContain($tag2->id);
});

test('cannot delete system tags', function () {
    $systemTag = Tag::factory()->create(['is_system' => true]);

    expect(fn () => $this->service->deleteTag($systemTag))
        ->toThrow(\Exception::class, __('Les tags système ne peuvent pas être supprimés.'));
});

test('can delete non-system tags', function () {
    $tag = Tag::factory()->create(['is_system' => false]);

    $result = $this->service->deleteTag($tag);

    expect($result)->toBeTrue()
        ->and(Tag::find($tag->id))->toBeNull();
});
