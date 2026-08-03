<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->text('ai_diagnosis_suggestion')->nullable();
            $table->text('ai_treatment_suggestion')->nullable();
            $table->string('ai_urgency')->nullable();
            $table->timestamp('ai_suggested_at')->nullable();
            $table->unsignedInteger('ai_input_tokens')->nullable();
            $table->unsignedInteger('ai_output_tokens')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn([
                'ai_diagnosis_suggestion',
                'ai_treatment_suggestion',
                'ai_urgency',
                'ai_suggested_at',
                'ai_input_tokens',
                'ai_output_tokens',
            ]);
        });
    }
};
