<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $central = Department::whereRaw("LOWER(name) LIKE ?", ['%central%'])->first();

        // Fallback: cualquier barrio del departamento si la ciudad no tiene barrios propios
        $anyNeighborhood = Neighborhood::whereHas(
            'city', fn($q) => $q->where('department_id', $central?->id)
        )->first();

        $getCity = fn(string $search) => City::where('department_id', $central?->id)
            ->where('name', 'LIKE', '%' . $search . '%')
            ->first();

        $getNeighborhood = function (?int $cityId) use ($anyNeighborhood): ?int {
            $nb = Neighborhood::where('city_id', $cityId)->first();
            return ($nb ?? $anyNeighborhood)?->id;
        };

        $owners = [
            ['ci' => 4512376, 'first_name' => 'José',     'last_name' => 'Rodríguez', 'gender' => 'Male',   'email' => 'jose.rodriguez@gmail.com',    'phone' => '0981412345', 'city' => 'Aregu',        'address' => 'Calle Independencia Nacional 234'],
            ['ci' => 3218945, 'first_name' => 'Ana',      'last_name' => 'Martínez',  'gender' => 'Female', 'email' => 'ana.martinez@hotmail.com',    'phone' => '0991567890', 'city' => 'Luque',        'address' => 'Av. General Díaz 567'],
            ['ci' => 5634821, 'first_name' => 'Carlos',   'last_name' => 'López',     'gender' => 'Male',   'email' => 'carlos.lopez@gmail.com',      'phone' => '0971234567', 'city' => 'San Lorenzo',  'address' => 'Ruta 2 km 12, casa 45'],
            ['ci' => 2987654, 'first_name' => 'Patricia', 'last_name' => 'Benítez',   'gender' => 'Female', 'email' => 'patricia.benitez@yahoo.com',  'phone' => '0981876543', 'city' => 'Aregu',        'address' => 'Calle San Roque 89'],
            ['ci' => 6123456, 'first_name' => 'Miguel',   'last_name' => 'Fernández', 'gender' => 'Male',   'email' => 'miguel.fernandez@gmail.com',  'phone' => '0991345678', 'city' => 'Luque',        'address' => 'Av. Mcal. López 320'],
            ['ci' => 4521789, 'first_name' => 'Sandra',   'last_name' => 'Giménez',   'gender' => 'Female', 'email' => 'sandra.gimenez@hotmail.com',  'phone' => '0971654321', 'city' => 'Fernando',     'address' => 'Calle Cerro Corá 102'],
            ['ci' => 3654987, 'first_name' => 'Luis',     'last_name' => 'Ávalos',    'gender' => 'Male',   'email' => 'luis.avalos@gmail.com',       'phone' => '0981765432', 'city' => 'Itaugu',       'address' => 'Ruta 1 km 30, lote 8'],
            ['ci' => 5412369, 'first_name' => 'Rosa',     'last_name' => 'Cáceres',   'gender' => 'Female', 'email' => 'rosa.caceres@gmail.com',      'phone' => '0991234567', 'city' => 'Aregu',        'address' => 'Calle Yegros 55'],
            ['ci' => 4789123, 'first_name' => 'Pedro',    'last_name' => 'Villalba',  'gender' => 'Male',   'email' => 'pedro.villalba@hotmail.com',  'phone' => '0971567890', 'city' => 'Luque',        'address' => 'Av. San Blas 430'],
            ['ci' => 3321456, 'first_name' => 'Claudia',  'last_name' => 'Bogado',    'gender' => 'Female', 'email' => 'claudia.bogado@gmail.com',    'phone' => '0981321456', 'city' => 'San Lorenzo',  'address' => 'Calle Paraguarí 210'],
        ];

        foreach ($owners as $data) {
            $city = $getCity($data['city']);

            Owner::create([
                'ci'              => $data['ci'],
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'gender'          => $data['gender'],
                'email'           => $data['email'],
                'phone'           => $data['phone'],
                'department_id'   => $central?->id,
                'city_id'         => $city?->id,
                'neighborhood_id' => $getNeighborhood($city?->id),
                'address'         => $data['address'],
            ]);
        }
    }
}
