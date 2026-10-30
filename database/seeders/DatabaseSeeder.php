<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        // Los datos demo (contraseña fija) no se siembran en producción; 'staging' sirve para capacitación.
        if (app()->environment(['local', 'testing', 'staging'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
