<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'vaccinations_pet_vaccine_application_index';

    /**
     * Acelera el cálculo de «vigente / renovada» (subconsulta por mascota + vacuna + fecha).
     */
    public function up(): void
    {
        if (Schema::hasIndex('vaccinations', self::INDEX)) {
            return;
        }

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->index(['pet_id', 'vaccine', 'application_date'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('vaccinations', self::INDEX)) {
            return;
        }

        Schema::table('vaccinations', function (Blueprint $table) {
            // MySQL descarta el índice propio de la clave foránea al existir uno compuesto que empieza por
            // pet_id y luego no deja quitar el compuesto: se vuelve a crear el de la FK antes de quitarlo.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && ! Schema::hasIndex('vaccinations', ['pet_id'])) {
                $table->index('pet_id', 'vaccinations_pet_id_foreign');
            }
        });

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
