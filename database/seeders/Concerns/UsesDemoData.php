<?php

namespace Database\Seeders\Concerns;

use App\Filament\Concerns\ClinicRoles;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PetSeeder;
use Illuminate\Support\Collection;

/**
 * Acceso determinista a los datos demo ya sembrados: los seeders de historial clínico
 * referencian las mascotas por posición en PetSeeder::PETS y reparten los registros
 * entre los veterinarios en orden, así la siembra es reproducible y no toca datos reales.
 */
trait UsesDemoData
{
    /**
     * Veterinarios por id (el admin y el asistente no atienden pacientes).
     *
     * @return Collection<int, User>
     */
    protected function demoVets(): Collection
    {
        return User::role(ClinicRoles::VETERINARIAN)->orderBy('id')->get();
    }

    /**
     * Mascotas demo en el mismo orden que PetSeeder::PETS.
     *
     * @return Collection<int, Pet>
     */
    protected function demoPets(): Collection
    {
        $names = array_column(PetSeeder::PETS, 0);

        return Pet::query()
            ->whereIn('name', $names)
            ->whereHas('owner', fn ($owners) => $owners->whereIn('ci', array_column(OwnerSeeder::OWNERS, 'ci')))
            ->get()
            ->sortBy(fn (Pet $pet): int => (int) array_search($pet->name, $names, true))
            ->values();
    }
}
