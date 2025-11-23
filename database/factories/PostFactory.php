<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => fake()->realText(),
            'is_draft' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_draft' => true,
                'published_at' => null,
            ];
        });
    }

    public function scheduled(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_draft' => false,
                'published_at' => now()->addDays(1),
            ];
        });
    }
}
