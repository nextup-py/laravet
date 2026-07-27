<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;

class RegionsSeeder extends Seeder
{
    /**
     * Siembra los datos geográficos de Paraguay (departamentos, ciudades, barrios).
     * Reemplaza al comando paraguay-regions:seed del paquete zrkb/paraguay-regions.
     */
    public function run(): void
    {
        if (Department::exists()) {
            $this->command->info('Regions ya sembradas, se omite.');
            return;
        }

        $this->command->info('Sembrando departments...');
        Department::insert(
            json_decode(file_get_contents(database_path('data/json/departments.json')), true)
        );

        $this->command->info('Sembrando cities...');
        City::insert(
            json_decode(file_get_contents(database_path('data/json/cities.json')), true)
        );

        $this->command->info('Sembrando neighborhoods...');
        Neighborhood::insert(
            json_decode(file_get_contents(database_path('data/json/neighborhoods.json')), true)
        );
    }
}
