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

    /**
     * Indicate that the lead is pending email confirmation.
     */
    public function pendingEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_email',
            'email_confirmed_at' => null,
            'email_confirmation_token' => \Illuminate\Support\Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'assigned_to' => null,
            'called_at' => null,
        ]);
    }

    /**
     * Indicate that the lead has confirmed their email.
     */
    public function emailConfirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'email_confirmed',
            'email_confirmed_at' => now()->subHours(rand(1, 48)),
            'email_confirmation_token' => null,
            'email_confirmation_token_expires_at' => null,
        ]);
    }

    /**
     * Indicate that the lead is pending a call.
     */
    public function pendingCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_call',
            'email_confirmed_at' => now()->subHours(rand(1, 24)),
            'email_confirmation_token' => null,
            'email_confirmation_token_expires_at' => null,
            'called_at' => null,
        ]);
    }

    /**
     * Indicate that the lead has been confirmed.
     */
    public function confirmed(): static
    {
        $emailConfirmedAt = now()->subDays(rand(1, 60))->subHours(rand(1, 24));

        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'email_confirmed_at' => $emailConfirmedAt,
            'email_confirmation_token' => null,
            'email_confirmation_token_expires_at' => null,
            'called_at' => $emailConfirmedAt->copy()->addHours(rand(1, 48)),
            'call_comment' => fake()->optional(0.3)->randomElement([
                'Client très intéressé',
                'Rendez-vous pris',
                'Devis envoyé',
                'Suivi dans 1 semaine',
            ]),
        ]);
    }

    /**
     * Indicate that the lead has been rejected.
     */
    public function rejected(): static
    {
        $emailConfirmedAt = now()->subDays(rand(1, 60))->subHours(rand(1, 24));

        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'email_confirmed_at' => $emailConfirmedAt,
            'email_confirmation_token' => null,
            'email_confirmation_token_expires_at' => null,
            'called_at' => $emailConfirmedAt->copy()->addHours(rand(1, 48)),
            'call_comment' => fake()->randomElement([
                'Lead non intéressé',
                'Prix trop élevé',
                'Déjà client de la concurrence',
                'Pas de budget disponible',
                'Besoin non prioritaire',
                'Ne répond pas au téléphone',
                'Email invalide',
            ]),
        ]);
    }

    /**
     * Indicate that the lead is pending a callback.
     */
    public function callbackPending(): static
    {
        $emailConfirmedAt = now()->subDays(rand(1, 30))->subHours(rand(1, 24));

        return $this->state(fn (array $attributes) => [
            'status' => 'callback_pending',
            'email_confirmed_at' => $emailConfirmedAt,
            'email_confirmation_token' => null,
            'email_confirmation_token_expires_at' => null,
            'called_at' => $emailConfirmedAt->copy()->addHours(rand(1, 24)),
            'call_comment' => fake()->randomElement([
                'Rappel demandé pour demain',
                'Client occupé, rappel dans 2h',
                'Pas disponible, rappel dans 1 semaine',
            ]),
        ]);
    }
}
