<?php

namespace Database\Factories;

use App\Filament\Resources\VaccinationResource;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaccination>
 */
class VaccinationFactory extends Factory
{
    protected $model = Vaccination::class;

    public function definition(): array
    {
        return [
            'vaccine' => fake()->randomElement(array_keys(VaccinationResource::vaccineOptions())),
            'application_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'next_application' => fake()->dateTimeBetween('now', '+1 year'),
            'batch' => strtoupper(fake()->bothify('LOT-####??')),
            'manufacturer' => fake()->randomElement(['Zoetis', 'MSD Animal Health', 'Virbac', 'Boehringer Ingelheim']),
            'observation' => fake()->optional()->sentence(),
            'pet_id' => Pet::factory(),
            'user_id' => User::factory(),
            'reminder_sent_at' => null,
        ];
    }

    public function proximaAVencer(int $dias = 2): static
    {
        return $this->state(fn () => [
            'next_application' => now()->addDays($dias),
            'reminder_sent_at' => null,
        ]);
    }

    public function recordatorioYaEnviado(): static
    {
        return $this->state(fn () => ['reminder_sent_at' => now()]);
    }
}
