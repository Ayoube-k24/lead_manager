<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SmtpProfile>
 */
class SmtpProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\SmtpProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' SMTP',
            'host' => fake()->domainName(),
            'port' => fake()->randomElement([587, 465, 25]),
            'encryption' => fake()->randomElement(['tls', 'ssl']),
            'username' => fake()->userName(),
            'password' => fake()->password(),
            'from_address' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
