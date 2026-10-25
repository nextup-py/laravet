<?php

use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\ConsultationResource\Pages\EditConsultation;
use App\Filament\Resources\DepartmentResource\Pages\EditDepartment;
use App\Filament\Resources\NeighborhoodResource\Pages\EditNeighborhood;
use App\Filament\Resources\OwnerResource\Pages\EditOwner;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Filament\Resources\SurgeryResource\Pages\EditSurgery;
use App\Filament\Resources\TestResource\Pages\EditTest;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\VaccinationResource\Pages\EditVaccination;
use App\Models\City;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;

it('todas las páginas de edición ofrecen volver a la ficha con «Ver»', function (string $page, Closure $record) {
    actingAsRole('admin');

    Livewire::test($page, ['record' => $record()->getRouteKey()])
        ->assertActionExists('view');
})->with([
    'consulta' => [EditConsultation::class, fn () => Consultation::factory()->create()],
    'cirugía' => [EditSurgery::class, fn () => Surgery::factory()->create()],
    'prueba' => [EditTest::class, fn () => Test::factory()->create()],
    'vacunación' => [EditVaccination::class, fn () => Vaccination::factory()->create()],
    'propietario' => [EditOwner::class, fn () => Owner::factory()->create()],
    'mascota' => [EditPet::class, fn () => Pet::factory()->create()],
    'usuario' => [EditUser::class, fn () => User::factory()->create()],
    'departamento' => [EditDepartment::class, fn () => Department::query()->firstOrFail()],
    'ciudad' => [EditCity::class, fn () => City::query()->firstOrFail()],
    'barrio' => [EditNeighborhood::class, fn () => Neighborhood::query()->firstOrFail()],
]);
