<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use Illuminate\Database\Migrations\Migration;

/**
 * Areguá no tenía barrios cargados y, como el barrio es obligatorio en los propietarios, no se podía
 * registrar a nadie de esa ciudad. Vive en una migración porque `RegionsSeeder` no corre sobre una
 * base ya sembrada ni el deploy corre seeders; es idempotente y en una instalación nueva no hace nada
 * (las regiones llegan después, ya con estos barrios).
 */
return new class extends Migration
{
    private const NEIGHBORHOODS = [
        // Centro urbano
        'San Miguel', 'Santo Domingo', 'San Roque', 'Las Mercedes', 'Boquerón', 'San Cayetano',
        'Santa Catalina', 'Estanzuela', 'Kokue Guasu', 'Pindolo', 'San Antonio', 'María Auxiliadora',
        // Compañías y barrios suburbanos (la compañía San Miguel se distingue del barrio céntrico)
        'Costa Fleitas', 'Jukyty', 'Valle Pucú', 'Jukyry', 'Isla Valle', 'San Miguel (compañía)',
        'Caacupemí', 'Fracción Yvoty', 'Fracción Alicia', 'Villa Salvador', 'Villa Rosita', 'Tajy Poty',
    ];

    public function up(): void
    {
        $central = Department::where('name', 'Central')->first();
        $city = City::where('department_id', $central?->id)->where('name', 'Areguá')->first();

        if ($city === null) {
            return;
        }

        foreach (self::NEIGHBORHOODS as $name) {
            Neighborhood::firstOrCreate(['city_id' => $city->id, 'name' => $name]);
        }
    }

    public function down(): void
    {
        // Los barrios pueden estar en uso como domicilio; borrarlos borraría a esos propietarios.
    }
};
