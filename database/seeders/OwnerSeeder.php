<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    /**
     * Propietarios demo. Los correos son del dominio reservado `.test` y los teléfonos terminan en
     * `0001NN` (prefijo de operadora + número ficticio): así ningún aviso del demo (correo o
     * «Avisar por WhatsApp») puede llegar a una persona real. `city` y `neighborhood` son nombres exactos de una ciudad de Central y de un barrio
     * suyo (el barrio es obligatorio en la base y solo Luque y San Lorenzo tienen barrios cargados).
     *
     * @var array<int, array<string, int|string>>
     */
    public const OWNERS = [
        ['ci' => 4512376, 'first_name' => 'José',     'last_name' => 'Rodríguez', 'gender' => 'Male',   'email' => 'jose.rodriguez@mbopivet-demo.test',    'phone' => '0981000101', 'city' => 'Luque', 'neighborhood' => 'Primer Barrio', 'address' => 'Calle Independencia Nacional 234'],
        ['ci' => 3218945, 'first_name' => 'Ana',      'last_name' => 'Martínez',  'gender' => 'Female', 'email' => 'ana.martinez@mbopivet-demo.test',    'phone' => '0991000102', 'city' => 'Luque', 'neighborhood' => 'Campo Grande', 'address' => 'Av. General Aquino 567'],
        ['ci' => 5634821, 'first_name' => 'Carlos',   'last_name' => 'López',     'gender' => 'Male',   'email' => 'carlos.lopez@mbopivet-demo.test',    'phone' => '0971000103', 'city' => 'San Lorenzo', 'neighborhood' => 'San Miguel', 'address' => 'Ruta 2 km 12, casa 45'],
        ['ci' => 2987654, 'first_name' => 'Patricia', 'last_name' => 'Benítez',   'gender' => 'Female', 'email' => 'patricia.benitez@mbopivet-demo.test',    'phone' => '0981000104', 'city' => 'Luque', 'neighborhood' => 'Yukyry', 'address' => 'Calle San Roque 89'],
        ['ci' => 6123456, 'first_name' => 'Miguel',   'last_name' => 'Fernández', 'gender' => 'Male',   'email' => 'miguel.fernandez@mbopivet-demo.test',    'phone' => '0991000105', 'city' => 'Luque', 'neighborhood' => 'Tercer Barrio', 'address' => 'Av. Mcal. López 320'],
        ['ci' => 4521789, 'first_name' => 'Sandra',   'last_name' => 'Giménez',   'gender' => 'Female', 'email' => 'sandra.gimenez@mbopivet-demo.test',    'phone' => '0971000106', 'city' => 'San Lorenzo', 'neighborhood' => 'Villa Universitaria', 'address' => 'Calle Cerro Corá 102'],
        ['ci' => 3654987, 'first_name' => 'Luis',     'last_name' => 'Ávalos',    'gender' => 'Male',   'email' => 'luis.avalos@mbopivet-demo.test',    'phone' => '0981000107', 'city' => 'San Lorenzo', 'neighborhood' => 'San Roque', 'address' => 'Ruta 2 km 14, lote 8'],
        ['ci' => 5412369, 'first_name' => 'Rosa',     'last_name' => 'Cáceres',   'gender' => 'Female', 'email' => 'rosa.caceres@mbopivet-demo.test',    'phone' => '0991000108', 'city' => 'Luque', 'neighborhood' => 'Ykuá Duré', 'address' => 'Calle Yegros 55'],
        ['ci' => 4789123, 'first_name' => 'Pedro',    'last_name' => 'Villalba',  'gender' => 'Male',   'email' => 'pedro.villalba@mbopivet-demo.test',    'phone' => '0971000109', 'city' => 'San Lorenzo', 'neighborhood' => 'San Blas', 'address' => 'Av. San Blas 430'],
        ['ci' => 3321456, 'first_name' => 'Claudia',  'last_name' => 'Bogado',    'gender' => 'Female', 'email' => 'claudia.bogado@mbopivet-demo.test',    'phone' => '0981000110', 'city' => 'San Lorenzo', 'neighborhood' => 'Santa Ana', 'address' => 'Calle Paraguarí 210'],
    ];

    public function run(): void
    {
        $central = Department::where('name', 'Central')->first();

        foreach (self::OWNERS as $data) {
            $city = City::where('department_id', $central?->id)->where('name', $data['city'])->first();
            $neighborhood = Neighborhood::where('city_id', $city?->id)->where('name', $data['neighborhood'])->first()
                ?? Neighborhood::where('city_id', $city?->id)->orderBy('id')->first();

            Owner::updateOrCreate(['ci' => $data['ci']], [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'department_id' => $central?->id,
                'city_id' => $city?->id,
                'neighborhood_id' => $neighborhood?->id,
                'address' => $data['address'],
            ]);
        }
    }
}
