<?php

namespace Database\Factories;

use App\Enums\PetGender;
use App\Enums\PetReproductionStatus;
use App\Enums\PetSize;
use App\Enums\PetSpecies;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'species' => fake()->randomElement(PetSpecies::cases()),
            'breed' => fake()->randomElement(['SRD', 'Labrador Retriever', 'Poodle', 'Siamés', 'Persa', 'Pastor Alemán']),
            'gender' => fake()->randomElement(PetGender::cases()),
            'birthdate' => fake()->dateTimeBetween('-12 years', '-1 month'),
            'size' => fake()->randomElement(PetSize::cases()),
            'weight' => fake()->randomFloat(1, 0.5, 45),
            'fur' => fake()->randomElement(['Corto', 'Largo', 'Rizado', 'Mediano']),
            'reproduction' => fake()->randomElement(PetReproductionStatus::cases()),
            'active' => true,
            'image' => 'pets/placeholder.jpg',
            'owner_id' => Owner::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function canino(): static
    {
        return $this->state(fn () => ['species' => PetSpecies::Canino]);
    }

    public function felino(): static
    {
        return $this->state(fn () => ['species' => PetSpecies::Felino]);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
