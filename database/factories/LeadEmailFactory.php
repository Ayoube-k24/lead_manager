<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadEmail>
 */
class LeadEmailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\LeadEmail::class;

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
            'email_subject_id' => null,
            'subject' => fake()->sentence(),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'body_text' => fake()->paragraph(),
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
            'sent_at' => now(),
        ];
    }
}
