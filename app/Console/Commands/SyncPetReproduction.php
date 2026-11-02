<?php

namespace App\Console\Commands;

use App\Models\Surgery;
use App\Support\PetReproductionSync;
use Illuminate\Console\Command;

class SyncPetReproduction extends Command
{
    protected $signature = 'pets:sync-reproduction';

    protected $description = 'Marca como castradas o esterilizadas a las mascotas cuya cirugía programada llegó (hoy o ayer).';

    public function handle(): int
    {
        // Ventana corta a propósito: una corrección manual posterior no debe deshacerse todos los días.
        $changed = Surgery::query()
            ->with('pet')
            ->whereBetween('date', [today()->subDay()->toDateString(), today()->toDateString()])
            ->get()
            ->filter(fn (Surgery $surgery): bool => PetReproductionSync::apply($surgery))
            ->count();

        $this->info("Mascotas actualizadas: {$changed}.");

        return self::SUCCESS;
    }
}
