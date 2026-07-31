<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Owner;
use Database\Seeders\RegionsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Owner>
 */
class OwnerFactory extends Factory
{
    protected $model = Owner::class;

    public function definition(): array
    {
        $city = City::query()->whereHas('neighborhoods')->inRandomOrder()->first();

        if (! $city) {
            // Red de seguridad: si algún test no pasó por el beforeEach global de Pest.php.
            app(RegionsSeeder::class)->run();
            $city = City::query()->whereHas('neighborhoods')->inRandomOrder()->first();
        }

        $neighborhood = Neighborhood::query()->where('city_id', $city->id)->inRandomOrder()->first();

        return [
            'ci' => fake()->unique()->numberBetween(1000000, 7999999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('########'),
            'department_id' => $city->department_id,
            'city_id' => $city->id,
            'neighborhood_id' => $neighborhood?->id,
            'address' => fake()->streetAddress(),
        ];
    }
}
