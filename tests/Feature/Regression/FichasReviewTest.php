<?php

use App\Filament\Resources\ConsultationResource\Pages\ViewConsultation;
use App\Filament\Resources\RoleResource\Pages\ViewRole;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\VaccinationResource\Pages\ViewVaccination;
use App\Models\Consultation;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('la ficha de consulta usa fechas d/m/Y, formatea peso y temperatura y deja el registro aparte', function () {
    actingAsRole('admin');
    $consulta = Consultation::factory()->create([
        'consultation_date' => '2026-10-19',
        'weight' => 28.4,
        'temperature' => 39.1,
        'created_at' => '2026-10-19 15:47:50',
    ]);

    Livewire::test(ViewConsultation::class, ['record' => $consulta->id])
        ->assertSee('19/10/2026')
        ->assertSee('19/10/2026 15:47')
        ->assertSee('28,4 kg')
        ->assertSee('39,1 °C')
        ->assertSee('Registro')
        ->assertDontSee('oct.')
        ->assertDontSee('28.40');
});

it('la ficha de vacunación se ordena en secciones y muestra «Sin refuerzo»', function () {
    actingAsRole('admin');
    $vacuna = Vaccination::factory()->create(['next_application' => null, 'batch' => 'L2401PY', 'manufacturer' => 'Zoetis']);

    Livewire::test(ViewVaccination::class, ['record' => $vacuna->id])
        ->assertSeeInOrder(['Vacuna', 'Fabricante', 'Lote', 'Fechas y estado', 'Fecha de aplicación', 'Sin refuerzo', 'Estado', 'Seguimiento', 'Veterinario', 'Recordatorio'])
        ->assertSee('L2401PY');
});

it('la ficha de usuario muestra el teléfono con enlace a WhatsApp', function () {
    actingAsRole('admin');
    $vet = User::factory()->create(['phone' => '0981123456']);
    $vet->assignRole('veterinarian');

    Livewire::test(ViewUser::class, ['record' => $vet->id])
        ->assertSee('Teléfono')
        ->assertSee('0981123456')
        ->assertSeeHtml('https://wa.me/595981123456');

    $sinTelefono = User::factory()->create(['phone' => null]);
    $sinTelefono->assignRole('assistant');

    Livewire::test(ViewUser::class, ['record' => $sinTelefono->id])->assertSee('Teléfono')->assertDontSeeHtml('wa.me');
});

it('la ficha del rol administrador avisa que tiene todos los permisos y lista sus usuarios', function () {
    $admin = actingAsRole('admin');
    $role = Role::findByName('admin');

    Livewire::test(ViewRole::class, ['record' => $role->id])
        ->assertSee('tiene todos los permisos')
        ->assertSee('Usuarios con este rol')
        ->assertSee($admin->name)
        ->assertDontSee('Seleccionar todos');
});

it('la ficha de un rol común sigue mostrando sus permisos', function () {
    actingAsRole('admin');
    $role = Role::findByName('veterinarian');

    Livewire::test(ViewRole::class, ['record' => $role->id])
        ->assertDontSee('tiene todos los permisos')
        ->assertSee('Mascota');
});
