<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;

/**
 * El departamento se sembró como "Coordillera" (error de tipeo en departments.json).
 * Se renombra a "Cordillera" solo si existe con el nombre antiguo y no hay ya otro
 * con el nombre correcto, para no generar duplicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Department::where('name', 'Cordillera')->exists()) {
            return;
        }

        Department::where('name', 'Coordillera')->update(['name' => 'Cordillera']);
    }

    public function down(): void
    {
        // No se revierte a propósito: el nombre anterior era un error de tipeo.
    }
};
