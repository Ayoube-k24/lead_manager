<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alert>
 */
class AlertFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Alert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_slug' => null, // Will be set automatically via afterCreating
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['lead_stale', 'agent_performance', 'conversion_rate', 'high_volume', 'low_volume', 'form_performance']),
            'conditions' => [],
            'threshold' => fake()->randomFloat(2, 10, 90),
            'is_active' => true,
            'notification_channels' => ['in_app'],
            'last_triggered_at' => null,
            'is_system' => false,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Alert $alert) {
            if (! $alert->role_slug && $alert->user) {
                $alert->update([
                    'role_slug' => $alert->user->role?->slug,
                ]);
            }
        });
    }
}
