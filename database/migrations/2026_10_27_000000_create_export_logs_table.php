<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('export_logs')) {
            return;
        }

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            // Se conserva el nombre aunque se elimine el usuario: es un registro de auditoría.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('list');
            $table->unsignedInteger('rows');
            $table->string('format', 10);
            $table->json('columns');
            $table->boolean('includes_owner_data')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_logs');
    }
};
