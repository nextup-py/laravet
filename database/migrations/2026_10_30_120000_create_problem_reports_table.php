<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('problem_reports')) {
            return;
        }

        Schema::create('problem_reports', function (Blueprint $table) {
            $table->id();
            // Se conserva el nombre aunque se elimine el usuario: el reporte sigue siendo útil para soporte.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('type', 20);
            $table->text('description');
            $table->string('screen', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('app_version', 100)->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_reports');
    }
};
