<?php

namespace Database\Factories;

use App\Filament\Resources\SurgeryResource;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Surgery>
 */
class SurgeryFactory extends Factory
{
    protected $model = Surgery::class;

    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'type' => fake()->randomElement(array_keys(SurgeryResource::typeOptions())),
            'observation' => fake()->optional()->sentence(),
            'pet_id' => Pet::factory(),
            'user_id' => User::factory(),
        ];
    }
}
