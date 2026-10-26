<?php

use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Spatie\Permission\Models\Role;

/**
 * Los PDF se abren en una pestaña nueva del navegador (Content-Disposition: inline) desde rutas
 * del panel que exigen sesión y permiso sobre el registro.
 */
it('cada documento se entrega como PDF en línea', function (string $route, Closure $record) {
    actingAsRole('admin');

    $response = test()->get(route($route, $record()));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->getContent())->toStartWith('%PDF-');
})->with([
    'historia clínica' => ['filament.admin.pdf.clinical-history', fn () => Pet::factory()->create()],
    'ficha de mascota' => ['filament.admin.pdf.pet-id-card', fn () => Pet::factory()->create()],
    'carnet de vacunación' => ['filament.admin.pdf.vaccine-card', fn () => Vaccination::factory()->create()->pet],
    'consulta' => ['filament.admin.pdf.consultation', fn () => Consultation::factory()->create()],
    'cirugía' => ['filament.admin.pdf.surgery', fn () => Surgery::factory()->create()],
    'prueba' => ['filament.admin.pdf.test', fn () => Test::factory()->create()],
]);

it('sin sesión no se entrega ningún documento', function () {
    $consultation = Consultation::factory()->create();

    test()->get(route('filament.admin.pdf.consultation', $consultation))->assertRedirect();
});

it('sin permiso sobre el registro el documento responde 403', function () {
    $role = Role::create(['name' => 'sin-permisos-clinicos', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    $pet = Pet::factory()->create();

    test()->get(route('filament.admin.pdf.pet-id-card', $pet))->assertOk();
    test()->get(route('filament.admin.pdf.clinical-history', $pet))->assertForbidden();
    test()->get(route('filament.admin.pdf.vaccine-card', $pet))->assertForbidden();
    test()->get(route('filament.admin.pdf.consultation', Consultation::factory()->create()))->assertForbidden();
    test()->get(route('filament.admin.pdf.surgery', Surgery::factory()->create()))->assertForbidden();
    test()->get(route('filament.admin.pdf.test', Test::factory()->create()))->assertForbidden();
});

it('las acciones de PDF abren una pestaña nueva y no piden confirmación', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire\Livewire::test(ViewPet::class, ['record' => $pet->getRouteKey()])
        ->assertActionHasUrl('openPetIdCard', route('filament.admin.pdf.pet-id-card', $pet))
        ->assertActionShouldOpenUrlInNewTab('openPetIdCard');
});
