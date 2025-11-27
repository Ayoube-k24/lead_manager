<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadReminder>
 */
class LeadReminderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\LeadReminder::class;

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
            'reminder_date' => fake()->dateTimeBetween('now', '+30 days'),
            'reminder_type' => fake()->randomElement(['call_back', 'follow_up', 'appointment']),
            'notes' => fake()->optional()->paragraph(),
            'is_completed' => false,
            'completed_at' => null,
            'notified_at' => null,
        ];
    }

    /**
     * Indicate that the reminder is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => fake()->dateTimeBetween($attributes['reminder_date'], 'now'),
        ]);
    }

    /**
     * Indicate that the reminder is a call back.
     */
    public function callBack(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => 'call_back',
        ]);
    }

    /**
     * Indicate that the reminder is a follow up.
     */
    public function followUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => 'follow_up',
        ]);
    }

    /**
     * Indicate that the reminder is an appointment.
     */
    public function appointment(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_type' => 'appointment',
        ]);
    }
}
