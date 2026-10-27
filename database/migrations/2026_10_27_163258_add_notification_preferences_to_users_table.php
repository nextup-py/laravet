<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencias de avisos del usuario: por tipo de aviso, los canales elegidos
     * (campanita y/o correo). Nulo = valores por defecto (solo campanita).
     * Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'notification_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
