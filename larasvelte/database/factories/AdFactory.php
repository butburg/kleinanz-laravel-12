<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ad>
 */
class AdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->uuid(),
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraphs(2, true),
            'price' => fake()->numberBetween(0, 500),
            'condition' => fake()->randomElement(config('ads.validation.conditions')),
            'shipping' => fake()->randomElement(config('ads.validation.shipping_options')),
            'status' => config('ads.status.default'),
            'prompt_text' => null,
            'metadata' => null,
        ];
    }
}
