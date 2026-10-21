<?php

/**
 * Complemento de database/demo-2026.php (no hace falta rehacer la carga):
 *  1. Mueve a 8-17 hs las horas de creación de los registros demo (la clínica atiende lun-sáb 8 a 17).
 *  2. Agrega 7 mascotas exóticas (4 roedores/conejos y 3 aves) a propietarios demo, con consultas
 *     (6 con IA), vacunas de conejos, 2 cirugías y 1 examen pendiente.
 *
 *   $x = require base_path('database/demo-exoticos-2026.php');
 *   $x();                // dry-run (default)
 *   $x(dryRun: false);   // escribe
 *
 * Los propietarios exóticos siguen siendo @example.com: el cleanup de demo-2026.php los borra con todo.
 */

use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

return function (bool $dryRun = true): array {
    $owners = Owner::where('email', 'like', '%@example.com')->get()->keyBy('first_name');
    if ($owners->isEmpty()) {
        return ['abortado' => 'No hay propietarios demo (@example.com). Corré primero demo-2026.php.'];
    }
    if (Pet::where('name', 'Copito')->whereIn('owner_id', $owners->pluck('id'))->exists()) {
        return ['abortado' => 'Las mascotas exóticas ya fueron cargadas.'];
    }
    $vets = User::whereIn('email', ['maria.gonzalez@mbopivet.com.py', 'carlos.gimenez@mbopivet.com.py'])->orderBy('email')->get()->values();
    if ($vets->count() !== 2 || $vets->contains(fn (User $v) => ! $v->hasPermissionTo('use_ai_diagnostics'))) {
        return ['abortado' => 'Faltan María González / Carlos Giménez o no tienen permiso de IA.'];
    }
    $missing = collect(['Cynthia', 'Elvira', 'Fabiola', 'Rubén', 'Víctor', 'Karina', 'Noemí'])->reject(fn ($n) => $owners->has($n));
    if ($missing->isNotEmpty()) {
        return ['abortado' => 'Faltan propietarios demo: '.$missing->implode(', ')];
    }

    $moved = [];
    DB::beginTransaction();
    try {
        // ------------------------------------------------ 1) horario 8 a 17 hs
        $petIds = Pet::whereIn('owner_id', $owners->pluck('id'))->pluck('id');
        foreach ([['consultations', 'pet_id'], ['vaccinations', 'pet_id'], ['surgeries', 'pet_id'], ['tests', 'pet_id'], ['pets', 'id']] as [$table, $col]) {
            $moved[$table] = 0;
            foreach (DB::table($table)->whereIn($col, $petIds)->whereRaw('HOUR(created_at) > 16')->get(['id', 'created_at']) as $r) {
                $new = CarbonImmutable::parse($r->created_at)->setTime(8 + $r->id % 9, ($r->id * 13) % 60, ($r->id * 7) % 60);
                DB::table($table)->where('id', $r->id)->update(['created_at' => $new, 'updated_at' => $new]);
                if ($table === 'consultations') {
                    DB::table($table)->where('id', $r->id)->whereNotNull('ai_suggested_at')->update(['ai_suggested_at' => $new->subMinutes(2 + $r->id % 14)]);
                }
                $moved[$table]++;
            }
        }
        $moved['propietarios'] = 0;
        foreach (DB::table('owners')->where('email', 'like', '%@example.com')->whereRaw('HOUR(created_at) > 16')->get(['id', 'created_at']) as $r) {
            $new = CarbonImmutable::parse($r->created_at)->setTime(8 + $r->id % 9, ($r->id * 13) % 60, ($r->id * 7) % 60);
            DB::table('owners')->where('id', $r->id)->update(['created_at' => $new, 'updated_at' => $new]);
            $moved['propietarios']++;
        }

        // ------------------------------------------------ 2) mascotas exóticas
        // [nombre, propietario, especie, raza, género, nacimiento, tamaño, peso, pelaje, reproducción final]
        $petRows = [
            ['Copito', 'Cynthia', 'Roedor', 'Conejo Mini Lop', 'Male', '2023-05-10', 'Small', 1.8, 'Corto', 'Castrated'],
            ['Nieve', 'Elvira', 'Roedor', 'Conejo Cabeza de León', 'Female', '2024-02-20', 'Small', 1.5, 'Largo', 'Sterilized'],
            ['Pipo', 'Fabiola', 'Roedor', 'Cobayo', 'Male', '2024-08-05', 'Tiny', 0.9, 'Corto', 'Normal'],
            ['Canelita', 'Rubén', 'Roedor', 'Hámster Sirio', 'Female', '2025-09-01', 'Tiny', 0.12, 'Corto', 'Normal'],
            ['Lorenzo', 'Víctor', 'Ave', 'Loro Hablador (Amazona)', 'Male', '2016-04-12', 'Small', 0.45, 'Plumaje', 'Normal'],
            ['Kiwi', 'Karina', 'Ave', 'Cotorra', 'Female', '2022-11-03', 'Tiny', 0.09, 'Plumaje', 'Normal'],
            ['Sol', 'Noemí', 'Ave', 'Canario', 'Male', '2025-03-14', 'Tiny', 0.02, 'Plumaje', 'Normal'],
        ];

        // [mascota, fecha, veterinario (0 = Carlos, 1 = María), anamnesis, diagnóstico, tratamiento, observación, IA: [urgencia, 'asis'|'edit'] | null]
        $consults = [
            ['Copito', '2026-03-10', 1, 'Control general y consulta por vacunas. Come heno y verduras, heces normales.', 'Paciente clínicamente sano.', 'Vacunación contra mixomatosis y enfermedad hemorrágica vírica. Heno ad libitum.', 'Peso 1,8 kg.', null],
            ['Nieve', '2026-04-07', 0, 'Babea, deja caer el alimento y los incisivos se ven largos. Come menos desde hace 2 semanas.', 'Maloclusión dental con sobrecrecimiento de incisivos.', 'Recorte de incisivos bajo sedación. Dieta con heno ad libitum y verdes. Control cada 4 a 6 semanas.', 'Se revisaron también molares sin hallazgos.', null],
            ['Copito', '2026-05-12', 0, 'Control prequirúrgico para castración. El propietario consulta por el procedimiento.', 'Paciente apto para cirugía electiva.', 'Ayuno de 2 a 3 hs sin restringir el agua. Se agenda la fecha.', null, null],
            ['Canelita', '2026-06-23', 0, 'Prurito y caspa en el dorso, se rasca con frecuencia. Convive con otro animal en la casa.', 'Ácaros de la piel (Cheyletiella).', 'Selamectina spot-on en dosis única, repetir a los 14 días. Cambiar el sustrato y limpiar la jaula.', 'Se tomó muestra por cinta adhesiva.', ['baja', 'edit']],
            ['Pipo', '2026-07-14', 0, 'Cojea, no quiere moverse y las encías sangran al comer. Se alimenta solo con alimento seco.', 'Hipovitaminosis C (escorbuto).', 'Vitamina C 50 mg/kg/día por vía oral. Verduras ricas en vitamina C a diario y alimento formulado para cobayos.', 'Dolor articular a la manipulación.', ['media', 'asis']],
            ['Kiwi', '2026-08-12', 1, 'Pico sobrecrecido y uñas largas que le dificultan posarse y comer.', 'Sobrecrecimiento de pico y uñas.', 'Recorte de pico y uñas. Agregar perchas de distintos diámetros y cuerdas para desgaste.', 'Sin sangrado durante el procedimiento.', ['baja', 'edit']],
            ['Pipo', '2026-07-28', 0, 'Control posterior a la consulta del 14/07. El propietario refiere mejoría; Pipo come y se mueve con normalidad.', 'Evolución favorable. Alta médica / continuar con el plan indicado.', 'Completar el tratamiento prescrito. Control ante cualquier recidiva.', null, null],
            ['Nieve', '2026-08-19', 1, 'Control prequirúrgico para ovariohisterectomía preventiva.', 'Paciente apta para cirugía electiva.', 'Ayuno de 2 a 3 hs sin restringir el agua. Se agenda la fecha.', null, null],
            ['Nieve', '2026-09-04', 1, 'Control posterior a la OVH del 26/08. Come y defeca con normalidad.', 'Evolución favorable. Alta médica / continuar con el plan indicado.', 'Se retiran los puntos. Heno ad libitum.', null, null],
            ['Sol', '2026-09-17', 1, 'Respira con el pico abierto y mueve la cola al respirar desde hace 3 días. Plumas erizadas y menos activo.', 'Infección respiratoria (aerosaculitis) sospechada.', 'Enrofloxacina 15 mg/kg c/12 hs. Nebulizaciones. Ambiente cálido a 28 °C y reposo. Oxígeno si hay disnea.', 'Pronóstico reservado. Se maneja con el mínimo estrés posible.', ['alta', 'asis']],
            ['Copito', '2026-09-22', 0, 'Desde hace 2 días come muy poco, las heces son pequeñas y escasas y tiene el abdomen distendido. Está quieto y se muele los dientes.', 'Estasis gastrointestinal.', 'Fluidoterapia subcutánea. Procinético y analgesia (meloxicam 0,3 mg/kg). Alimentación asistida con papilla de fibra y heno ad libitum. Control a las 24 hs.', 'Hipotermia leve. Se explica al propietario la importancia del heno en la dieta.', ['alta', 'asis']],
            ['Sol', '2026-09-24', 1, 'Control posterior a la consulta del 17/09. El propietario refiere mejoría; Sol come y canta con normalidad.', 'Evolución favorable. Alta médica / continuar con el plan indicado.', 'Completar el tratamiento prescrito. Control ante cualquier recidiva.', null, null],
            ['Copito', '2026-09-30', 0, 'Control posterior a la consulta del 22/09. Come heno y defeca con normalidad.', 'Evolución favorable. Alta médica / continuar con el plan indicado.', 'Heno ad libitum. Control ante cualquier recidiva.', null, null],
            ['Lorenzo', '2026-02-24', 0, 'Se arranca las plumas del pecho y las alas desde hace 3 semanas. Pasa mucho tiempo solo.', 'Plumofagia (picaje) de probable origen conductual y ambiental.', 'Enriquecimiento ambiental y más tiempo de interacción. Dieta con pellets y verduras, menos semillas. Dormir 10 a 12 hs en oscuridad. Control en 30 días.', 'Descartar causas médicas si no mejora.', null],
            ['Lorenzo', '2026-10-19', 1, 'Heces líquidas desde hace 3 días, plumas erizadas y está decaído.', 'Enteritis parasitaria / bacteriana.', 'Fenbendazol según peso por 5 días. Probióticos. Calor ambiental. Higiene diaria de la jaula.', 'Se recomienda desinfectar bebederos y comederos.', ['media', 'asis']],
        ];

        $first = [];
        foreach ($consults as [$name, $date]) {
            $first[$name] = min($first[$name] ?? $date, $date);
        }
        $first['Copito'] = min($first['Copito'], '2026-03-10');
        $first['Nieve'] = min($first['Nieve'], '2026-04-07');

        $at = fn (string $date, int $k) => CarbonImmutable::parse($date)->setTime(9 + $k % 7, 10 + ($k * 7) % 50, ($k * 11) % 60);

        $pets = [];
        foreach ($petRows as $k => [$name, $ownerName, $species, $breed, $gender, $birth, $size, $weight, $fur, $repro]) {
            $created = $at($first[$name] ?? '2026-03-01', $k);
            $pets[$name] = Pet::unguarded(fn () => Pet::create([
                'name' => $name, 'species' => $species, 'breed' => $breed, 'gender' => $gender, 'birthdate' => $birth,
                'size' => $size, 'weight' => $weight, 'fur' => $fur, 'reproduction' => $repro, 'active' => true, 'image' => '',
                'owner_id' => $owners[$ownerName]->id, 'user_id' => $vets[$k % 2]->id, 'created_at' => $created, 'updated_at' => $created,
            ]));
        }

        $ai = 0;
        foreach ($consults as $k => [$name, $date, $vetIdx, $an, $dx, $tx, $ob, $aiInfo]) {
            $when = $at($date, $k + 3);
            $row = [
                'anamnesis' => $an, 'diagnosis' => $dx, 'treatment' => $tx, 'observation' => $ob, 'consultation_date' => $date,
                'pet_id' => $pets[$name]->id, 'user_id' => $vets[$vetIdx]->id, 'created_at' => $when, 'updated_at' => $when,
            ];
            if ($aiInfo) {
                $aiDx = 'Diagnóstico presuntivo: '.lcfirst($dx).' Confirmar con el examen físico y estudios complementarios.';
                $aiTx = $tx.' Reevaluar en 48 a 72 hs si no hay mejoría clínica.';
                $row['diagnosis'] = $aiDx;
                $row['treatment'] = $aiInfo[1] === 'asis' ? $aiTx : $aiTx.' Se ajusta la dosis según el peso actual.';
                $row += [
                    'ai_diagnosis_suggestion' => $aiDx, 'ai_treatment_suggestion' => $aiTx, 'ai_urgency' => $aiInfo[0],
                    'ai_suggested_at' => $when->subMinutes(4 + $k % 9), 'ai_input_tokens' => 480 + $k * 23, 'ai_output_tokens' => 270 + $k * 11,
                ];
                $ai++;
            }
            Consultation::unguarded(fn () => Consultation::create($row));
        }

        foreach ([['Copito', '2026-03-10'], ['Nieve', '2026-04-07']] as $k => [$name, $date]) {
            $when = $at($date, $k + 20);
            foreach (['Mixomatosis' => ['M', 'Hipra'], 'Enfermedad Hemorrágica Vírica' => ['H', 'Virbac']] as $vaccine => [$prefix, $maker]) {
                Vaccination::unguarded(fn () => Vaccination::create([
                    'vaccine' => $vaccine, 'application_date' => $date, 'next_application' => CarbonImmutable::parse($date)->addYear()->toDateString(),
                    'batch' => sprintf('%s26%sPY%s', $prefix, substr($date, 5, 2), chr(65 + $k)), 'manufacturer' => $maker,
                    'observation' => 'Aplicada junto con la otra vacuna del esquema.', 'pet_id' => $pets[$name]->id, 'user_id' => $vets[1]->id,
                    'reminder_sent_at' => $when, 'created_at' => $when, 'updated_at' => $when,
                ]));
            }
        }

        foreach ([
            ['Copito', '2026-05-20', 'Castración', 'Castración de conejo macho bajo anestesia inhalatoria. Sin complicaciones; alimentación asistida las primeras 24 hs.'],
            ['Nieve', '2026-08-26', 'Ovariohisterectomía', 'OVH preventiva en coneja (prevención de neoplasia uterina). Recuperación sin incidentes.'],
        ] as $k => [$name, $date, $type, $obs]) {
            $when = $at($date, $k + 30);
            Surgery::unguarded(fn () => Surgery::create([
                'date' => $date, 'type' => $type, 'observation' => $obs, 'pet_id' => $pets[$name]->id,
                'user_id' => $vets[$k]->id, 'created_at' => $when, 'updated_at' => $when,
            ]));
        }

        $when = $at('2026-10-19', 40);
        Test::unguarded(fn () => Test::create([
            'date' => '2026-10-19', 'type' => 'Coprología', 'result' => null,
            'observation' => 'Coprología por flotación: muestra enviada al laboratorio, resultado pendiente.',
            'pet_id' => $pets['Lorenzo']->id, 'user_id' => $vets[1]->id, 'created_at' => $when, 'updated_at' => $when,
        ]));

        $dryRun ? DB::rollBack() : DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }

    return [
        'modo' => $dryRun ? 'DRY-RUN (revertido, no se escribió nada)' : 'ESCRITO',
        'horarios_corregidos' => $moved,
        'mascotas_nuevas' => count($petRows),
        'consultas_nuevas' => count($consults),
        'consultas_ia_nuevas' => $ai,
    ];
};
