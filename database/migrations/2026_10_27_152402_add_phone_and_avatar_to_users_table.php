<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el teléfono y la foto de perfil del usuario (ambos opcionales).
     * Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }

            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar_url']);
        });
    }
};
