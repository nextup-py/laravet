<?php

/**
 * Datos demo para la clínica Mbopivet (Areguá, Paraguay): febrero a octubre de 2026.
 *
 * No es un Seeder: se carga a mano desde Tinker y devuelve un closure.
 *
 *   php artisan tinker
 *   >>> $demo = require base_path('database/demo-2026.php');
 *   >>> $demo();                          // dry-run (default): simula, cuenta y revierte
 *   >>> $demo(dryRun: false);             // escribe de verdad
 *   >>> $demo(cleanup: true, dryRun: false);  // borra SOLO lo que sembró este script
 *   >>> $demo(dryRun: false, vetEmails: ['a@x.com']);  // atribuye los registros solo a esos usuarios
 *
 * Seguridad:
 *  - Todo corre en una transacción; el dry-run siempre hace rollback.
 *  - Los propietarios demo se identifican por el dominio @example.com. Sus mascotas y
 *    registros clínicos cuelgan de ellos (cascadeOnDelete), así que el cleanup los
 *    elimina sin tocar nada más.
 *  - Aborta si ya hay propietarios demo (correr cleanup primero) o si hay datos reales
 *    en owners/pets y no se pasó allowExisting: true.
 *  - Es determinístico (mt_srand fijo): mismos datos en cada corrida.
 *  - Todas las vacunas quedan con reminder_sent_at, así que send:vaccination-notifications
 *    nunca intenta enviar a estos propietarios.
 */

use App\Models\City;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

return function (bool $dryRun = true, bool $allowExisting = false, bool $cleanup = false, array $vetEmails = []): array {
    $demoDomain = '@example.com';
    $pdfDir = 'demo-2026';
    $pdfDisk = config('filament.default_filesystem_disk', 'public');

    // ---------------------------------------------------------------- cleanup
    if ($cleanup) {
        DB::beginTransaction();
        try {
            $owners = Owner::where('email', 'like', '%'.$demoDomain)->get();
            $summary = [
                'modo' => $dryRun ? 'DRY-RUN cleanup (no se borra nada)' : 'CLEANUP',
                'propietarios' => $owners->count(),
                'mascotas' => Pet::whereIn('owner_id', $owners->modelKeys())->count(),
            ];
            Owner::whereKey($owners->modelKeys())->delete();
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        if (! $dryRun) {
            Storage::disk($pdfDisk)->deleteDirectory($pdfDir);
        }

        return $summary;
    }

    // ------------------------------------------------------------ precondiciones
    if (Owner::where('email', 'like', '%'.$demoDomain)->exists()) {
        return ['abortado' => 'Ya hay propietarios demo (@example.com). Corré primero $demo(cleanup: true, dryRun: false).'];
    }

    $realOwners = Owner::count();
    $realPets = Pet::count();
    if (($realOwners > 0 || $realPets > 0) && ! $allowExisting) {
        return ['abortado' => "La DB ya tiene datos ({$realOwners} propietarios, {$realPets} mascotas). Si querés agregar los demo igual, pasá allowExisting: true."];
    }

    if (! Role::where('name', 'veterinarian')->exists()) {
        return ['abortado' => 'Falta el rol "veterinarian". Corré RolesAndPermissionsSeeder primero (ProductionSeeder).'];
    }

    $central = Department::whereRaw('LOWER(name) LIKE ?', ['%central%'])->first();
    $areguaCity = City::where('department_id', $central?->id)->where('name', 'like', '%Aregu%')->first();
    if (! $central || ! $areguaCity) {
        return ['abortado' => 'Faltan Departamento Central / Areguá. Corré RegionsSeeder primero.'];
    }
    $fallbackNeighborhood = Neighborhood::whereHas('city', fn ($q) => $q->where('department_id', $central->id))->orderBy('id')->first();
    if (! $fallbackNeighborhood) {
        return ['abortado' => 'No hay barrios cargados para el departamento Central.'];
    }

    mt_srand(99);

    // ------------------------------------------------------------------ helpers
    $start = CarbonImmutable::create(2026, 2, 2);
    $today = CarbonImmutable::create(2026, 10, 21);
    $aiSince = CarbonImmutable::create(2026, 8, 3);

    $pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];
    $chance = fn (int $pct) => mt_rand(1, 100) <= $pct;
    $wpick = function (array $items, callable $weight) {
        $total = array_sum(array_map($weight, $items));
        $r = mt_rand(1, max(1, $total));
        foreach ($items as $k => $item) {
            $r -= $weight($item);
            if ($r <= 0) {
                return $k;
            }
        }

        return array_key_last($items);
    };
    $days = fn (CarbonImmutable $a, CarbonImmutable $b) => intdiv($b->getTimestamp() - $a->getTimestamp(), 86400);
    $workday = function (CarbonImmutable $d) {
        return $d->dayOfWeek === 0 ? $d->subDay() : $d;
    };
    $randDate = function (CarbonImmutable $from, CarbonImmutable $to) use ($days, $workday) {
        return $workday($from->addDays(mt_rand(0, max(0, $days($from, $to)))));
    };
    $atTime = fn (CarbonImmutable $d) => $d->setTime(mt_rand(8, 16), mt_rand(0, 59), mt_rand(0, 59));
    $fmt = fn (CarbonImmutable $d) => $d->format('d/m/Y');

    // --------------------------------------------------------------- veterinarios
    DB::beginTransaction(); // incluye la creación de veterinarios: el dry-run también la revierte
    $createdVets = [];
    $vets = $vetEmails !== [] ? User::whereIn('email', $vetEmails)->get() : User::role('veterinarian')->get();
    if ($vetEmails !== [] && $vets->count() !== count($vetEmails)) {
        DB::rollBack();

        return ['abortado' => 'Algún email de vetEmails no existe en users.'];
    }
    if ($vets->isEmpty()) {
        foreach ([
            ['Dra. María González', 'maria.gonzalez@mbopivet.com.py'],
            ['Dr. Carlos Giménez', 'carlos.gimenez@mbopivet.com.py'],
        ] as [$name, $email]) {
            $vet = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make(Str::password(32)), // sin acceso: solo sirven de autores de los registros
                'email_verified_at' => now(),
                'department_id' => $central->id,
                'city_id' => $areguaCity->id,
                'neighborhood_id' => $fallbackNeighborhood->id,
                'address' => 'Areguá',
            ]);
            $vet->syncRoles(['veterinarian']);
            $createdVets[] = $email;
        }
        $vets = User::role('veterinarian')->get();
    }
    $vetList = $vets->values()->all();
    $aiVets = array_values(array_filter($vetList, fn (User $u) => $u->hasPermissionTo('use_ai_diagnostics')));

    // ------------------------------------------------------------- propietarios
    // [nombre, apellido, género, ciudad, dirección]
    $ownerRows = [
        ['Gustavo', 'Ortigoza', 'Male', 'Aregu', 'Calle Mcal. Estigarribia 345'],
        ['Liz', 'Benítez', 'Female', 'Aregu', 'Barrio Valle Pucú, calle 3'],
        ['Rodrigo', 'Ayala', 'Male', 'Aregu', 'Compañía Estanzuela, casa 12'],
        ['Fabiola', 'Cardozo', 'Female', 'Aregu', 'Av. Mcal. López 820'],
        ['Hugo', 'Báez', 'Male', 'Aregu', 'Calle Tte. Fariña 150'],
        ['Noemí', 'Gauto', 'Female', 'Aregu', 'Calle Cerro Ñu 41'],
        ['Derlis', 'Mendoza', 'Male', 'Aregu', 'Calle San Roque 89'],
        ['Cynthia', 'Franco', 'Female', 'Aregu', 'Calle Yegros 55'],
        ['Armando', 'Ríos', 'Male', 'Aregu', 'Compañía Estanzuela, lote 7'],
        ['Marta', 'Duarte', 'Female', 'Aregu', 'Calle Independencia Nacional 234'],
        ['Juan Carlos', 'Sanabria', 'Male', 'Aregu', 'Barrio Valle Pucú, calle 5'],
        ['Elvira', 'Insfrán', 'Female', 'Aregu', 'Calle Cerro Corá 102'],
        ['Víctor', 'Maidana', 'Male', 'Aregu', 'Av. Mcal. López 1.150'],
        ['Gloria', 'Caballero', 'Female', 'Aregu', 'Calle 14 de Mayo 67'],
        ['Alcides', 'Portillo', 'Male', 'Itaugu', 'Ruta 1 km 30, lote 8'],
        ['Teresa', 'Vera', 'Female', 'Itaugu', 'Calle Mcal. Estigarribia 410'],
        ['Osvaldo', 'Noguera', 'Male', 'Itaugu', 'Barrio San Miguel, casa 21'],
        ['Lorena', 'Pereira', 'Female', 'Ypacara', 'Calle Presidente Franco 76'],
        ['Rubén', 'Escobar', 'Male', 'Ypacara', 'Barrio Santa Ana, casa 9'],
        ['Mirtha', 'Aquino', 'Female', 'Ypacara', 'Calle Pa\'i Pérez 33'],
        ['Édgar', 'Zárate', 'Male', 'Capiat', 'Ruta 2 km 20, casa 14'],
        ['Karina', 'Ledesma', 'Female', 'Capiat', 'Calle Gral. Díaz 280'],
        ['Sergio', 'Montiel', 'Male', 'Luque', 'Av. San Blas 430'],
        ['Analía', 'Servín', 'Female', 'Luque', 'Calle Mcal. López 215'],
        ['Cristian', 'Acosta', 'Male', 'San Lorenzo', 'Calle Paraguarí 210'],
    ];

    // ---------------------------------------------------------------- mascotas
    // [nombre, owner_idx, especie, raza, género, nacimiento, tamaño, peso, pelaje, reproducción inicial]
    $petRows = [
        ['Toby', 0, 'Canino', 'Labrador Retriever', 'Male', '2019-03-14', 'Big', 31.0, 'Corto', 'Castrated'],
        ['Luna', 0, 'Canino', 'Golden Retriever', 'Female', '2021-07-22', 'Big', 27.5, 'Largo', 'Normal'],
        ['Rex', 1, 'Canino', 'Pastor Alemán', 'Male', '2017-11-05', 'Big', 34.0, 'Mediano', 'Normal'],
        ['Coco', 1, 'Canino', 'Poodle', 'Female', '2016-01-10', 'Small', 5.2, 'Rizado', 'Sterilized'],
        ['Rocky', 2, 'Canino', 'Rottweiler', 'Male', '2020-05-18', 'Giant', 44.0, 'Corto', 'Normal'],
        ['Bella', 2, 'Canino', 'Beagle', 'Female', '2022-09-30', 'Small', 10.5, 'Corto', 'Normal'],
        ['Duque', 3, 'Canino', 'Pitbull', 'Male', '2023-04-12', 'Medium', 23.0, 'Corto', 'Normal'],
        ['Max', 4, 'Canino', 'SRD', 'Male', '2018-12-01', 'Medium', 19.0, 'Corto', 'Castrated'],
        ['Pancho', 5, 'Canino', 'Bulldog Francés', 'Male', '2023-02-14', 'Small', 11.0, 'Corto', 'Normal'],
        ['Thor', 6, 'Canino', 'Doberman', 'Male', '2019-08-20', 'Big', 36.0, 'Corto', 'Normal'],
        ['Negra', 7, 'Canino', 'SRD', 'Female', '2013-06-03', 'Medium', 21.0, 'Corto', 'Normal'],
        ['Reina', 8, 'Canino', 'Boxer', 'Female', '2017-10-09', 'Big', 27.0, 'Corto', 'Normal'],
        ['Pepe', 9, 'Canino', 'SRD', 'Male', '2014-02-11', 'Medium', 17.0, 'Corto', 'Castrated'],
        ['Dina', 10, 'Canino', 'Cocker Spaniel', 'Female', '2012-04-28', 'Medium', 13.5, 'Largo', 'Normal'],
        ['Chester', 11, 'Canino', 'Schnauzer', 'Male', '2020-09-17', 'Small', 8.5, 'Mediano', 'Normal'],
        ['Tango', 12, 'Canino', 'Salchicha', 'Male', '2018-05-25', 'Small', 7.8, 'Corto', 'Castrated'],
        ['Frida', 13, 'Canino', 'Yorkshire Terrier', 'Female', '2021-11-30', 'Tiny', 3.1, 'Largo', 'Normal'],
        ['Gringo', 14, 'Canino', 'Pastor Belga', 'Male', '2022-01-19', 'Big', 29.0, 'Mediano', 'Normal'],
        ['Mora', 15, 'Canino', 'SRD', 'Female', '2019-07-07', 'Medium', 18.0, 'Corto', 'Sterilized'],
        // cachorros
        ['Chispa', 16, 'Canino', 'SRD', 'Female', '2026-08-24', 'Medium', 6.0, 'Corto', 'Normal'],
        ['Bruno', 17, 'Canino', 'Labrador Retriever', 'Male', '2026-08-28', 'Big', 7.5, 'Corto', 'Normal'],
        ['Mimi', 18, 'Canino', 'Chihuahua', 'Female', '2026-09-02', 'Tiny', 1.1, 'Corto', 'Normal'],
        ['Zeus', 19, 'Canino', 'Rottweiler', 'Male', '2026-06-15', 'Giant', 18.0, 'Corto', 'Normal'],
        ['Kira', 20, 'Canino', 'Pastor Alemán', 'Female', '2026-05-02', 'Big', 19.0, 'Mediano', 'Normal'],
        ['Rufo', 21, 'Canino', 'SRD', 'Male', '2026-03-20', 'Medium', 15.0, 'Corto', 'Normal'],
        ['Lola', 22, 'Canino', 'Cocker Spaniel', 'Female', '2026-04-10', 'Medium', 9.0, 'Largo', 'Normal'],
        ['Oso', 23, 'Canino', 'Bulldog Francés', 'Male', '2025-12-12', 'Small', 8.0, 'Corto', 'Normal'],
        // gatos adultos
        ['Simba', 4, 'Felino', 'SRD', 'Male', '2022-06-05', 'Small', 4.4, 'Corto', 'Castrated'],
        ['Nala', 5, 'Felino', 'Siamés', 'Female', '2021-10-18', 'Small', 3.6, 'Corto', 'Sterilized'],
        ['Milo', 24, 'Felino', 'Persa', 'Male', '2020-03-25', 'Small', 4.6, 'Largo', 'Normal'],
        ['Princesa', 9, 'Felino', 'SRD', 'Female', '2023-01-08', 'Small', 3.8, 'Mediano', 'Normal'],
        ['Canela', 12, 'Felino', 'Angora', 'Female', '2021-05-15', 'Small', 3.2, 'Largo', 'Sterilized'],
        ['Garfield', 13, 'Felino', 'SRD', 'Male', '2015-08-02', 'Small', 5.9, 'Corto', 'Castrated'],
        ['Mía', 14, 'Felino', 'Maine Coon', 'Female', '2019-12-12', 'Small', 5.2, 'Largo', 'Normal'],
        ['Félix', 15, 'Felino', 'SRD', 'Male', '2023-09-09', 'Small', 4.1, 'Corto', 'Normal'],
        ['Cleo', 16, 'Felino', 'Siamés', 'Female', '2012-03-03', 'Small', 3.3, 'Corto', 'Sterilized'],
        ['Tom', 1, 'Felino', 'SRD', 'Male', '2021-01-20', 'Small', 4.9, 'Corto', 'Castrated'],
        // gatitos
        ['Mishi', 3, 'Felino', 'SRD', 'Female', '2026-09-01', 'Small', 0.9, 'Corto', 'Normal'],
        ['Pelusa', 2, 'Felino', 'Persa', 'Female', '2026-07-12', 'Small', 1.9, 'Largo', 'Normal'],
        ['Tigre', 20, 'Felino', 'SRD', 'Male', '2026-04-25', 'Small', 3.0, 'Corto', 'Normal'],
    ];

    // ---------------------------------------- escala: propietarios y mascotas generados
    $newOwners = [
        ['Adriana', 'Female'], ['Camila', 'Female'], ['Diego', 'Male'], ['Estela', 'Female'], ['Fernando', 'Male'],
        ['Graciela', 'Female'], ['Héctor', 'Male'], ['Irma', 'Female'], ['Jorge', 'Male'], ['Lidia', 'Female'],
        ['Mario', 'Male'], ['Nilda', 'Female'], ['Óscar', 'Male'], ['Patricia', 'Female'], ['Ramón', 'Male'],
        ['Silvia', 'Female'], ['Tomás', 'Male'], ['Verónica', 'Female'], ['Walter', 'Male'], ['Ximena', 'Female'],
        ['Yolanda', 'Female'], ['Zulma', 'Female'], ['Alberto', 'Male'], ['Blanca', 'Female'], ['César', 'Male'],
        ['Dora', 'Female'], ['Emilio', 'Male'], ['Flora', 'Female'], ['Guillermo', 'Male'], ['Hilda', 'Female'],
        ['Ignacio', 'Male'], ['Julia', 'Female'], ['Kevin', 'Male'], ['Laura', 'Female'], ['Miguel', 'Male'],
        ['Natalia', 'Female'], ['Olga', 'Female'], ['Pablo', 'Male'], ['Rosa', 'Female'], ['Santiago', 'Male'],
        ['Telma', 'Female'], ['Ulises', 'Male'], ['Valeria', 'Female'], ['Wilfrido', 'Male'], ['Yessica', 'Female'],
    ];
    $lasts = [
        'Ramírez', 'Acuña', 'Barrios', 'Colmán', 'Escurra', 'Fleitas', 'Galeano', 'Domínguez', 'Ibarra', 'Jara',
        'Lezcano', 'Machuca', 'Núñez', 'Ovelar', 'Paredes', 'Quiñónez', 'Riveros', 'Samaniego', 'Torres', 'Urbieta',
        'Valdez', 'Zacarías', 'Peralta', 'Medina', 'Brítez', 'Cabañas', 'Delvalle', 'Lugo', 'Orué', 'Sosa',
    ];
    $streets = [
        'Calle Mcal. Estigarribia', 'Calle Cerro Corá', 'Calle San Roque', 'Calle Yegros', 'Av. Mcal. López', 'Calle 14 de Mayo',
        'Calle Tte. Fariña', 'Calle Independencia Nacional', 'Calle Presidente Franco', 'Calle Gral. Díaz', 'Calle Cerro Ñu',
        'Barrio Valle Pucú, calle', 'Compañía Estanzuela, lote', 'Calle Itapúa', 'Calle Paraguarí', 'Calle Cerro Memby',
    ];
    $cityPool = array_merge(array_fill(0, 10, 'Aregu'), array_fill(0, 3, 'Itaugu'), array_fill(0, 2, 'Ypacara'), array_fill(0, 2, 'Capiat'), array_fill(0, 2, 'Luque'), ['San Lorenzo']);
    foreach ($newOwners as $k => [$first, $gender]) {
        $ownerRows[] = [$first, $lasts[($k * 7 + 3) % count($lasts)], $gender, $pick($cityPool), $pick($streets).' '.mt_rand(10, 980)];
    }

    // [raza, tamaño, peso mín., peso máx., pelaje, frecuencia relativa]
    $dogBreeds = [
        ['SRD', 'Medium', 8, 25, 'Corto', 30], ['Labrador Retriever', 'Big', 25, 34, 'Corto', 6], ['Pastor Alemán', 'Big', 28, 38, 'Mediano', 5],
        ['Poodle', 'Small', 4, 9, 'Rizado', 6], ['Chihuahua', 'Tiny', 1.5, 3.5, 'Corto', 4], ['Salchicha', 'Small', 6, 10, 'Corto', 4],
        ['Bulldog Francés', 'Small', 8, 13, 'Corto', 4], ['Beagle', 'Small', 9, 13, 'Corto', 3], ['Rottweiler', 'Giant', 35, 50, 'Corto', 3],
        ['Cocker Spaniel', 'Medium', 11, 15, 'Largo', 4], ['Schnauzer', 'Small', 6, 11, 'Mediano', 4], ['Pitbull', 'Medium', 18, 28, 'Corto', 5],
        ['Boxer', 'Big', 25, 32, 'Corto', 3], ['Yorkshire Terrier', 'Tiny', 2, 3.5, 'Largo', 4], ['Shih Tzu', 'Small', 5, 9, 'Largo', 4],
        ['Golden Retriever', 'Big', 25, 32, 'Largo', 3], ['Pastor Belga', 'Big', 25, 32, 'Mediano', 2], ['Doberman', 'Big', 30, 40, 'Corto', 2],
    ];
    $catBreeds = [
        ['SRD', 'Small', 3, 5.5, 'Corto', 20], ['SRD', 'Small', 3, 5.5, 'Mediano', 6], ['Siamés', 'Small', 3, 4.5, 'Corto', 3],
        ['Persa', 'Small', 3.5, 5.5, 'Largo', 3], ['Angora', 'Small', 3, 4.5, 'Largo', 2], ['Maine Coon', 'Small', 5, 8, 'Largo', 1],
        ['Bengalí', 'Small', 4, 6, 'Corto', 1],
    ];
    $dogNames = [
        'Bobby', 'Nico', 'Lucky', 'Rambo', 'Fido', 'Maya', 'Sasha', 'Roco', 'Taco', 'Atila', 'Ñandé', 'Jaguar', 'Pytã', 'Morocha',
        'Kuarahy', 'Tyson', 'Kaiser', 'Goliat', 'Apolo', 'Hera', 'Lobo', 'Dante', 'Nena', 'Piojo', 'Chocolate', 'Canelo', 'Panchita',
        'Manchas', 'Pinky', 'Rina', 'Milka', 'Kenia', 'Blanquita', 'Tuti', 'Churro', 'Capitán', 'Samba', 'Trueno', 'Fiona', 'Gala',
        'Ringo', 'Tita', 'Wilson', 'Yeyo', 'Zoe', 'Hachi', 'Bandido', 'Mateo', 'Pocho', 'Rulo', 'Sultán', 'Tomi', 'Uno', 'Vito', 'Chico', 'Dolly',
    ];
    $catNames = [
        'Michi', 'Garra', 'Tito', 'Coqui', 'Bigotes', 'Misty', 'Oreo', 'Pantera', 'Mochi', 'Ágata', 'Bruja', 'Pelu', 'Gatuno', 'Kitty',
        'Lupe', 'Neko', 'Ópalo', 'Pepa', 'Rayo', 'Sombra', 'Tuna', 'Vainilla', 'Whisky', 'Yuyo', 'Zafiro', 'Ámbar', 'Bolita', 'Cuqui',
        'Dorado', 'Estrella', 'Tigresa',
    ];
    for ($p = 0; $p < 73; $p++) {
        $isDog = $p < 47;
        $breeds = $isDog ? $dogBreeds : $catBreeds;
        [$breed, $size, $wMin, $wMax, $fur] = $breeds[$wpick($breeds, fn ($b) => $b[5])];
        $r = mt_rand(1, 100);
        if ($r <= 18) { // cachorros y gatitos
            $birth = CarbonImmutable::create(2025, 12, 1)->addDays(mt_rand(0, 278));
        } elseif ($r <= 80) { // adultos
            $birth = CarbonImmutable::create(2018, 11, 1)->addDays(mt_rand(0, 2433));
        } else { // senior
            $birth = CarbonImmutable::create(2011, 1, 1)->addDays(mt_rand(0, 2844));
        }
        $ageToday = $days($birth, $today);
        $adultWeight = $wMin + ($wMax - $wMin) * mt_rand(0, 100) / 100;
        $weight = round($ageToday < 330 ? max(0.4, $adultWeight * (0.12 + 0.88 * $ageToday / 330)) : $adultWeight, 1);
        $gender = $chance(50) ? 'Male' : 'Female';
        $repro = 'Normal';
        if ($r > 18 && $chance($r > 80 ? 40 : 35)) {
            $repro = $gender === 'Male' ? 'Castrated' : 'Sterilized';
        }
        $petRows[] = [
            $isDog ? $dogNames[$p] : $catNames[$p - 47], $p < 45 ? 25 + $p : mt_rand(0, 69),
            $isDog ? 'Canino' : 'Felino', $breed, $gender, $birth->toDateString(), $size, $weight, $fur, $repro,
        ];
    }

    $pets = [];
    foreach ($petRows as $i => [$name, $ownerIdx, $species, $breed, $gender, $birth, $size, $weight, $fur, $repro]) {
        $pets[$i] = compact('name', 'ownerIdx', 'species', 'breed', 'gender', 'size', 'weight', 'fur') + [
            'birth' => $birth,
            'repro0' => $repro,
            'changes' => [], // [fecha 'Y-m-d' => estado]
            'events' => [],  // fechas de cualquier registro clínico (para created_at)
            'vet' => $pick($vetList),
        ];
    }
    $byName = array_column(array_map(fn ($p, $i) => ['n' => $p['name'], 'i' => $i], $pets, array_keys($pets)), 'i', 'n');

    $reproAt = function (array $pet, CarbonImmutable $date) {
        $state = $pet['repro0'];
        ksort($pet['changes']);
        foreach ($pet['changes'] as $d => $s) {
            if ($d <= $date->toDateString()) {
                $state = $s;
            }
        }

        return $state;
    };
    $ageDays = fn (array $pet, CarbonImmutable $date) => $days(CarbonImmutable::parse($pet['birth']), $date);
    $stateAfter = fn (array $pet, string $type) => $pet['gender'] === 'Female' ? 'Sterilized' : 'Castrated';

    // ----------------------------------------------------------- plantillas de lab
    // key => [tipo, título, líneas del informe, resumen para la observación]
    $labs = [
        'hemo_normal' => ['Hematología', 'Hemograma completo', ['Eritrocitos: 6,9 M/uL (5,5 - 8,5)', 'Hemoglobina: 15,1 g/dL (12 - 18)', 'Hematocrito: 45 % (37 - 55)', 'Leucocitos: 9.100 /uL (6.000 - 17.000)', 'Plaquetas: 310.000 /uL (200.000 - 500.000)', 'Sin alteraciones morfologicas relevantes.'], 'Hemograma completo dentro de parámetros normales para la especie y edad.'],
        'hemo_infec' => ['Hematología', 'Hemograma completo', ['Eritrocitos: 6,2 M/uL (5,5 - 8,5)', 'Hematocrito: 41 % (37 - 55)', 'Leucocitos: 22.400 /uL (6.000 - 17.000)', 'Neutrofilos: 82 % con desvio a la izquierda', 'Plaquetas: 280.000 /uL'], 'Leucocitosis con neutrofilia y desvío a la izquierda, compatible con proceso infeccioso/inflamatorio.'],
        'hemo_ehrl' => ['Hematología', 'Hemograma completo', ['Eritrocitos: 4,6 M/uL (5,5 - 8,5)', 'Hematocrito: 31 % (37 - 55)', 'Leucocitos: 5.200 /uL', 'Plaquetas: 62.000 /uL (200.000 - 500.000)', 'Se observan inclusiones compatibles con morulas en monocitos.'], 'Anemia leve y trombocitopenia marcada, compatible con hemoparásito (Ehrlichia).'],
        'bioq_normal' => ['Bioquímica', 'Perfil bioquímico', ['Glucosa: 94 mg/dL', 'Urea: 31 mg/dL', 'Creatinina: 0,9 mg/dL', 'ALT: 41 U/L', 'Fosfatasa alcalina: 78 U/L', 'Proteínas totales: 6,9 g/dL'], 'Perfil bioquímico sin alteraciones. Función renal y hepática normales.'],
        'bioq_renal' => ['Bioquímica', 'Perfil renal', ['Urea: 112 mg/dL (15 - 60)', 'Creatinina: 3,4 mg/dL (0,5 - 1,5)', 'Fósforo: 7,8 mg/dL', 'Potasio: 4,1 mEq/L'], 'Azotemia con elevación de urea y creatinina, compatible con enfermedad renal crónica.'],
        'rapid_parvo' => ['Prueba Rápida', 'Test rápido Parvovirus (antígeno en heces)', ['Resultado: POSITIVO'], 'Test rápido de Parvovirus POSITIVO.'],
        'rapid_ehrl' => ['Prueba Rápida', 'Test rápido Ehrlichia canis (anticuerpos)', ['Resultado: POSITIVO', 'Anaplasma: negativo', 'Dirofilaria: negativo'], 'Test rápido de Ehrlichia canis POSITIVO.'],
        'rapid_felv' => ['Prueba Rápida', 'Test rápido FeLV / FIV', ['FeLV (antígeno): NEGATIVO', 'FIV (anticuerpos): NEGATIVO'], 'Test rápido FeLV/FIV negativo.'],
        'raspado' => ['Parasitología', 'Raspado cutáneo profundo', ['Muestra: región dorsal y periocular', 'Demodex canis: ++ (abundantes formas adultas y ninfas)', 'Sarcoptes scabiei: no observado'], 'Raspado cutáneo con abundantes Demodex canis. Compatible con demodicosis.'],
        'coprol' => ['Coprología', 'Coprología por flotación', ['Giardia spp.: quistes presentes (+)', 'Ancylostoma spp.: huevos escasos', 'Sin otros parásitos'], 'Coprología positiva a Giardia y huevos de Ancylostoma.'],
        'uro' => ['Uroanálisis', 'Uroanálisis completo', ['Densidad: 1.034', 'pH: 7,5', 'Proteínas: +', 'Sangre: ++', 'Sedimento: cristales de estruvita abundantes'], 'Hematuria y cristaluria de estruvita; pH alcalino.'],
        'cultivo_oido' => ['Cultivo', 'Cultivo y antibiograma de oído', ['Desarrollo de Staphylococcus pseudintermedius', 'Sensible a: amoxicilina-clavulánico, cefalexina, enrofloxacina', 'Resistente a: penicilina'], 'Cultivo de oído con Staphylococcus pseudintermedius; antibiograma disponible.'],
        'rx' => ['Otros', 'Radiografía (2 proyecciones)', ['Proyecciones laterolateral y ventrodorsal', 'Ver observaciones del médico veterinario tratante.'], 'Radiografía realizada; hallazgos compatibles con el cuadro clínico.'],
        'eco' => ['Otros', 'Ecografía abdominal', ['Estudio con transductor microconvexo', 'Ver observaciones del médico veterinario tratante.'], 'Ecografía abdominal realizada; hallazgos compatibles con el cuadro clínico.'],
    ];

    // --------------------------------------------------------------- casos clínicos
    // sp: especie (null = ambas) | age: puppy|adult|senior|null | sex | intact: solo no esterilizados
    // urg: urgencia IA | w: peso | tests: [clave => % de probabilidad] | surg: [tipo, obs] | ctrl: admite control
    // Placeholders: {p} nombre, {d} días, {w} peso, {o} o/a, {el} el/la
    $cases = [
        'gastro' => ['sp' => 'Canino', 'w' => 6, 'urg' => 'baja', 'ctrl' => true, 'tests' => ['coprol' => 25],
            'an' => 'La propietaria refiere que {p} presenta vómitos y diarrea desde hace {d} días. Comió restos de comida del patio. Decaíd{o} pero toma agua.',
            'dx' => 'Gastroenteritis aguda de probable origen alimentario.',
            'tx' => 'Metronidazol 15 mg/kg c/12 hs por 5 días. Omeprazol 1 mg/kg c/24 hs por 5 días. Dieta blanda (arroz con pollo hervido) por 5 días. Suero oral fraccionado.',
            'ob' => 'Se hidrató por vía subcutánea en consultorio. Control si persisten los vómitos.'],
        'dermatitis' => ['sp' => 'Canino', 'w' => 6, 'urg' => 'baja', 'ctrl' => true,
            'an' => 'Prurito intenso en dorso y base de la cola desde hace {d} semanas. {p} se rasca y se muerde. Se observan pulgas en el pelaje.',
            'dx' => 'Dermatitis alérgica a la picadura de pulga (DAPP).',
            'tx' => 'Antiparasitario externo oral (isoxazolina) en dosis única, repetir al mes. Prednisolona 0,5 mg/kg c/24 hs por 5 días. Baño con shampoo hipoalergénico semanal. Tratar el ambiente.',
            'ob' => 'Se indica tratar a todos los animales del hogar.'],
        'otitis' => ['sp' => 'Canino', 'w' => 5, 'urg' => 'baja', 'ctrl' => true, 'tests' => ['cultivo_oido' => 30],
            'an' => 'Sacude la cabeza y se rasca la oreja desde hace {d} días. Olor fétido y secreción marrón oscura en el conducto auditivo.',
            'dx' => 'Otitis externa bacteriana con sobrecrecimiento de levaduras.',
            'tx' => 'Limpieza auricular con solución ceruminolítica. Gotas óticas con antibiótico, antimicótico y corticoide, 5 gotas c/12 hs por 10 días.',
            'ob' => 'Otoscopia: conducto eritematoso con abundante cerumen. Se tomó muestra.'],
        'tos' => ['sp' => 'Canino', 'w' => 3, 'urg' => 'media', 'ctrl' => true, 'tests' => ['hemo_infec' => 30],
            'an' => 'Tos seca y seca en accesos desde hace {d} días, con arcadas al final. Convivió con otros perros en una guardería. Sin fiebre al ingreso.',
            'dx' => 'Traqueobronquitis infecciosa canina (tos de las perreras).',
            'tx' => 'Doxiciclina 10 mg/kg c/24 hs por 10 días. Bromhexina según peso c/12 hs. Reposo, evitar el collar (usar pechera) y aislar de otros perros.',
            'ob' => 'Auscultación pulmonar sin ruidos agregados.'],
        'herida' => ['sp' => null, 'w' => 4, 'urg' => 'media', 'ctrl' => true,
            'an' => '{p} llegó con una herida en {zona} luego de una pelea / enganche con un alambrado. Sangrado leve, cojea.',
            'dx' => 'Herida lacerante superficial con contaminación. Sin compromiso óseo al examen.',
            'tx' => 'Limpieza y sutura bajo sedación. Amoxicilina-clavulánico 12,5 mg/kg c/12 hs por 7 días. Meloxicam 0,1 mg/kg c/24 hs por 3 días (solo caninos). Curación diaria con clorhexidina. Collar isabelino.',
            'ob' => 'Control para retirar puntos en 10 días.'],
        'erliquiosis' => ['sp' => 'Canino', 'w' => 4, 'urg' => 'media', 'ctrl' => true, 'tests' => ['rapid_ehrl' => 80, 'hemo_ehrl' => 90],
            'an' => 'Decaimiento, fiebre intermitente y epistaxis ocasional desde hace {d} días. Tiene garrapatas frecuentes y vive en zona de monte.',
            'dx' => 'Erliquiosis canina (sospecha clínica, a confirmar con serología).',
            'tx' => 'Doxiciclina 10 mg/kg c/24 hs por 28 días. Control de garrapatas con collar + antiparasitario. Suplemento hematínico. Reposo.',
            'ob' => 'Mucosas pálidas. Se solicitan hemograma y test rápido.'],
        'sarna' => ['sp' => 'Canino', 'w' => 3, 'urg' => 'media', 'ctrl' => true, 'tests' => ['raspado' => 85],
            'an' => 'Alopecia en parches alrededor de los ojos y las patas anteriores desde hace {d} semanas, con costras y mal olor.',
            'dx' => 'Demodicosis canina localizada con piodermia secundaria.',
            'tx' => 'Isoxazolina oral una vez al mes por 3 meses. Cefalexina 25 mg/kg c/12 hs por 14 días. Baños con shampoo de peróxido de benzoílo c/3 días.',
            'ob' => 'Se realizó raspado cutáneo profundo.'],
        'parvo' => ['sp' => 'Canino', 'age' => 'puppy', 'w' => 3, 'urg' => 'alta', 'ctrl' => true, 'tests' => ['rapid_parvo' => 100, 'hemo_infec' => 70],
            'an' => 'Cachorro con vómitos incoercibles y diarrea sanguinolenta de olor fétido desde hace {d} días. No come ni toma agua. Esquema de vacunas incompleto.',
            'dx' => 'Parvovirosis canina con deshidratación moderada.',
            'tx' => 'Internación. Fluidoterapia IV con Ringer lactato. Maropitant 1 mg/kg SC c/24 hs. Ampicilina 20 mg/kg IV c/8 hs. Omeprazol 1 mg/kg c/24 hs. Ayuno 12 hs y reintroducción gradual de dieta.',
            'ob' => 'Pronóstico reservado. Se aísla del resto de los pacientes.'],
        'displasia' => ['sp' => 'Canino', 'age' => 'adult', 'w' => 3, 'urg' => 'baja', 'tests' => ['rx' => 70],
            'an' => 'Cojera intermitente del miembro posterior, que empeora tras el ejercicio. Le cuesta levantarse por las mañanas.',
            'dx' => 'Sospecha de displasia de cadera / artrosis coxofemoral.',
            'tx' => 'Meloxicam 0,1 mg/kg c/24 hs por 7 días. Condroprotector oral por 3 meses. Control de peso y ejercicio moderado.',
            'ob' => 'Dolor a la extensión de cadera. Se solicita radiografía.'],
        'ulcera' => ['sp' => null, 'w' => 3, 'urg' => 'media', 'ctrl' => true,
            'an' => 'Ojo derecho cerrado, lagrimeo y secreción desde hace {d} días. Se frota con la pata.',
            'dx' => 'Úlcera corneal superficial con conjuntivitis.',
            'tx' => 'Colirio de ciprofloxacina 1 gota c/6 hs por 7 días. Lágrima artificial c/8 hs. Collar isabelino.',
            'ob' => 'Test de fluoresceína positivo.'],
        'piometra' => ['sp' => 'Canino', 'sex' => 'Female', 'intact' => true, 'age' => 'adult', 'w' => 3, 'urg' => 'alta', 'ctrl' => true, 'tests' => ['hemo_infec' => 90, 'eco' => 90],
            'surg' => ['Ovariohisterectomía', 'OVH de urgencia por piómetra. Útero con abundante contenido purulento. Postoperatorio sin complicaciones.'],
            'an' => 'Hembra entera con decaimiento, polidipsia y secreción vaginal purulenta desde hace {d} días. Terminó el celo hace aproximadamente 6 semanas.',
            'dx' => 'Piómetra de cérvix abierto.',
            'tx' => 'Estabilización con fluidoterapia IV y antibioticoterapia. Ovariohisterectomía de urgencia. Amoxicilina-clavulánico 20 mg/kg c/12 hs por 10 días. Tramadol según peso.',
            'ob' => 'Propietario informado del riesgo anestésico. Se recomienda esterilizar a las hembras luego del primer celo.'],
        'golpe_calor' => ['sp' => 'Canino', 'months' => [2, 3, 10], 'w' => 3, 'urg' => 'emergencia', 'ctrl' => true,
            'an' => 'Estuvo toda la tarde en el patio a pleno sol, sin sombra ni agua. Jadeo intenso, salivación y temblores. Llegó casi sin responder.',
            'dx' => 'Golpe de calor (hipertermia 41,2 °C).',
            'tx' => 'Enfriamiento progresivo con agua tibia y ventilación. Fluidoterapia IV con Ringer a temperatura ambiente. Oxígeno. Monitoreo de temperatura cada 15 minutos.',
            'ob' => 'Se mantiene en observación por 24 hs. Se explicó al propietario la importancia de la sombra y el agua fresca.'],
        'intox' => ['sp' => 'Canino', 'w' => 2, 'urg' => 'emergencia', 'ctrl' => true,
            'an' => 'Hallado en el fondo de la casa con salivación profusa, temblores y diarrea. Sospecha de ingesta de veneno para hormigas / rodenticida.',
            'dx' => 'Intoxicación por organofosforado (sospecha).',
            'tx' => 'Atropina según peso. Lavado gástrico y carbón activado. Fluidoterapia IV. Diazepam si hay convulsiones. Internación y monitoreo.',
            'ob' => 'Se indica al propietario guardar el envase del producto.'],
        'sobrepeso' => ['sp' => null, 'age' => 'adult', 'w' => 3, 'urg' => 'baja',
            'an' => 'Control de peso. {p} aumentó de peso en los últimos meses, se agita al caminar. Come croquetas y sobras a voluntad.',
            'dx' => 'Sobrepeso (condición corporal 7/9).',
            'tx' => 'Dieta de control de peso, ración medida en 2 tomas. Eliminar sobras y premios calóricos. Paseos diarios progresivos.',
            'ob' => 'Peso actual {w} kg. Control de peso en 30 días.'],
        'dental' => ['sp' => null, 'age' => 'adult', 'w' => 3, 'urg' => 'baja', 'ctrl' => false,
            'an' => 'Mal aliento marcado y dificultad para masticar. El propietario nota sarro en los dientes.',
            'dx' => 'Enfermedad periodontal grado 2 con sarro.',
            'tx' => 'Higiene dental con ultrasonido (programar). Clindamicina 11 mg/kg c/12 hs por 7 días previo a la limpieza. Cepillado diario.',
            'ob' => 'Gingivitis en premolares y molares.'],
        'sacos' => ['sp' => 'Canino', 'w' => 3, 'urg' => 'baja',
            'an' => '{p} se arrastra sentad{o} en el piso y se lame la zona perianal. Olor fuerte.',
            'dx' => 'Impactación de sacos anales.',
            'tx' => 'Vaciado manual de sacos anales y lavado. Aumentar fibra en la dieta. Control en 30 días.',
            'ob' => 'Sin signos de absceso.'],
        'cistitis' => ['sp' => null, 'w' => 3, 'urg' => 'media', 'ctrl' => true, 'tests' => ['uro' => 70],
            'an' => 'Orina en pequeñas cantidades y muy seguido, con dolor. Notó sangre en la orina desde hace {d} días.',
            'dx' => 'Cistitis bacteriana.',
            'tx' => 'Amoxicilina-clavulánico 12,5 mg/kg c/12 hs por 10 días. Aumentar el consumo de agua. Dieta húmeda.',
            'ob' => 'Se solicita uroanálisis. Vejiga dolorosa a la palpación.'],
        'absceso' => ['sp' => null, 'w' => 3, 'urg' => 'media', 'ctrl' => true,
            'an' => 'Hinchazón dolorosa en {zona} luego de una pelea hace {d} días. Fiebre y falta de apetito.',
            'dx' => 'Absceso subcutáneo por mordedura.',
            'tx' => 'Drenaje y lavado del absceso con solución fisiológica y clorhexidina. Amoxicilina-clavulánico 12,5 mg/kg c/12 hs por 10 días. Collar isabelino.',
            'ob' => 'Se colocó drenaje. Control en 3 días.'],
        'miasis' => ['sp' => 'Canino', 'months' => [2, 3, 4, 10], 'w' => 2, 'urg' => 'media', 'ctrl' => true,
            'an' => 'Herida con larvas (bichera) en {zona}. Mal olor. {p} está decaíd{o}.',
            'dx' => 'Miasis cutánea con infección secundaria.',
            'tx' => 'Extracción manual de larvas bajo sedación y limpieza. Ivermectina SC (descartar razas sensibles) y amoxicilina-clavulánico 12,5 mg/kg c/12 hs por 10 días. Curaciones diarias.',
            'ob' => 'Se evitó aplicar productos caseros en la herida.'],
        'hotspot' => ['sp' => 'Canino', 'months' => [2, 3, 10], 'w' => 2, 'urg' => 'baja',
            'an' => 'Zona húmeda, roja y sin pelo en el flanco, que se lame sin parar desde hace {d} días.',
            'dx' => 'Dermatitis húmeda aguda (hot spot).',
            'tx' => 'Rasurado y limpieza del área. Spray antibiótico-corticoide tópico c/12 hs por 7 días. Collar isabelino.',
            'ob' => 'Descartar alergia alimentaria si recidiva.'],
        'cardio' => ['sp' => 'Canino', 'age' => 'senior', 'w' => 3, 'urg' => 'media', 'ctrl' => true, 'tests' => ['rx' => 60, 'eco' => 60],
            'an' => 'Tos nocturna, se cansa rápido en los paseos y respira agitado en reposo desde hace {d} semanas.',
            'dx' => 'Insuficiencia valvular mitral con signos de insuficiencia cardíaca congestiva.',
            'tx' => 'Furosemida 2 mg/kg c/12 hs. Pimobendan 0,25 mg/kg c/12 hs. Dieta baja en sodio. Control con ecocardiograma.',
            'ob' => 'Soplo sistólico grado IV/VI en foco mitral.'],
        'cuerpo_extrano' => ['sp' => 'Canino', 'w' => 2, 'urg' => 'alta', 'ctrl' => true, 'tests' => ['rx' => 100, 'hemo_normal' => 50],
            'surg' => ['Cirugía de Cuerpo Extraño', 'Enterotomía para extracción de cuerpo extraño (trozo de pelota). Sutura sin complicaciones.'],
            'an' => 'Vómitos repetidos desde hace {d} días, dolor abdominal y ausencia de heces. El propietario sospecha que se tragó parte de un juguete.',
            'dx' => 'Obstrucción intestinal por cuerpo extraño.',
            'tx' => 'Estabilización con fluidos IV. Laparotomía y enterotomía para extracción del cuerpo extraño. Ampicilina 20 mg/kg IV c/8 hs. Analgesia postoperatoria.',
            'ob' => 'Radiografía con patrón de obstrucción. Alta a las 48 hs.'],
        'giardia' => ['sp' => null, 'age' => 'puppy', 'w' => 3, 'urg' => 'baja', 'tests' => ['coprol' => 90],
            'an' => 'Cachorro con diarrea mucosa y pastosa de {d} días, abdomen distendido. Come bien.',
            'dx' => 'Parasitosis intestinal (Giardia / Ancylostoma).',
            'tx' => 'Fenbendazol 50 mg/kg c/24 hs por 5 días. Metronidazol 15 mg/kg c/12 hs por 5 días. Higiene estricta del ambiente.',
            'ob' => 'Repetir coprología a los 15 días.'],
        'mamario' => ['sp' => 'Canino', 'sex' => 'Female', 'intact' => true, 'age' => 'senior', 'w' => 2, 'urg' => 'media', 'tests' => ['rx' => 60, 'bioq_normal' => 60],
            'an' => 'Nódulos en las mamas, uno de ellos creció mucho en los últimos 2 meses. {p} nunca fue esterilizada.',
            'dx' => 'Neoplasia mamaria (sospecha), a confirmar con histopatología.',
            'tx' => 'Se programa exéresis quirúrgica con mastectomía parcial. Estudios prequirúrgicos (hemograma, bioquímica, radiografía de tórax).',
            'ob' => 'Nódulo de 3 cm en mama inguinal derecha, firme y móvil.'],
        'artrosis' => ['sp' => 'Canino', 'age' => 'senior', 'w' => 4, 'urg' => 'baja', 'ctrl' => true,
            'an' => 'Rigidez al levantarse, no quiere subir escalones y duerme mucho más que antes.',
            'dx' => 'Osteoartrosis generalizada.',
            'tx' => 'Meloxicam 0,1 mg/kg c/24 hs por 5 días. Condroprotector oral de forma continua. Cama ortopédica y control de peso.',
            'ob' => 'Dolor a la manipulación lumbar y de cadera.'],
        'renal' => ['sp' => null, 'age' => 'senior', 'w' => 3, 'urg' => 'media', 'ctrl' => true, 'tests' => ['bioq_renal' => 90, 'uro' => 60],
            'an' => 'Toma mucha agua, orina más de lo normal y viene bajando de peso. Menos apetito en las últimas semanas.',
            'dx' => 'Enfermedad renal crónica (estadio II - III).',
            'tx' => 'Dieta renal. Fluidoterapia subcutánea periódica. Quelante de fósforo. Control de presión arterial. Controles cada 3 meses.',
            'ob' => 'Deshidratación leve al examen. Peso actual {w} kg.'],
        'cachorro' => ['sp' => null, 'age' => 'puppy', 'w' => 5, 'urg' => 'baja',
            'an' => 'Primer control. Cachorro adoptado hace pocos días, come y juega bien. El propietario consulta por vacunas y desparasitación.',
            'dx' => 'Cachorro clínicamente sano. Parasitosis intestinal presuntiva.',
            'tx' => 'Desparasitación interna con pirantel-praziquantel. Plan de vacunación a iniciar. Alimento balanceado para cachorros.',
            'ob' => 'Peso {w} kg. Mucosas rosadas. Repetir desparasitación en 15 días.'],
        'chequeo' => ['sp' => null, 'age' => 'adult', 'w' => 6, 'urg' => 'baja', 'tests' => ['hemo_normal' => 35, 'bioq_normal' => 25],
            'an' => 'Chequeo anual. Sin signos de enfermedad, buen apetito y actividad normal.',
            'dx' => 'Paciente clínicamente sano.',
            'tx' => 'Mantener esquema de vacunación y desparasitación. Dieta de mantenimiento.',
            'ob' => 'Peso actual {w} kg. Próximo control en 12 meses.'],
        'atropello' => ['sp' => null, 'w' => 3, 'urg' => 'alta', 'ctrl' => true, 'tests' => ['rx' => 90, 'hemo_normal' => 40],
            'an' => 'Atropellado por una moto frente a la casa hace 2 horas. Dolor intenso, rasguños y no apoya el miembro anterior izquierdo.',
            'dx' => 'Politraumatismo leve con contusión pulmonar y fractura de radio (sospecha).',
            'tx' => 'Analgesia (tramadol según peso), fluidoterapia IV, oxígeno e inmovilización. Antibiótico profiláctico. Radiografías de tórax y miembro.',
            'ob' => 'Se deriva a cirugía ortopédica si la fractura lo requiere.'],
        'anorexia' => ['sp' => null, 'w' => 3, 'urg' => 'media', 'ctrl' => true, 'tests' => ['hemo_infec' => 50, 'bioq_normal' => 40],
            'an' => 'Decaimiento y falta de apetito desde hace {d} días. Bebe poco. Sin vómitos ni diarrea.',
            'dx' => 'Síndrome febril inespecífico.',
            'tx' => 'Fluidoterapia subcutánea. Estimulante del apetito. Antibiótico de amplio espectro por 7 días. Reevaluar con estudios si persiste.',
            'ob' => 'Temperatura 39,6 °C.'],
        // felinos
        'rinotraqueitis' => ['sp' => 'Felino', 'w' => 6, 'urg' => 'media', 'ctrl' => true,
            'an' => 'Estornudos, secreción nasal y ocular, ojos cerrados y menos apetito desde hace {d} días. Convive con otro gato.',
            'dx' => 'Complejo respiratorio felino (rinotraqueítis).',
            'tx' => 'Doxiciclina 5 mg/kg c/12 hs por 10 días. Colirio con antibiótico c/8 hs. Nebulizaciones con solución fisiológica. Suplemento de lisina. Alimento húmedo tibio.',
            'ob' => 'Vacunación incompleta. Aislar del otro gato.'],
        'flutd' => ['sp' => 'Felino', 'sex' => 'Male', 'w' => 3, 'urg' => 'emergencia', 'ctrl' => true, 'tests' => ['uro' => 80, 'bioq_renal' => 40],
            'an' => 'Entra y sale de la caja de arena sin orinar, maúlla de dolor y se lame el pene. Última micción hace más de 24 hs.',
            'dx' => 'Obstrucción uretral (FLUTD) en felino macho.',
            'tx' => 'Sedación y desobstrucción con sonda uretral. Fluidoterapia IV. Prazosina y analgesia. Dieta húmeda urinaria. Internación 48 hs con sonda.',
            'ob' => 'Vejiga distendida y dolorosa. Pronóstico reservado ante recidiva (evaluar uretrostomía).'],
        'pulgas_gato' => ['sp' => 'Felino', 'w' => 4, 'urg' => 'baja',
            'an' => 'Se rasca mucho y tiene pequeñas costras en el cuello. El propietario vio pulgas.',
            'dx' => 'Infestación por pulgas con dermatitis miliar.',
            'tx' => 'Antiparasitario externo spot-on en dosis única, repetir al mes. Tratar al resto de los animales y el ambiente.',
            'ob' => 'Evitar productos de uso canino.'],
        'abceso_gato' => ['sp' => 'Felino', 'w' => 3, 'urg' => 'media', 'ctrl' => true,
            'an' => 'Hinchazón en {zona} luego de salir de noche. Fiebre, apático y no come desde ayer.',
            'dx' => 'Absceso por mordedura de pelea.',
            'tx' => 'Drenaje y lavado. Amoxicilina-clavulánico 12,5 mg/kg c/12 hs por 10 días. Analgesia. Mantener dentro de la casa.',
            'ob' => 'Test FeLV/FIV recomendado.', 'tests' => ['rapid_felv' => 60]],
        'estomatitis' => ['sp' => 'Felino', 'age' => 'adult', 'w' => 2, 'urg' => 'baja',
            'an' => 'Babea, mal aliento y deja caer el alimento al comer desde hace semanas.',
            'dx' => 'Gingivoestomatitis crónica felina.',
            'tx' => 'Limpieza dental y evaluación de extracciones. Analgesia con buprenorfina. Antibiótico por 10 días. Dieta blanda.',
            'ob' => 'Encías muy inflamadas y friables.'],
        'bolas_pelo' => ['sp' => 'Felino', 'w' => 3, 'urg' => 'baja',
            'an' => 'Arcadas y vómitos con pelo, heces duras, pelaje largo sin cepillar.',
            'dx' => 'Tricobezoar (bola de pelo) con constipación leve.',
            'tx' => 'Laxante en pasta c/24 hs por 5 días. Cepillado diario. Aumentar fibra de la dieta.',
            'ob' => 'Abdomen blando, sin signos de obstrucción.'],
        'hipertiroidismo' => ['sp' => 'Felino', 'age' => 'senior', 'w' => 2, 'urg' => 'media', 'ctrl' => true, 'tests' => ['bioq_normal' => 50],
            'an' => 'Pierde peso aunque come mucho, está inquieto y maúlla de noche.',
            'dx' => 'Hipertiroidismo (sospecha).',
            'tx' => 'Metimazol 2,5 mg c/12 hs. Control de T4 total a las 3 semanas. Dieta para pacientes senior.',
            'ob' => 'Taquicardia y glándula tiroides palpable.'],
        'tina' => ['sp' => 'Felino', 'w' => 2, 'urg' => 'media', 'ctrl' => true,
            'an' => 'Áreas circulares sin pelo con descamación en la cara y las orejas desde hace {d} semanas. Hay niños en la casa.',
            'dx' => 'Dermatofitosis (tiña) probable.',
            'tx' => 'Itraconazol 5 mg/kg c/24 hs por 4 semanas. Baños antifúngicos 2 veces por semana. Limpieza del ambiente. Precaución: zoonosis.',
            'ob' => 'Lámpara de Wood sugestiva. Se informa riesgo zoonótico.'],
        'gatito' => ['sp' => 'Felino', 'age' => 'puppy', 'w' => 5, 'urg' => 'baja',
            'an' => 'Control de gatito rescatado. Come bien, juega, heces blandas. Consulta por vacunas y desparasitación.',
            'dx' => 'Gatito clínicamente sano. Parasitosis intestinal presuntiva.',
            'tx' => 'Desparasitación interna. Plan de vacunación (Triple Felina). Alimento para gatitos.',
            'ob' => 'Peso {w} kg. Test FeLV/FIV recomendado antes de vacunar.', 'tests' => ['rapid_felv' => 60]],
        'otodectes' => ['sp' => 'Felino', 'w' => 3, 'urg' => 'baja',
            'an' => 'Se sacude la cabeza y tiene cerumen negro seco en ambas orejas.',
            'dx' => 'Otoacariosis (Otodectes cynotis).',
            'tx' => 'Limpieza auricular. Selamectina spot-on en dosis única, repetir a los 30 días.',
            'ob' => 'Otoscopia: ácaros visibles.'],
    ];
    $zonas = ['el miembro posterior', 'el cuello', 'el lomo', 'la base de la cola', 'la oreja', 'el hocico'];
    $addenda = [
        'Se ajusta la dosis según el peso actual.',
        'Se agrega control telefónico a las 48 hs.',
        'Propietario informado sobre los signos de alarma.',
        'Se indica reposo relativo durante el tratamiento.',
        'Se suma probiótico durante el tratamiento.',
    ];

    $caseText = function (string $t, array $pet, int $d) use ($zonas, $pick): string {
        return strtr($t, [
            '{p}' => $pet['name'],
            '{d}' => (string) $d,
            '{w}' => number_format($pet['weight'], 1, ',', ''),
            '{o}' => $pet['gender'] === 'Female' ? 'a' : 'o',
            '{el}' => $pet['gender'] === 'Female' ? 'la' : 'el',
            '{zona}' => $pick($zonas),
        ]);
    };

    // --------------------------------------------------- cirugías electivas / agenda
    // nombre => [tipo, fecha, observación]
    $elective = [
        'Duque' => ['Castración', '2026-04-14', 'Castración electiva sin complicaciones. Alta el mismo día. Retiro de puntos a los 10 días.'],
        'Bella' => ['Ovariohisterectomía', '2026-06-09', 'OVH electiva. Anestesia inhalatoria. Recuperación sin incidentes.'],
        'Frida' => ['Ovariohisterectomía', '2026-09-15', 'OVH electiva en paciente de talla mini. Se extremaron cuidados de temperatura.'],
        'Princesa' => ['Esterilización', '2026-07-21', 'Esterilización felina por flanco. Sin complicaciones.'],
        'Félix' => ['Castración', '2026-08-11', 'Castración felina de rutina. Alta el mismo día.'],
        'Rufo' => ['Castración', '2026-09-29', 'Castración electiva. Cachorro de 6 meses, recuperación rápida.'],
        'Tango' => ['Extracción de Tumores', '2026-05-27', 'Exéresis de nódulo cutáneo (lipoma) en flanco izquierdo. Se envía muestra a histopatología.'],
        'Coco' => ['Cirugía Dental', '2026-03-18', 'Limpieza y extracción de dos premolares con movilidad grado 3.'],
        'Pepe' => ['Extracción de Tumores', '2026-08-05', 'Exéresis de nódulo en miembro anterior. Paciente senior con anestesia monitoreada.'],
        // agenda próxima
        'Luna' => ['Ovariohisterectomía', '2026-10-23', 'Cirugía programada. Ayuno sólido de 12 hs y de agua de 4 hs. Estudios prequirúrgicos realizados.'],
        'Milo' => ['Castración', '2026-10-26', 'Cirugía programada. Ayuno de 12 hs. Se aprovecha para limpieza dental si el estado lo permite.'],
        'Chester' => ['Cirugía Dental', '2026-10-28', 'Cirugía programada. Limpieza dental con ultrasonido y posibles extracciones. Ayuno de 12 hs.'],
    ];
    foreach ($pets as $i => $pet) {
        if ($i < 40 || $pet['repro0'] !== 'Normal' || ! $chance(14)) {
            continue;
        }
        $earliest = CarbonImmutable::parse($pet['birth'])->addDays(210)->max($start);
        $latest = $today->subDays(7);
        if ($earliest->gt($latest)) {
            continue;
        }
        $type = $pet['gender'] === 'Male' ? 'Castración' : ($pet['species'] === 'Canino' ? 'Ovariohisterectomía' : 'Esterilización');
        $elective[$pet['name']] = [$type, $randDate($earliest, $latest)->toDateString(), $pick([
            'Cirugía electiva sin complicaciones. Alta el mismo día.',
            'Procedimiento de rutina bajo anestesia inhalatoria. Recuperación sin incidentes.',
            'Cirugía electiva. Se indica collar isabelino y control de la herida a los 10 días.',
        ])];
    }

    $consultPlan = [];  // cada una: pet, date(CarbonImmutable), vet, case-key, ...
    $surgeryPlan = [];  // pet, date, type, obs
    $testPlan = [];     // pet, date, lab, vet
    $usedCase = [];

    foreach ($elective as $name => [$type, $date, $obs]) {
        $i = $byName[$name];
        $d = CarbonImmutable::parse($date);
        $surgeryPlan[] = ['pet' => $i, 'date' => $d, 'type' => $type, 'obs' => $obs, 'vet' => $pets[$i]['vet']];
        if (in_array($type, ['Castración', 'Ovariohisterectomía', 'Esterilización'], true) && $d->lte($today)) {
            $pets[$i]['changes'][$d->toDateString()] = $stateAfter($pets[$i], $type);
        }
        if (in_array($type, ['Ovariohisterectomía', 'Esterilización'], true)) {
            $pets[$i]['electiveSpay'] = true; // no puede tener también una OVH de urgencia (piómetra)
        }
        if ($chance(50)) { // estudios prequirúrgicos
            foreach (['hemo_normal', 'bioq_normal'] as $lab) {
                $preop = $d->subDays(mt_rand(1, 4));
                $preop = $preop->gt($today) ? $today->subDays(mt_rand(0, 3)) : $preop;
                $testPlan[] = ['pet' => $i, 'date' => $preop, 'lab' => $lab, 'vet' => $pets[$i]['vet']];
            }
        }
        $pets[$i]['events'][] = $d;
    }
    // el consultorio también ve a esos pacientes antes de operar
    $presurgeryConsult = [];
    foreach ($elective as $name => [$type, $date]) {
        $presurgeryConsult[] = [$byName[$name], CarbonImmutable::parse($date)->subDays(mt_rand(3, 9))];
    }

    // ----------------------------------------------- consultas (cronológico)
    $monthTargets = [2 => 38, 3 => 44, 4 => 50, 5 => 55, 6 => 60, 7 => 64, 8 => 68, 9 => 72, 10 => 53];
    $aiAdoption = [2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 25, 9 => 50, 10 => 70];

    $slots = [];
    foreach ($monthTargets as $m => $n) {
        $from = CarbonImmutable::create(2026, $m, 1);
        $from = $from->lt($start) ? $start : $from;
        $to = CarbonImmutable::create(2026, $m, 1)->endOfMonth()->startOfDay();
        $to = $to->gt($today) ? $today : $to;
        for ($k = 0; $k < $n; $k++) {
            $slots[] = $randDate($from, $to);
        }
    }
    usort($slots, fn ($a, $b) => $a->getTimestamp() <=> $b->getTimestamp());

    $makeConsult = function (int $petIdx, CarbonImmutable $date, string $key, ?User $vet, bool $isControl = false) use (&$pets, $cases, $caseText, $chance, $pick, $atTime, $aiVets, $aiAdoption, $aiSince, $addenda, &$testPlan, &$surgeryPlan, &$reproAt) {
        $pet = $pets[$petIdx];
        $case = $cases[$key];
        $dd = mt_rand(2, 6);
        $an = $caseText($case['an'], $pet, $dd);
        $dx = $caseText($case['dx'], $pet, $dd);
        $tx = $caseText($case['tx'], $pet, $dd);
        $ob = isset($case['ob']) ? $caseText($case['ob'], $pet, $dd) : null;
        $when = $atTime($date);

        $row = [
            'pet' => $petIdx, 'date' => $date, 'when' => $when, 'vet' => $vet,
            'an' => $an, 'dx' => $dx, 'tx' => $tx, 'ob' => $ob, 'ai' => null, 'case' => $key,
        ];

        $aiVet = $vet !== null && in_array($vet->id, array_map(fn ($u) => $u->id, $aiVets), true);
        if (! $isControl && $aiVet && $date->gte($aiSince) && $chance(min(95, $aiAdoption[$date->month] + (in_array($case['urg'], ['alta', 'emergencia'], true) ? 15 : 0)))) {
            $aiDx = 'Diagnóstico presuntivo: '.lcfirst($dx).' Confirmar con el examen físico y estudios complementarios.';
            $aiTx = $tx.' Reevaluar en 48 a 72 hs si no hay mejoría clínica.';
            $urgency = $case['urg'];
            if ($chance(55)) { // usada tal cual
                $row['dx'] = $aiDx;
                $row['tx'] = $aiTx;
            } elseif ($chance(70)) { // editada: suma una nota del veterinario
                $row['dx'] = $aiDx;
                $row['tx'] = $aiTx.' '.$pick($addenda);
            } else { // el veterinario mantiene su propio criterio
                $row['dx'] = $dx;
                $row['tx'] = $tx;
            }
            $row['ai'] = [
                'dx' => $aiDx, 'tx' => $aiTx, 'urg' => $urgency,
                'at' => $when->subMinutes(mt_rand(2, 15)),
                'in' => mt_rand(450, 900), 'out' => mt_rand(250, 500),
            ];
        }

        return $row;
    };

    $consultPlan = [];
    $attempts = 0;
    foreach ($slots as $date) {
        $done = false;
        for ($try = 0; $try < 60 && ! $done; $try++) {
            $attempts++;
            $key = $wpick($cases, fn ($c) => $c['w']);
            $case = $cases[$key];
            if (isset($case['months']) && ! in_array($date->month, $case['months'], true)) {
                continue;
            }
            $elig = [];
            foreach ($pets as $i => $pet) {
                $age = $ageDays($pet, $date);
                if ($age < 45) {
                    continue;
                }
                if ($case['sp'] && $pet['species'] !== $case['sp']) {
                    continue;
                }
                $need = $case['age'] ?? null;
                if ($need === 'puppy' && $age > 365) {
                    continue;
                }
                if ($need === 'adult' && $age < 365) {
                    continue;
                }
                if ($need === 'senior' && $age < 8 * 365) {
                    continue;
                }
                if (isset($case['sex']) && $pet['gender'] !== $case['sex']) {
                    continue;
                }
                if (! empty($case['intact']) && (! empty($pet['electiveSpay']) || $reproAt($pet, $date) !== 'Normal')) {
                    continue;
                }
                if (isset($usedCase[$i.'|'.$key])) {
                    continue;
                }
                $elig[] = $i;
            }
            if ($elig === []) {
                continue;
            }
            $i = $pick($elig);
            $usedCase[$i.'|'.$key] = true;
            $vet = $pick($vetList);
            $row = $makeConsult($i, $date, $key, $vet);
            $consultPlan[] = $row;
            $pets[$i]['events'][] = $date;
            $done = true;

            // estudios y cirugías vinculados al caso
            foreach ($case['tests'] ?? [] as $lab => $pct) {
                if ($chance((int) round($pct * 0.6))) {
                    $testPlan[] = ['pet' => $i, 'date' => $date->addDays($chance(30) && $date->lt($today) ? 1 : 0), 'lab' => $lab, 'vet' => $vet];
                }
            }
            if (isset($case['surg'])) {
                $surgeryPlan[] = ['pet' => $i, 'date' => $date->lt($today) ? $date->addDay() : $date, 'type' => $case['surg'][0], 'obs' => $case['surg'][1], 'vet' => $vet];
                if ($case['surg'][0] === 'Ovariohisterectomía') {
                    $pets[$i]['changes'][($date->lt($today) ? $date->addDay() : $date)->toDateString()] = 'Sterilized';
                }
            }
            // control posterior
            if (! empty($case['ctrl']) && $chance(30)) {
                $ctrlDate = $workday($date->addDays(mt_rand(7, 12)));
                if ($ctrlDate->lte($today)) {
                    $ctrl = $makeConsult($i, $ctrlDate, $key, $vet, true);
                    $ctrl['an'] = "Control posterior a la consulta del {$fmt($date)}. El propietario refiere mejoría; {$pets[$i]['name']} come y se comporta con normalidad.";
                    $ctrl['dx'] = 'Evolución favorable. Alta médica / continuar con el plan indicado.';
                    $ctrl['tx'] = 'Completar el tratamiento prescrito. Control ante cualquier recidiva.';
                    $ctrl['ob'] = null;
                    $consultPlan[] = $ctrl;
                    $pets[$i]['events'][] = $ctrlDate;
                }
            }
        }
    }
    // consultas previas a las cirugías electivas
    foreach ($presurgeryConsult as [$i, $d]) {
        if ($d->lt($start) || $d->gt($today)) {
            continue;
        }
        $row = $makeConsult($i, $d, 'chequeo', $pets[$i]['vet']);
        $row['an'] = 'Control prequirúrgico. Se explica el procedimiento al propietario y se solicitan estudios previos.';
        $row['dx'] = 'Paciente apto para cirugía electiva.';
        $row['tx'] = 'Ayuno sólido de 12 hs y de agua de 4 hs previo a la cirugía. Se agenda fecha.';
        $row['ob'] = null;
        $row['ai'] = null;
        $consultPlan[] = $row;
        $pets[$i]['events'][] = $d;
    }
    usort($consultPlan, fn ($a, $b) => $a['when']->getTimestamp() <=> $b['when']->getTimestamp());

    // ------------------------------------------------------------ vacunas
    $makers = ['Zoetis', 'MSD Animal Health', 'Boehringer Ingelheim', 'Virbac', 'Vencofarma', 'Biogénesis Bagó'];
    $vaccObs = [null, null, null, 'Sin reacciones adversas.', 'Aplicada junto con la antirrábica.', 'Paciente tranquilo durante la aplicación.', 'Se recuerda al propietario la fecha del próximo refuerzo.'];
    $vaccPlan = [];

    $addVaccSeries = function (int $petIdx, string $vaccine, array $plannedDates, CarbonImmutable $lastNext) use (&$vaccPlan, &$pets, $start, $today, $pick, $makers, $vaccObs) {
        usort($plannedDates, fn ($a, $b) => $a->getTimestamp() <=> $b->getTimestamp());
        $recorded = array_values(array_filter($plannedDates, fn ($d) => $d->gte($start) && $d->lte($today)));
        foreach ($recorded as $k => $date) {
            $isLast = $k === count($recorded) - 1;
            $following = null;
            foreach ($plannedDates as $pd) {
                if ($pd->gt($date)) {
                    $following = $pd;
                    break;
                }
            }
            $next = null;
            if ($isLast) {
                $next = $following ?? $lastNext;
            }
            $vaccPlan[] = [
                'pet' => $petIdx, 'vaccine' => $vaccine, 'date' => $date, 'next' => $next,
                'batch' => sprintf('%s26%02dPY%s', $pick(['L', 'R', 'V']), $date->month, chr(65 + mt_rand(0, 3))),
                'maker' => $pick($makers), 'obs' => $pick($vaccObs),
                'vet' => $pets[$petIdx]['vet'],
            ];
            $pets[$petIdx]['events'][] = $date;
        }
    };

    foreach ($pets as $i => $pet) {
        $birth = CarbonImmutable::parse($pet['birth']);
        $isDog = $pet['species'] === 'Canino';
        $young = $ageDays($pet, $start) < 150; // cachorro/gatito: serie de primovacunación
        $main = $isDog ? 'Séptuple' : 'Triple Felina';

        if ($young) {
            $doses = $isDog ? [45, 66, 87] : [60, 81];
            $planned = array_map(fn ($n) => $birth->addDays($n), $doses);
            $addVaccSeries($i, $main, $planned, end($planned)->addYear());
            $addVaccSeries($i, 'Antirrábica', [$birth->addDays(120)], $birth->addDays(120)->addYear());
        } elseif ($chance(75)) {
            $date = $randDate($start, $today);
            $addVaccSeries($i, $main, [$date], $date->addYear());
            $rabies = $chance(85) ? $date : $workday($date->addDays(mt_rand(-7, 7)));
            $rabies = $rabies->gt($today) ? $date : $rabies;
            $addVaccSeries($i, 'Antirrábica', [$rabies], $rabies->addYear());
        }

        if ($isDog && ! $young && $chance(40)) { // tos de las perreras: refuerzo semestral
            $date = $randDate(CarbonImmutable::create(2026, 4, 6), CarbonImmutable::create(2026, 5, 8));
            $addVaccSeries($i, 'Tos de las Perreras', [$date], $date->addMonths(6));
        }
        if (! $isDog && ! $young && $chance(40)) { // leucemia felina: 2 dosis + refuerzo anual
            $d1 = $randDate(CarbonImmutable::create(2026, 3, 2), CarbonImmutable::create(2026, 6, 15));
            $d2 = $workday($d1->addDays(28));
            $addVaccSeries($i, 'Leucemia Felina', [$d1, $d2], $d2->addYear());
        }
    }
    usort($vaccPlan, fn ($a, $b) => $a['date']->getTimestamp() <=> $b['date']->getTimestamp());

    // ------------------------------------------ top-up de estudios pendientes
    $recentCons = array_values(array_filter($consultPlan, fn ($c) => $c['date']->gte($today->subDays(9))));
    $isPending = fn (array $t) => $t['date']->gte($today->subDays(9)) && $t['date']->lte($today);
    $pending = count(array_filter($testPlan, $isPending));
    foreach ($recentCons as $c) {
        if ($pending >= 10) {
            break;
        }
        $testPlan[] = ['pet' => $c['pet'], 'date' => $c['date'], 'lab' => $pick(['hemo_normal', 'bioq_normal', 'coprol']), 'vet' => $c['vet']];
        $pending++;
    }
    usort($testPlan, fn ($a, $b) => $a['date']->getTimestamp() <=> $b['date']->getTimestamp());
    usort($surgeryPlan, fn ($a, $b) => $a['date']->getTimestamp() <=> $b['date']->getTimestamp());

    // ------------------------------------------------------------------- PDF
    $makePdf = function (string $title, array $lines): string {
        $enc = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'));
        $content = 'BT /F1 14 Tf 50 790 Td ('.$enc($title).") Tj ET\n";
        $y = 760;
        foreach ($lines as $line) {
            foreach (explode("\n", wordwrap($line, 95, "\n", true)) as $part) {
                $content .= "BT /F1 10 Tf 50 {$y} Td (".$enc($part).") Tj ET\n";
                $y -= 15;
            }
        }
        $objs = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $n => $body) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    };

    // ---------------------------------------------------------------- persistir
    $counts = ['propietarios' => 0, 'mascotas' => 0, 'consultas' => 0, 'consultas_ia' => 0, 'vacunas' => 0, 'cirugias' => 0, 'examenes' => 0, 'examenes_sin_resultado' => 0];
    $urgencies = [];
    $aiStatus = ['usada_tal_cual' => 0, 'editada' => 0];
    $written = [];

    try {
        $ownerFirstEvent = [];
        foreach ($pets as $i => $pet) {
            $events = $pet['events'];
            $first = $events === [] ? $randDate(CarbonImmutable::parse($pet['birth'])->max($start), $start->addMonths(2)) : collect($events)->sortBy(fn ($d) => $d->getTimestamp())->first();
            $pets[$i]['first'] = $first;
            $o = $pet['ownerIdx'];
            if (! isset($ownerFirstEvent[$o]) || $first->lt($ownerFirstEvent[$o])) {
                $ownerFirstEvent[$o] = $first;
            }
        }

        $ownerModels = [];
        $usedCi = [];
        foreach ($ownerRows as $o => [$first, $last, $gender, $cityKey, $address]) {
            $city = City::where('department_id', $central->id)->where('name', 'like', '%'.$cityKey.'%')->first() ?? $areguaCity;
            $nb = Neighborhood::where('city_id', $city->id)->orderBy('id')->first() ?? $fallbackNeighborhood;
            do {
                $ci = mt_rand(2400000, 6900000);
            } while (isset($usedCi[$ci]));
            $usedCi[$ci] = true;
            $at = $atTime($ownerFirstEvent[$o] ?? $start);
            $ownerModels[$o] = Owner::unguarded(fn () => Owner::create([
                'ci' => $ci, 'first_name' => $first, 'last_name' => $last, 'gender' => $gender,
                'email' => Str::slug($first.' '.$last, '.').$demoDomain,
                'phone' => $pick(['0961', '0971', '0972', '0973', '0981', '0982', '0983', '0984', '0985', '0991', '0992', '0994']).mt_rand(100000, 999999),
                'department_id' => $central->id, 'city_id' => $city->id, 'neighborhood_id' => $nb->id,
                'address' => $address, 'created_at' => $at, 'updated_at' => $at,
            ]));
            $counts['propietarios']++;
        }

        $petModels = [];
        foreach ($pets as $i => $pet) {
            $final = $pet['repro0'];
            $changes = $pet['changes'];
            ksort($changes);
            foreach ($changes as $s) {
                $final = $s;
            }
            $at = $atTime($pet['first']);
            $petModels[$i] = Pet::unguarded(fn () => Pet::create([
                'name' => $pet['name'], 'species' => $pet['species'], 'breed' => $pet['breed'], 'gender' => $pet['gender'],
                'birthdate' => $pet['birth'], 'size' => $pet['size'], 'weight' => $pet['weight'], 'fur' => $pet['fur'],
                'reproduction' => $final, 'active' => true, 'image' => '',
                'owner_id' => $ownerModels[$pet['ownerIdx']]->id, 'user_id' => $pet['vet']->id,
                'created_at' => $at, 'updated_at' => $at,
            ]));
            $counts['mascotas']++;
        }

        foreach ($consultPlan as $c) {
            $attrs = [
                'anamnesis' => $c['an'], 'diagnosis' => $c['dx'], 'treatment' => $c['tx'], 'observation' => $c['ob'],
                'consultation_date' => $c['date']->toDateString(), 'pet_id' => $petModels[$c['pet']]->id, 'user_id' => $c['vet']->id,
                'created_at' => $c['when'], 'updated_at' => $c['when'],
            ];
            if ($c['ai']) {
                $attrs += [
                    'ai_diagnosis_suggestion' => $c['ai']['dx'], 'ai_treatment_suggestion' => $c['ai']['tx'],
                    'ai_urgency' => $c['ai']['urg'], 'ai_suggested_at' => $c['ai']['at'],
                    'ai_input_tokens' => $c['ai']['in'], 'ai_output_tokens' => $c['ai']['out'],
                ];
            }
            $model = Consultation::unguarded(fn () => Consultation::create($attrs));
            $counts['consultas']++;
            if ($c['ai']) {
                $counts['consultas_ia']++;
                $urgencies[$c['ai']['urg']] = ($urgencies[$c['ai']['urg']] ?? 0) + 1;
                $aiStatus[$model->aiUsageStatus()->value === 'UsedAsIs' ? 'usada_tal_cual' : 'editada']++;
            }
        }

        foreach ($vaccPlan as $v) {
            $at = $atTime($v['date']);
            $reminder = $v['next'] === null ? $at : ($v['next']->subDays(3)->gt($today) ? $v['next']->subDays(3) : $today)->startOfDay();
            Vaccination::unguarded(fn () => Vaccination::create([
                'vaccine' => $v['vaccine'], 'application_date' => $v['date']->toDateString(),
                'next_application' => $v['next']?->toDateString(), 'batch' => $v['batch'], 'manufacturer' => $v['maker'],
                'observation' => $v['obs'], 'pet_id' => $petModels[$v['pet']]->id, 'user_id' => $v['vet']->id,
                'reminder_sent_at' => $reminder, 'created_at' => $at, 'updated_at' => $at,
            ]));
            $counts['vacunas']++;
        }

        foreach ($surgeryPlan as $s) {
            $at = $atTime($s['date']->lte($today) ? $s['date'] : $today->subDays(mt_rand(3, 10)));
            Surgery::unguarded(fn () => Surgery::create([
                'date' => $s['date']->toDateString(), 'type' => $s['type'], 'observation' => $s['obs'],
                'pet_id' => $petModels[$s['pet']]->id, 'user_id' => $s['vet']->id, 'created_at' => $at, 'updated_at' => $at,
            ]));
            $counts['cirugias']++;
        }

        $n = 0;
        foreach ($testPlan as $t) {
            [$type, $title, $lines, $summary] = $labs[$t['lab']];
            $pet = $pets[$t['pet']];
            $owner = $ownerRows[$pet['ownerIdx']];
            $at = $atTime($t['date']);
            $pendingTest = $isPending($t);
            $result = null;
            $obs = $summary;
            if ($pendingTest) {
                $obs = "{$title}: muestra enviada al laboratorio, resultado pendiente.";
            } else {
                $n++;
                $path = sprintf('%s/informe-%s-%03d.pdf', $pdfDir, Str::slug($t['lab']), $n);
                $head = ['Clínica Veterinaria Mbopivet - Areguá', "Paciente: {$pet['name']} ({$pet['species']}, {$pet['breed']})", "Propietario: {$owner[0]} {$owner[1]}", "Fecha: {$fmt($t['date'])}", "Solicitó: {$t['vet']->name}", ''];
                if (! $dryRun) {
                    Storage::disk($pdfDisk)->put($path, $makePdf($title, array_merge($head, $lines, ['', 'Informe de ejemplo (datos demo).'])));
                    $written[] = $path;
                }
                $result = [$path];
            }
            Test::unguarded(fn () => Test::create([
                'date' => $t['date']->toDateString(), 'type' => $type, 'result' => $result, 'observation' => $obs,
                'pet_id' => $petModels[$t['pet']]->id, 'user_id' => $t['vet']->id, 'created_at' => $at, 'updated_at' => $at,
            ]));
            $counts['examenes']++;
            $counts['examenes_sin_resultado'] += $pendingTest ? 1 : 0;
        }

        $dryRun ? DB::rollBack() : DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        foreach ($written as $path) {
            Storage::disk($pdfDisk)->delete($path);
        }
        throw $e;
    }

    $perMonth = [];
    foreach ($consultPlan as $c) {
        $perMonth[$c['date']->format('Y-m')] = ($perMonth[$c['date']->format('Y-m')] ?? 0) + 1;
    }
    ksort($perMonth);

    return [
        'modo' => $dryRun ? 'DRY-RUN (revertido, no se escribió nada)' : 'ESCRITO',
        'veterinarios_usados' => collect($vetList)->pluck('name')->all(),
        'veterinarios_creados' => $createdVets,
        'veterinarios_con_permiso_ia' => count($aiVets),
        'registros' => $counts,
        'consultas_por_mes' => $perMonth,
        'ia_urgencias' => $urgencies,
        'ia_uso' => $aiStatus,
        'barrio_fallback_usado_en_areguá' => Neighborhood::where('city_id', $areguaCity->id)->doesntExist(),
    ];
};
