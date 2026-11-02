<?php

namespace Database\Factories;

use App\Enums\PetWeightSource;
use App\Models\Pet;
use App\Models\PetWeight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetWeight>
 */
class PetWeightFactory extends Factory
{
    protected $model = PetWeight::class;

    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'weight' => fake()->randomFloat(1, 0.5, 45),
            'weighed_at' => today(),
            'user_id' => User::factory(),
            'consultation_id' => null,
            'source' => PetWeightSource::Weighing,
        ];
    }
}
