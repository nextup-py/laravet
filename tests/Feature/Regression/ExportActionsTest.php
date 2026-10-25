<?php

use App\Filament\Exporters\OwnerExporter;
use App\Filament\Exporters\PetExporter;
use App\Filament\Exporters\VaccinationExporter;
use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\SurgeryResource\Pages\ListSurgeries;
use App\Filament\Resources\TestResource\Pages\ListTests;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('las listas muestran Exportar solo a quien tiene el permiso export_data', function (string $page) {
    actingAsRole('admin');
    Livewire::test($page)->assertActionVisible('export');

    actingAsRole('assistant');
    Livewire::test($page)->assertActionHidden('export');
})->with([
    ListPets::class,
    ListOwners::class,
    ListVaccinations::class,
    ListConsultations::class,
    ListSurgeries::class,
    ListTests::class,
]);

it('quien no puede ver propietarios no exporta sus datos', function () {
    $names = fn (string $exporter): array => collect($exporter::getColumns())
        ->map(fn (ExportColumn $column): string => $column->getName())
        ->all();

    actingAsRole('admin');
    expect($names(PetExporter::class))->toContain('owner.phone')
        ->and($names(VaccinationExporter::class))->toContain('pet.owner.phone');

    $role = Role::create(['name' => 'sin-propietarios', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_pet', 'view_pet', 'export_data']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    expect($names(PetExporter::class))->not->toContain('owner.phone')
        ->and($names(VaccinationExporter::class))->not->toContain('pet.owner.phone')
        ->and($names(OwnerExporter::class))->toContain('phone');
});
