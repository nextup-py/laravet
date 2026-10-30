<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;

/**
 * Barrios reales de Fernando de la Mora (los datos traían una copia de los de Luque).
 *
 * @var array<int, string>
 */
const FERNANDO_DE_LA_MORA_NEIGHBORHOODS = [
    'Bernardino Caballero', "Ita Ka'aguy", 'Kokue Guazu', 'Laguna Grande', 'Laguna Satí',
    'Orilla del Campo Grande', 'Pitiantuta', 'Villa Ofelia',
    'Domingo Savio', 'Estanzuela', 'IPVU', 'Las Residentas', 'San Juan', 'Tres Bocas',
    'Barrio Centro',
];

function centralCity(string $name): City
{
    return City::where('department_id', Department::where('name', 'Central')->value('id'))
        ->where('name', $name)
        ->firstOrFail();
}

function runFernandoDeLaMoraMigration(): void
{
    (require database_path('migrations/2026_10_30_150000_fix_fernando_de_la_mora_neighborhoods.php'))->up();
}

it('Fernando de la Mora tiene sus barrios reales y ninguno copiado de Luque', function () {
    $fdlm = Neighborhood::where('city_id', centralCity('Fernando de la Mora')->id)->pluck('name');
    $luque = Neighborhood::where('city_id', centralCity('Luque')->id)->pluck('name');

    expect($fdlm->sort()->values()->all())->toBe(collect(FERNANDO_DE_LA_MORA_NEIGHBORHOODS)->sort()->values()->all())
        ->and($fdlm->intersect($luque))->toBeEmpty();
});

describe('migración fix_fernando_de_la_mora_neighborhoods', function () {
    beforeEach(function () {
        // Estado de producción antes de la corrección: Fernando de la Mora con una copia de los barrios de Luque.
        $this->fdlm = centralCity('Fernando de la Mora');
        $this->luque = centralCity('Luque');
        Neighborhood::where('city_id', $this->fdlm->id)->delete();

        foreach (Neighborhood::where('city_id', $this->luque->id)->pluck('name') as $name) {
            Neighborhood::create(['city_id' => $this->fdlm->id, 'name' => $name]);
        }
    });

    it('quita los duplicados y carga los barrios reales', function () {
        runFernandoDeLaMoraMigration();

        $names = Neighborhood::where('city_id', $this->fdlm->id)->pluck('name');

        expect($names->sort()->values()->all())->toBe(collect(FERNANDO_DE_LA_MORA_NEIGHBORHOODS)->sort()->values()->all())
            ->and(Neighborhood::where('city_id', $this->luque->id)->count())->toBe(28);
    });

    it('conserva el barrio duplicado que ya usa un propietario', function () {
        $used = Neighborhood::where('city_id', $this->fdlm->id)->where('name', 'Campo Grande')->firstOrFail();
        $owner = Owner::factory()->create([
            'department_id' => $this->fdlm->department_id,
            'city_id' => $this->fdlm->id,
            'neighborhood_id' => $used->id,
        ]);

        runFernandoDeLaMoraMigration();

        expect($used->fresh())->not->toBeNull()
            ->and($owner->fresh())->not->toBeNull()
            ->and(Neighborhood::where('city_id', $this->fdlm->id)->where('name', 'Laurelty')->exists())->toBeFalse()
            ->and(Neighborhood::where('city_id', $this->fdlm->id)->where('name', 'Barrio Centro')->exists())->toBeTrue();
    });

    it('es idempotente', function () {
        runFernandoDeLaMoraMigration();
        $count = Neighborhood::count();

        runFernandoDeLaMoraMigration();

        expect(Neighborhood::count())->toBe($count);
    });
});

it('la migración no hace nada si todavía no hay regiones (instalación nueva)', function () {
    Owner::query()->delete();
    Neighborhood::query()->delete();
    City::query()->delete();
    Department::query()->delete();

    runFernandoDeLaMoraMigration();

    expect(Neighborhood::count())->toBe(0);
});
