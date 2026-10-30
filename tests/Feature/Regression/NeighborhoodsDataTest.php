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

/**
 * Barrios y compañías de Areguá (la compañía homónima del barrio céntrico se distingue en el nombre).
 *
 * @var array<int, string>
 */
const AREGUA_NEIGHBORHOODS = [
    'San Miguel', 'Santo Domingo', 'San Roque', 'Las Mercedes', 'Boquerón', 'San Cayetano',
    'Santa Catalina', 'Estanzuela', 'Kokue Guasu', 'Pindolo', 'San Antonio', 'María Auxiliadora',
    'Costa Fleitas', 'Jukyty', 'Valle Pucú', 'Jukyry', 'Isla Valle', 'San Miguel (compañía)',
    'Caacupemí', 'Fracción Yvoty', 'Fracción Alicia', 'Villa Salvador', 'Villa Rosita', 'Tajy Poty',
];

function centralCity(string $name): City
{
    return City::where('department_id', Department::where('name', 'Central')->value('id'))
        ->where('name', $name)
        ->firstOrFail();
}

function runAreguaMigration(): void
{
    (require database_path('migrations/2026_10_30_150100_add_aregua_neighborhoods.php'))->up();
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

it('Areguá tiene sus barrios y compañías, sin nombres repetidos', function () {
    $names = Neighborhood::where('city_id', centralCity('Areguá')->id)->pluck('name');

    expect($names->sort()->values()->all())->toBe(collect(AREGUA_NEIGHBORHOODS)->sort()->values()->all())
        ->and($names->unique())->toHaveCount(24);
});

describe('migración add_aregua_neighborhoods', function () {
    it('carga los barrios en una base ya sembrada sin duplicar al repetirla', function () {
        $aregua = centralCity('Areguá');
        Neighborhood::where('city_id', $aregua->id)->delete();

        runAreguaMigration();
        $count = Neighborhood::count();

        runAreguaMigration();

        expect(Neighborhood::where('city_id', $aregua->id)->pluck('name')->sort()->values()->all())
            ->toBe(collect(AREGUA_NEIGHBORHOODS)->sort()->values()->all())
            ->and(Neighborhood::count())->toBe($count);
    });

    it('no pisa los barrios que ya existen ni los de otras ciudades', function () {
        $before = Neighborhood::where('city_id', centralCity('Luque')->id)->count();
        $existing = Neighborhood::where('city_id', centralCity('Areguá')->id)->where('name', 'Pindolo')->firstOrFail();

        runAreguaMigration();

        expect($existing->fresh()->id)->toBe($existing->id)
            ->and(Neighborhood::where('city_id', centralCity('Luque')->id)->count())->toBe($before);
    });
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
    runAreguaMigration();

    expect(Neighborhood::count())->toBe(0);
});
