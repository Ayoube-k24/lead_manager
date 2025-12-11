<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;

describe('TagService', function () {
    beforeEach(function () {
        $this->service = new TagService();
    });

    describe('createTag', function () {
        test('creates a new tag', function () {
            $tag = $this->service->createTag('Test Tag', '#FF0000', 'Test description');

            expect($tag)->toBeInstanceOf(Tag::class)
                ->and($tag->name)->toBe('Test Tag')
                ->and($tag->color)->toBe('#FF0000')
                ->and($tag->description)->toBe('Test description')
                ->and($tag->is_system)->toBeFalse();
        });

        test('uses default color when not provided', function () {
            $tag = $this->service->createTag('Test Tag');

            expect($tag->color)->toBe('#6B7280');
        });
    });

    describe('attachTag', function () {
        test('attaches tag to lead', function () {
            $lead = Lead::factory()->create();
            $tag = Tag::factory()->create();
            $user = User::factory()->create();

            $this->service->attachTag($lead, $tag, $user);

            expect($lead->tags()->where('tag_id', $tag->id)->exists())->toBeTrue();
        });

        test('does not attach duplicate tag', function () {
            $lead = Lead::factory()->create();
            $tag = Tag::factory()->create();

            $this->service->attachTag($lead, $tag);
            $this->service->attachTag($lead, $tag);

            expect($lead->tags()->where('tag_id', $tag->id)->count())->toBe(1);
        });
    });

    describe('detachTag', function () {
        test('detaches tag from lead', function () {
            $lead = Lead::factory()->create();
            $tag = Tag::factory()->create();

            $lead->tags()->attach($tag->id);

            $this->service->detachTag($lead, $tag);

            expect($lead->tags()->where('tag_id', $tag->id)->exists())->toBeFalse();
        });
    });

    describe('getTagsForLead', function () {
        test('returns tags for a lead', function () {
            $lead = Lead::factory()->create();
            $tag1 = Tag::factory()->create();
            $tag2 = Tag::factory()->create();

            $lead->tags()->attach([$tag1->id, $tag2->id]);

            $tags = $this->service->getTagsForLead($lead);

            expect($tags->count())->toBe(2)
                ->and($tags->pluck('id')->toArray())->toContain($tag1->id, $tag2->id);
        });
    });

    describe('getPopularTags', function () {
        test('returns popular tags ordered by usage', function () {
            $tag1 = Tag::factory()->create();
            $tag2 = Tag::factory()->create();
            $tag3 = Tag::factory()->create();

            Lead::factory()->count(5)->create()->each(fn ($lead) => $lead->tags()->attach($tag1->id));
            Lead::factory()->count(3)->create()->each(fn ($lead) => $lead->tags()->attach($tag2->id));
            Lead::factory()->count(1)->create()->each(fn ($lead) => $lead->tags()->attach($tag3->id));

            $tags = $this->service->getPopularTags(null, 10);

            expect($tags->first()->id)->toBe($tag1->id)
                ->and($tags->first()->leads_count)->toBe(5);
        });

        test('filters by call center', function () {
            $callCenter = CallCenter::factory()->create();
            $tag = Tag::factory()->create();

            Lead::factory()->count(3)->create([
                'call_center_id' => $callCenter->id,
            ])->each(fn ($lead) => $lead->tags()->attach($tag->id));

            Lead::factory()->count(2)->create([
                'call_center_id' => CallCenter::factory()->create()->id,
            ])->each(fn ($lead) => $lead->tags()->attach($tag->id));

            $tags = $this->service->getPopularTags($callCenter, 10);

            expect($tags->first()->leads_count)->toBe(3);
        });
    });

    describe('updateTag', function () {
        test('updates tag successfully', function () {
            $tag = Tag::factory()->create([
                'name' => 'Old Name',
                'color' => '#000000',
            ]);

            $updated = $this->service->updateTag($tag, [
                'name' => 'New Name',
                'color' => '#FFFFFF',
            ]);

            expect($updated->name)->toBe('New Name')
                ->and($updated->color)->toBe('#FFFFFF');
        });

        test('prevents updating system tag name', function () {
            $tag = Tag::factory()->create([
                'is_system' => true,
                'name' => 'System Tag',
            ]);

            expect(fn () => $this->service->updateTag($tag, ['name' => 'New Name']))
                ->toThrow(Exception::class);
        });
    });

    describe('deleteTag', function () {
        test('deletes custom tag', function () {
            $tag = Tag::factory()->create(['is_system' => false]);

            $result = $this->service->deleteTag($tag);

            expect($result)->toBeTrue()
                ->and(Tag::find($tag->id))->toBeNull();
        });

        test('prevents deleting system tag', function () {
            $tag = Tag::factory()->create(['is_system' => true]);

            expect(fn () => $this->service->deleteTag($tag))
                ->toThrow(Exception::class);
        });
    });

    describe('getAllTagsWithCount', function () {
        test('returns tags with usage count', function () {
            $tag = Tag::factory()->create();
            Lead::factory()->count(3)->create()->each(fn ($lead) => $lead->tags()->attach($tag->id));

            $tags = $this->service->getAllTagsWithCount();

            expect($tags->firstWhere('id', $tag->id)->leads_count)->toBe(3);
        });
    });
});



