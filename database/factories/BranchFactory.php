<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            // La 001 es la sucursal principal: las de prueba arrancan en 002 y no se repiten.
            'code' => str_pad((string) fake()->unique()->numberBetween(2, 999), 3, '0', STR_PAD_LEFT),
            'name' => 'Sucursal '.fake()->unique()->city(),
            'address' => 'Calle Independencia Nacional 123',
            'phone' => '0981123456',
            'email' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
