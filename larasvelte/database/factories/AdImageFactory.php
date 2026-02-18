<?php

namespace Database\Factories;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdImage>
 */
class AdImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'large_path' => 'ads/large/'.$this->faker->uuid().'.jpg',
            'large_thumb_path' => 'ads/large_thumb/'.$this->faker->uuid().'.jpg',
            'cropped_path' => null,
            'cropped_thumb_path' => null,
            'original_name' => $this->faker->word().'.jpg',
            'position' => 0,
            'is_title' => false,
        ];
    }
}
