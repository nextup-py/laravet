<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reportes personalizados guardados por cada usuario (tipo de registro, columnas y filtros).
     * Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        if (Schema::hasTable('saved_reports')) {
            return;
        }

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('entity');
            $table->json('config');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
