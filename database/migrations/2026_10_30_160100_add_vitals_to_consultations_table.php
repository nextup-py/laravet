<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'weight')) {
                $table->decimal('weight', 5, 2)->nullable();
            }

            if (! Schema::hasColumn('consultations', 'temperature')) {
                $table->decimal('temperature', 3, 1)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn(['weight', 'temperature']);
        });
    }
};
