<?php

/**
 * Personal demo para la clínica Mbopivet: un tercer veterinario y dos asistentes.
 * Se corre DESPUÉS de database/demo-2026.php (necesita los propietarios @example.com).
 *
 *   php artisan tinker
 *   >>> $staff = require base_path('database/demo-staff-2026.php');
 *   >>> $staff();                                 // dry-run (default)
 *   >>> $staff(dryRun: false);                    // crea los usuarios y reparte autoría; muestra las contraseñas UNA vez
 *   >>> $staff(dryRun: false, login: false);      // usuarios sin acceso (contraseña desconocida)
 *   >>> $staff(cleanup: true, dryRun: false);     // borra los 3 usuarios, devolviendo sus registros a otro veterinario
 *
 * Qué reparte (solo registros de propietarios demo @example.com):
 *  - El veterinario nuevo toma ~35 % de las consultas, vacunas, cirugías y exámenes
 *    posteriores a su ingreso (15/06/2026), incluidas consultas con IA.
 *  - Las asistentes figuran como quien registró ~50 % de las mascotas dadas de alta
 *    después de su ingreso (el rol assistant puede crear mascotas, no registros clínicos).
 *  - La selección es determinística (crc32), sin azar.
 */

use App\Models\City;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return function (bool $dryRun = true, bool $login = true, bool $cleanup = false): array {
    $demoDomain = '@example.com';

    // [nombre, email, rol, ingreso, % de mascotas (asistentes) / % de registros (veterinario)]
    $staff = [
        ['Dr. Sebastián Ayala', 'sebastian.ayala@mbopivet.com.py', 'veterinarian', '2026-06-15', 35],
        ['Lucía Romero', 'lucia.romero@mbopivet.com.py', 'assistant', '2026-02-02', 50],
        ['Daniel Cabrera', 'daniel.cabrera@mbopivet.com.py', 'assistant', '2026-07-01', 50],
    ];
    $emails = array_column($staff, 1);

    $demoPetIds = fn () => Pet::whereHas('owner', fn ($q) => $q->where('email', 'like', '%'.$demoDomain))->pluck('id');
    $pick = fn (string $key, int $pct) => crc32($key) % 100 < $pct;

    DB::beginTransaction();
    try {
        // ---------------------------------------------------------------- cleanup
        if ($cleanup) {
            $users = User::whereIn('email', $emails)->get();
            $fallback = User::role('veterinarian')->whereNotIn('email', $emails)->orderBy('id')->first();
            if ($users->isNotEmpty() && ! $fallback) {
                DB::rollBack();

                return ['abortado' => 'No hay otro veterinario al que devolver los registros.'];
            }

            $moved = [];
            foreach (['pets' => Pet::class, 'consultations' => Consultation::class, 'vaccinations' => Vaccination::class, 'surgeries' => Surgery::class, 'tests' => Test::class] as $name => $model) {
                $moved[$name] = $model::whereIn('user_id', $users->modelKeys())->update(['user_id' => $fallback?->id]);
            }
            User::whereKey($users->modelKeys())->delete();
            $dryRun ? DB::rollBack() : DB::commit();

            return ['modo' => $dryRun ? 'DRY-RUN cleanup (no se borra nada)' : 'CLEANUP', 'usuarios' => $users->count(), 'registros_devueltos_a' => $fallback?->email, 'registros' => $moved];
        }

        // ------------------------------------------------------------ precondiciones
        $petIds = $demoPetIds();
        if ($petIds->isEmpty()) {
            DB::rollBack();

            return ['abortado' => 'No hay mascotas demo (@example.com). Corré primero database/demo-2026.php.'];
        }
        if (User::whereIn('email', $emails)->exists()) {
            DB::rollBack();

            return ['abortado' => 'Ya existe alguno de los usuarios demo. Corré primero $staff(cleanup: true, dryRun: false).'];
        }

        $central = Department::whereRaw('LOWER(name) LIKE ?', ['%central%'])->first();
        $city = City::where('department_id', $central?->id)->where('name', 'like', '%Aregu%')->first();
        $neighborhood = Neighborhood::whereHas('city', fn ($q) => $q->where('department_id', $central?->id))->orderBy('id')->first();
        if (! $central || ! $city || ! $neighborhood) {
            DB::rollBack();

            return ['abortado' => 'Faltan regiones (Central / Areguá). Corré RegionsSeeder primero.'];
        }

        // ----------------------------------------------------------------- usuarios
        $credentials = [];
        $created = [];
        foreach ($staff as [$name, $email, $role, $joined]) {
            $password = Str::password(16);
            $at = CarbonImmutable::parse($joined)->setTime(9, 0);
            $user = User::unguarded(fn () => User::create([
                'name' => $name, 'email' => $email, 'password' => Hash::make($password),
                'email_verified_at' => $at, 'department_id' => $central->id, 'city_id' => $city->id,
                'neighborhood_id' => $neighborhood->id, 'address' => 'Areguá',
                'created_at' => $at, 'updated_at' => $at,
            ]));
            $user->syncRoles([$role]);
            $created[$email] = $user;
            if ($login) {
                $credentials[$email] = $password;
            }
        }

        // -------------------------------------------------------- reparto de autoría
        [$vetName, $vetEmail, , $vetJoined, $vetPct] = $staff[0];
        $vet = $created[$vetEmail];
        $moved = ['consultas' => 0, 'consultas_ia' => 0, 'vacunas' => 0, 'cirugias' => 0, 'examenes' => 0, 'mascotas_lucia' => 0, 'mascotas_daniel' => 0];

        foreach (Consultation::whereIn('pet_id', $petIds)->whereDate('consultation_date', '>=', $vetJoined)->get() as $c) {
            if ($pick("consulta|{$c->id}", $vetPct)) {
                $c->forceFill(['user_id' => $vet->id])->saveQuietly();
                $moved['consultas']++;
                $moved['consultas_ia'] += $c->ai_suggested_at ? 1 : 0;
            }
        }
        foreach ([['vacunas', Vaccination::class, 'application_date'], ['cirugias', Surgery::class, 'date'], ['examenes', Test::class, 'date']] as [$key, $model, $dateColumn]) {
            foreach ($model::whereIn('pet_id', $petIds)->whereDate($dateColumn, '>=', $vetJoined)->get() as $r) {
                if ($pick("{$key}|{$r->id}", $vetPct)) {
                    $r->forceFill(['user_id' => $vet->id])->saveQuietly();
                    $moved[$key]++;
                }
            }
        }

        foreach ([[1, 'mascotas_lucia'], [2, 'mascotas_daniel']] as [$i, $key]) {
            $assistant = $created[$staff[$i][1]];
            $joined = CarbonImmutable::parse($staff[$i][3])->startOfDay();
            foreach (Pet::whereIn('id', $petIds)->where('created_at', '>=', $joined)->get() as $pet) {
                // una mascota va a una sola asistente: Lucía elige primero, Daniel toma de las que quedan
                if ($pet->user_id === $created[$staff[1][1]]->id) {
                    continue;
                }
                if ($pick("mascota|{$pet->id}|{$i}", $staff[$i][4])) {
                    $pet->forceFill(['user_id' => $assistant->id])->saveQuietly();
                    $moved[$key]++;
                }
            }
        }

        $dryRun ? DB::rollBack() : DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }

    return [
        'modo' => $dryRun ? 'DRY-RUN (revertido, no se escribió nada)' : 'ESCRITO',
        'usuarios' => array_map(fn ($s) => "{$s[0]} <{$s[1]}> ({$s[2]}, desde {$s[3]})", $staff),
        'registros_reasignados' => $moved,
        'credenciales' => $dryRun ? 'se generan al escribir' : ($login ? $credentials : 'sin acceso (contraseña desconocida)'),
    ];
};
