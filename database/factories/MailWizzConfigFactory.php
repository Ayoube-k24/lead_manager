<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MailWizzConfig>
 */
class MailWizzConfigFactory extends Factory
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
    protected $model = \App\Models\MailWizzConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_url' => 'https://'.fake()->domainName(),
            'public_key' => fake()->sha256(),
            'private_key' => fake()->sha256(),
            'list_uid' => fake()->uuid(),
            'call_center_id' => \App\Models\CallCenter::factory(),
            'import_frequency' => 15,
            'is_active' => true,
            'last_import_at' => null,
            'last_import_count' => 0,
        ];
    }

    /**
     * Indicate that the config is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
