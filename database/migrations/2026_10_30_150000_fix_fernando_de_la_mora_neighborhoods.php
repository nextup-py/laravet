<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use Illuminate\Database\Migrations\Migration;

/**
 * Los datos de regiones traían para Fernando de la Mora los mismos 28 barrios de Luque (copia por
 * error). Se quitan esos duplicados, salvo los que alguien ya use como domicilio (borrarlos borraría
 * a esos propietarios y su historial), y se cargan los 15 barrios reales. Vive en una migración
 * porque `RegionsSeeder` no corre sobre una base ya sembrada ni el deploy corre seeders; es
 * idempotente y en una instalación nueva no hace nada (las regiones llegan después, ya corregidas).
 */
return new class extends Migration
{
    private const NEIGHBORHOODS = [
        // Zona Norte
        'Bernardino Caballero', "Ita Ka'aguy", 'Kokue Guazu', 'Laguna Grande', 'Laguna Satí',
        'Orilla del Campo Grande', 'Pitiantuta', 'Villa Ofelia',
        // Zona Sur
        'Domingo Savio', 'Estanzuela', 'IPVU', 'Las Residentas', 'San Juan', 'Tres Bocas',
        // Zona Central
        'Barrio Centro',
    ];

    public function up(): void
    {
        $central = Department::where('name', 'Central')->first();
        $city = City::where('department_id', $central?->id)->where('name', 'Fernando de la Mora')->first();
        $luque = City::where('department_id', $central?->id)->where('name', 'Luque')->first();

        if ($city === null) {
            return;
        }

        if ($luque !== null) {
            $copied = Neighborhood::where('city_id', $luque->id)->pluck('name');

            Neighborhood::where('city_id', $city->id)
                ->whereIn('name', $copied)
                ->get()
                ->each(function (Neighborhood $neighborhood): void {
                    if ($neighborhood->deletionBlockedReason() === null) {
                        $neighborhood->delete();
                    }
                });
        }

        foreach (self::NEIGHBORHOODS as $name) {
            Neighborhood::firstOrCreate(['city_id' => $city->id, 'name' => $name]);
        }
    }

    public function down(): void
    {
        // No se restauran los barrios duplicados de Luque: eran un error de datos.
    }
};
