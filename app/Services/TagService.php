<?php

namespace App\Services;

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;

class TagService
{
    /**
     * Create a new tag.
     */
    public function createTag(
        string $name,
        string $color = '#6B7280',
        ?string $description = null,
        ?int $categoryId = null
    ): Tag {
        return Tag::create([
            'name' => $name,
            'color' => $color,
            'description' => $description,
            'category_id' => $categoryId,
            'is_system' => false,
        ]);
    }

    /**
     * Attach a tag to a lead.
     */
    public function attachTag(Lead $lead, Tag $tag, ?User $user = null): void
    {
        if (! $lead->tags()->where('tag_id', $tag->id)->exists()) {
            $lead->tags()->attach($tag->id, [
                'user_id' => $user?->id,
            ]);
        }
    }

    /**
     * Detach a tag from a lead.
     */
    public function detachTag(Lead $lead, Tag $tag): void
    {
        $lead->tags()->detach($tag->id);
    }

    /**
     * Get tags for a lead.
     *
     * @return Collection<int, Tag>
     */
    public function getTagsForLead(Lead $lead): Collection
    {
        return $lead->tags()->get();
    }

    /**
     * Get popular tags.
     *
     * @return Collection<int, Tag>
     */
    public function getPopularTags(?CallCenter $callCenter = null, int $limit = 10): Collection
    {
        $query = Tag::query();

        if ($callCenter) {
            // Count only leads from this call center
            $query->withCount(['leads' => function ($q) use ($callCenter) {
                $q->where('call_center_id', $callCenter->id);
            }])
            ->whereHas('leads', function ($q) use ($callCenter) {
                $q->where('call_center_id', $callCenter->id);
            });
        } else {
            // Count all leads
            $query->withCount('leads');
        }

        return $query->orderBy('leads_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Update a tag.
     */
    public function updateTag(Tag $tag, array $data): Tag
    {
        // Prevent updating system tags (except description)
        if ($tag->is_system && isset($data['name'])) {
            throw new \Exception(__('Le nom des tags système ne peut pas être modifié.'));
        }

        $tag->update($data);

        return $tag->fresh();
    }

    /**
     * Get all tags with usage count.
     *
     * @return Collection<int, Tag>
     */
    public function getAllTagsWithCount(?CallCenter $callCenter = null): Collection
    {
        $query = Tag::withCount('leads')
            ->orderBy('name');

        if ($callCenter) {
            $query->whereHas('leads', function ($q) use ($callCenter) {
                $q->where('call_center_id', $callCenter->id);
            });
        }

        return $query->get();
    }

    /**
     * Delete a tag (only if not system).
     */
    public function deleteTag(Tag $tag): bool
    {
        if ($tag->is_system) {
            throw new \Exception(__('Les tags système ne peuvent pas être supprimés.'));
        }

        return $tag->delete();
    }
}
