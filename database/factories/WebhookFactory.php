<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\Webhook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Webhook',
            'url' => fake()->url(),
            'secret' => \App\Models\Webhook::generateSecret(),
            'events' => ['lead.created', 'lead.email_confirmed'],
            'is_active' => true,
            'form_id' => null,
            'call_center_id' => null,
            'user_id' => \App\Models\User::factory(),
        ];
    }

    /**
     * Indicate that the webhook is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Associate webhook with a form.
     */
    public function forForm(\App\Models\Form $form): static
    {
        return $this->state(fn (array $attributes) => [
            'form_id' => $form->id,
        ]);
    }

    /**
     * Associate webhook with a call center.
     */
    public function forCallCenter(\App\Models\CallCenter $callCenter): static
    {
        return $this->state(fn (array $attributes) => [
            'call_center_id' => $callCenter->id,
        ]);
    }
}
