<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            RegionsSeeder::class,
        ]);

        $email = config('app.admin_email');

        if (empty($email)) {
            $this->command->warn('ADMIN_EMAIL no está configurado; se omite la creación del usuario admin.');

            return;
        }

        $password = Str::password(16);

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => config('app.admin_name'), 'email_verified_at' => now(), 'password' => $password]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->warn("Usuario admin creado ({$email}). Password generada: {$password} — guardala ya que no se vuelve a mostrar.");
        } else {
            $this->command->info("Usuario admin ya existía ({$email}), no se modifica la password.");
        }

        $user->syncRoles(['admin']);
    }
}
