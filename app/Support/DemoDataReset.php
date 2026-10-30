<?php

namespace App\Support;

use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoVolumeSeeder;
use Database\Seeders\OwnerSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Borra solo los datos que siembran DemoSeeder y DemoVolumeSeeder (propietarios por CI o por el
 * dominio de volumen, con sus mascotas e historial en cascada, y los usuarios demo), sin tocar lo
 * que cargó la clínica. Un usuario demo que ya atendió pacientes reales se conserva.
 */
class DemoDataReset
{
    /**
     * Cantidades que se borrarían, para pedir confirmación.
     *
     * @return array{owners: int, pets: int, users: int}
     */
    public function preview(): array
    {
        return [
            'owners' => $this->owners()->count(),
            'pets' => Pet::whereIn('owner_id', $this->owners()->select('id'))->count(),
            'users' => User::whereIn('email', DemoSeeder::STAFF_EMAILS)->count(),
        ];
    }

    /**
     * @return array{owners: int, users: int, kept_users: array<int, string>}
     */
    public function run(): array
    {
        $owners = $this->owners()->count();
        $this->owners()->delete();

        $users = 0;
        $kept = [];

        foreach (User::whereIn('email', DemoSeeder::STAFF_EMAILS)->get() as $user) {
            if ($this->hasRecords($user)) {
                $kept[] = $user->email;

                continue;
            }

            $user->delete();
            $users++;
        }

        foreach (Storage::disk('test-results')->files('resultados') as $file) {
            if (str_starts_with(basename($file), 'demo-')) {
                Storage::disk('test-results')->delete($file);
            }
        }

        return ['owners' => $owners, 'users' => $users, 'kept_users' => $kept];
    }

    private function owners()
    {
        return Owner::query()
            ->whereIn('ci', array_column(OwnerSeeder::OWNERS, 'ci'))
            ->orWhere('email', 'like', '%@'.DemoVolumeSeeder::EMAIL_DOMAIN);
    }

    private function hasRecords(User $user): bool
    {
        return Pet::where('user_id', $user->id)->exists()
            || Consultation::where('user_id', $user->id)->exists()
            || Vaccination::where('user_id', $user->id)->exists()
            || Surgery::where('user_id', $user->id)->exists()
            || Test::where('user_id', $user->id)->exists();
    }
}
