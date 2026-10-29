<?php

use App\Filament\Pages\ClinicSettingsPage;
use App\Filament\Resources\CityResource;
use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\ExportLogResource;
use App\Filament\Resources\NeighborhoodResource;
use App\Filament\Resources\OwnerResource;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VaccinationResource;

it('las URLs de los recursos y páginas del panel están en español', function (string $class, string $slug) {
    expect($class::getSlug())->toBe($slug);
})->with([
    [PetResource::class, 'mascotas'],
    [OwnerResource::class, 'propietarios'],
    [ConsultationResource::class, 'consultas'],
    [VaccinationResource::class, 'vacunaciones'],
    [SurgeryResource::class, 'cirugias'],
    [TestResource::class, 'pruebas'],
    [UserResource::class, 'usuarios'],
    [DepartmentResource::class, 'departamentos'],
    [CityResource::class, 'ciudades'],
    [NeighborhoodResource::class, 'barrios'],
    [RoleResource::class, 'roles'],
    [ExportLogResource::class, 'exportaciones'],
    [ClinicSettingsPage::class, 'datos-de-la-clinica'],
]);

it('las pantallas abren en la URL nueva y la vieja da 404', function () {
    actingAsRole('admin');

    $this->get('/mascotas')->assertOk();
    $this->get('/datos-de-la-clinica')->assertOk();
    $this->get('/perfil')->assertOk();
    $this->get('/pets')->assertNotFound();
    $this->get('/clinic-settings')->assertNotFound();
    $this->get('/profile')->assertNotFound();
    $this->get('/shield/roles')->assertNotFound();
});
