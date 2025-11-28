<?php

namespace Database\Factories;

use App\Models\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadStatus>
 */
class LeadStatusFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = LeadStatus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug();

        return [
            'slug' => $slug,
            'name' => $this->faker->words(2, true),
            'color' => $this->faker->hexColor(),
            'description' => $this->faker->sentence(),
            'is_system' => false,
            'is_active' => $this->faker->boolean(30),
            'is_final' => $this->faker->boolean(20),
            'can_be_set_after_call' => $this->faker->boolean(50),
            'order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the status is a system status.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Indicate that the status is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the status is final.
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => true,
        ]);
    }

    /**
     * Indicate that the status can be set after a call.
     */
    public function postCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_be_set_after_call' => true,
        ]);
    }
}
