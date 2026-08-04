<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    public function definition(): array
    {
        return [
            'anamnesis' => fake()->sentence(12),
            'diagnosis' => fake()->sentence(8),
            'treatment' => fake()->sentence(10),
            'observation' => fake()->optional()->sentence(),
            'consultation_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'pet_id' => Pet::factory(),
            'user_id' => User::factory(),
        ];
    }
}
