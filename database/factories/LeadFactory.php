<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => \App\Models\Form::factory(),
            'data' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
            ],
            'email' => fake()->safeEmail(),
            'status' => fake()->randomElement(['pending_email', 'email_confirmed', 'pending_call', 'confirmed', 'rejected', 'callback_pending']),
            'email_confirmed_at' => fake()->optional()->dateTime(),
            'email_confirmation_token' => fake()->optional()->sha1(),
            'email_confirmation_token_expires_at' => fake()->optional()->dateTimeBetween('now', '+24 hours'),
            'assigned_to' => \App\Models\User::factory(),
            'call_center_id' => \App\Models\CallCenter::factory(),
            'call_comment' => fake()->optional()->paragraph(),
            'called_at' => fake()->optional()->dateTime(),
        ];
    }
}
