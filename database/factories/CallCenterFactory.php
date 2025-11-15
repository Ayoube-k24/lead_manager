<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallCenter>
 */
class CallCenterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\CallCenter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Call Center',
            'description' => fake()->paragraph(),
            'owner_id' => \App\Models\User::factory(),
            'distribution_method' => fake()->randomElement(['round_robin', 'weighted', 'manual']),
            'is_active' => true,
        ];
    }
}
