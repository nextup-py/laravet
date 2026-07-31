<?php

namespace Database\Factories;

use App\Filament\Resources\TestResource;
use App\Models\Pet;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    protected $model = Test::class;

    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'type' => fake()->randomElement(array_keys(TestResource::typeOptions())),
            'result' => ['resumen' => fake()->sentence(), 'valores_normales' => true],
            'observation' => fake()->optional()->sentence(),
            'pet_id' => Pet::factory(),
            'user_id' => User::factory(),
        ];
    }
}
