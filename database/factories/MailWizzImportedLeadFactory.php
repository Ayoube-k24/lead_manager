<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MailWizzImportedLead>
 */
class MailWizzImportedLeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\MailWizzImportedLead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lead = \App\Models\Lead::factory()->create(['source' => 'mailwizz_seo']);

        return [
            'mailwizz_subscriber_id' => fake()->uuid(),
            'lead_id' => $lead->id,
            'email' => $lead->email,
            'imported_at' => now(),
            'mailwizz_data' => [
                'subscriber_uid' => fake()->uuid(),
                'email' => $lead->email,
                'FNAME' => fake()->firstName(),
                'LNAME' => fake()->lastName(),
            ],
        ];
    }
}
