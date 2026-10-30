<?php

namespace Database\Seeders;

use App\Models\Owner;
use App\Models\Pet;
use Database\Seeders\Concerns\UsesDemoData;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    use UsesDemoData;

    /**
     * Mascotas demo: [nombre, especie, raza, género, nacimiento, tamaño, peso, pelaje, reproducción, índice en OwnerSeeder::OWNERS].
     * Los seeders de historial clínico las referencian por su posición en esta lista.
     *
     * @var array<int, array<int, float|int|string>>
     */
    public const PETS = [
        ['Toby',     'Canino', 'Labrador Retriever',  'Male',   '2021-03-15', 'Big',    28.5, 'Corto',   'Normal',     0],
        ['Luna',     'Canino', 'Golden Retriever',    'Female', '2020-07-22', 'Big',    26.0, 'Largo',   'Sterilized', 0],
        ['Rex',      'Canino', 'Pastor Alemán',       'Male',   '2019-11-05', 'Big',    32.0, 'Mediano', 'Castrated',  1],
        ['Coco',     'Canino', 'Poodle',              'Female', '2022-01-10', 'Small',   4.5, 'Rizado',  'Normal',     1],
        ['Rocky',    'Canino', 'Rottweiler',          'Male',   '2020-05-18', 'Giant',  42.0, 'Corto',   'Normal',     2],
        ['Bella',    'Canino', 'Beagle',              'Female', '2021-09-30', 'Small',  10.0, 'Corto',   'Sterilized', 2],
        ['Duque',    'Canino', 'Pitbull',             'Male',   '2022-04-12', 'Medium', 22.0, 'Corto',   'Normal',     3],
        ['Max',      'Canino', 'SRD',                 'Male',   '2020-12-01', 'Medium', 18.5, 'Corto',   'Castrated',  3],
        ['Pancho',   'Canino', 'Bulldog Francés',     'Male',   '2023-02-14', 'Small',   9.0, 'Corto',   'Normal',     4],
        ['Thor',     'Canino', 'Doberman',            'Male',   '2019-08-20', 'Big',    35.0, 'Corto',   'Normal',     4],
        ['Simba',    'Felino', 'SRD',                 'Male',   '2022-06-05', 'Small',   4.0, 'Corto',   'Castrated',  5],
        ['Nala',     'Felino', 'Siamés',              'Female', '2021-10-18', 'Small',   3.5, 'Corto',   'Sterilized', 6],
        ['Milo',     'Felino', 'Persa',               'Male',   '2020-03-25', 'Small',   4.2, 'Largo',   'Castrated',  7],
        ['Princesa', 'Felino', 'SRD',                 'Female', '2023-01-08', 'Small',   3.8, 'Mediano', 'Normal',     8],
        ['Canela',   'Felino', 'Angora',              'Female', '2021-05-15', 'Small',   3.2, 'Largo',   'Sterilized', 9],
    ];

    public function run(): void
    {
        $owners = Owner::whereIn('ci', array_column(OwnerSeeder::OWNERS, 'ci'))->get()
            ->sortBy(fn (Owner $owner): int => (int) array_search($owner->ci, array_column(OwnerSeeder::OWNERS, 'ci')))
            ->values();
        $vets = $this->demoVets();

        foreach (self::PETS as $i => [$name, $species, $breed, $gender, $birthdate, $size, $weight, $fur, $reproduction, $ownerIdx]) {
            $owner = $owners[$ownerIdx % $owners->count()];

            Pet::updateOrCreate(['owner_id' => $owner->id, 'name' => $name], [
                'species' => $species,
                'breed' => $breed,
                'gender' => $gender,
                'birthdate' => $birthdate,
                'size' => $size,
                'weight' => $weight,
                'fur' => $fur,
                'reproduction' => $reproduction,
                'active' => true,
                'image' => '',
                'user_id' => $vets[$i % $vets->count()]->id,
            ]);
        }
    }
}
