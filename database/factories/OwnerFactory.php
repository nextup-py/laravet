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

    /** @var array<int, string> */
    private const MALE_NAMES = ['José', 'Carlos', 'Miguel', 'Luis', 'Pedro', 'Juan', 'Rubén', 'Derlis', 'Osvaldo', 'Fidel', 'Édgar', 'Ramón', 'Aníbal', 'Diego', 'Marcelo'];

    /** @var array<int, string> */
    private const FEMALE_NAMES = ['Ana', 'Patricia', 'Sandra', 'Rosa', 'Claudia', 'Liz', 'Fátima', 'Norma', 'Gladys', 'Mirta', 'Lourdes', 'Karina', 'Cynthia', 'Marlene', 'Silvana'];

    /** @var array<int, string> */
    private const SURNAMES = ['Rodríguez', 'Martínez', 'López', 'Benítez', 'Fernández', 'Giménez', 'Ávalos', 'Cáceres', 'Villalba', 'Bogado', 'Ayala', 'Duarte', 'Báez', 'Ortiz', 'Acosta', 'Franco', 'Insfrán', 'Ojeda', 'Cardozo', 'Samaniego', 'Riveros', 'Vera', 'Núñez', 'Rolón', 'Aquino', 'Vázquez', 'Peralta', 'Servín', 'Fleitas', 'Yegros'];

    /** @var array<int, string> */
    private const STREETS = ['Calle Independencia Nacional', 'Av. Mcal. López', 'Calle Yegros', 'Av. San Blas', 'Calle Cerro Corá', 'Av. Defensores del Chaco', 'Calle Pdte. Franco', 'Calle Palma', 'Calle 14 de Mayo', 'Av. General Aquino', 'Calle Mcal. Estigarribia'];

    public function definition(): array
    {
        $city = City::query()->whereHas('neighborhoods')->inRandomOrder()->first();

        if (! $city) {
            // Red de seguridad: si algún test no pasó por el beforeEach global de Pest.php.
            app(RegionsSeeder::class)->run();
            $city = City::query()->whereHas('neighborhoods')->inRandomOrder()->first();
        }

        $neighborhood = Neighborhood::query()->where('city_id', $city->id)->inRandomOrder()->first();

        $gender = fake()->randomElement(['Male', 'Female']);

        return [
            'ci' => fake()->unique()->numberBetween(1000000, 7999999),
            'first_name' => fake()->randomElement($gender === 'Male' ? self::MALE_NAMES : self::FEMALE_NAMES),
            'last_name' => fake()->randomElement(self::SURNAMES),
            'gender' => $gender,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('########'),
            'department_id' => $city->department_id,
            'city_id' => $city->id,
            'neighborhood_id' => $neighborhood?->id,
            'address' => fake()->randomElement(self::STREETS).' '.fake()->numberBetween(10, 999),
        ];
    }
}
