<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * En Paraguay Asunción es el Distrito Capital y no parte del departamento Central. Crea el departamento
     * «Capital» y mueve la ciudad (con sus barrios) y el departamento de los propietarios y usuarios que viven
     * en ella. Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        $centralId = DB::table('departments')->where('name', 'Central')->value('id');
        $asuncion = $centralId
            ? DB::table('cities')->where('name', 'Asunción')->where('department_id', $centralId)->first()
            : null;

        if (! $asuncion) {
            return;
        }

        DB::transaction(function () use ($asuncion): void {
            $capitalId = DB::table('departments')->where('name', 'Capital')->value('id')
                ?? DB::table('departments')->insertGetId([
                    'name' => 'Capital',
                    'capital' => 'Asunción',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('cities')->where('id', $asuncion->id)->update(['department_id' => $capitalId]);
            DB::table('owners')->where('city_id', $asuncion->id)->update(['department_id' => $capitalId]);
            DB::table('users')->where('city_id', $asuncion->id)->update(['department_id' => $capitalId]);
        });
    }

    public function down(): void
    {
        $centralId = DB::table('departments')->where('name', 'Central')->value('id');
        $capitalId = DB::table('departments')->where('name', 'Capital')->value('id');
        $asuncion = $capitalId ? DB::table('cities')->where('name', 'Asunción')->where('department_id', $capitalId)->first() : null;

        if (! $centralId || ! $asuncion) {
            return;
        }

        DB::transaction(function () use ($asuncion, $centralId, $capitalId): void {
            DB::table('cities')->where('id', $asuncion->id)->update(['department_id' => $centralId]);
            DB::table('owners')->where('city_id', $asuncion->id)->update(['department_id' => $centralId]);
            DB::table('users')->where('city_id', $asuncion->id)->update(['department_id' => $centralId]);

            $inUse = DB::table('cities')->where('department_id', $capitalId)->exists()
                || DB::table('owners')->where('department_id', $capitalId)->exists()
                || DB::table('users')->where('department_id', $capitalId)->exists();

            if (! $inUse) {
                DB::table('departments')->where('id', $capitalId)->delete();
            }
        });
    }
};
