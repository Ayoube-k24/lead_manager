<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadNote>
 */
class LeadNoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\LeadNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => \App\Models\Lead::factory(),
            'user_id' => \App\Models\User::factory(),
            'content' => fake()->paragraph(),
            'is_private' => false,
            'type' => fake()->randomElement(['comment', 'call_log', 'internal_note']),
            'attachments' => null,
        ];
    }

    /**
     * Indicate that the note is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Indicate that the note is a call log.
     */
    public function callLog(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'call_log',
        ]);
    }

    /**
     * Indicate that the note is an internal note.
     */
    public function internalNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'internal_note',
        ]);
    }
}
