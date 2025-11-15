<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Form>
 */
class FormFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\Form::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Form',
            'description' => fake()->paragraph(),
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'validation' => ['required', 'string', 'max:255'],
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                    'validation' => ['required', 'email'],
                ],
            ],
            'smtp_profile_id' => \App\Models\SmtpProfile::factory(),
            'email_template_id' => \App\Models\EmailTemplate::factory(),
            'is_active' => true,
        ];
    }
}
