<?php

use App\Models\User;
use Database\Seeders\RegionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)
    ->beforeEach(function () {
        // Prerequisito de casi todo Feature test: roles (assignRole()) y
        // datos geográficos reales (Owner/User exigen department_id/city_id/
        // neighborhood_id en el form).
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RegionsSeeder::class);
    })
    ->in('Feature');

/**
 * Crea un usuario con el rol indicado y lo autentica en el guard por defecto
 * (el mismo que usa el panel de Filament, no hay guard custom).
 */
function actingAsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user);

    return $user;
}
