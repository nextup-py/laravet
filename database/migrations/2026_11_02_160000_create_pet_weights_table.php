<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de pesajes de cada mascota. `pets.weight` queda como el peso actual (el del pesaje más
     * reciente); un pesaje con `consultation_id` es el peso anotado en esa consulta.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pet_weights')) {
            Schema::create('pet_weights', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
                $table->decimal('weight', 5, 2);
                $table->date('weighed_at');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('consultation_id')->nullable()->unique()->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->index(['pet_id', 'weighed_at']);
            });
        }

        // Idempotente: un pesaje inicial por mascota que todavía no tenga ninguno, con el peso actual.
        DB::table('pets')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('pet_weights')->whereColumn('pet_weights.pet_id', 'pets.id'))
            ->orderBy('id')
            ->select(['id', 'weight', 'user_id', 'created_at'])
            ->chunkById(500, function ($pets): void {
                DB::table('pet_weights')->insert($pets->map(fn (object $pet): array => [
                    'pet_id' => $pet->id,
                    'weight' => $pet->weight,
                    'weighed_at' => substr((string) ($pet->created_at ?? now()), 0, 10),
                    'user_id' => $pet->user_id,
                    'consultation_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_weights');
    }
};
