<?php

use App\Filament\Widgets\TestsWithoutResultWidget;
use App\Filament\Widgets\UpcomingSurgeriesWidget;
use App\Filament\Widgets\UpcomingVaccinationsWidget;
use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAsWithoutOwnerAccess(array $permissions): User
{
    $role = Role::create(['name' => 'sin_propietarios_'.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permissions);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

function petWithOwner(): Pet
{
    $owner = Owner::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Benítez',
        'ci' => '1234567',
        'phone' => '0981123456',
        'address' => 'Calle Secreta 12',
    ]);

    return Pet::factory()->create(['owner_id' => $owner->id]);
}

it('los widgets del dashboard muestran solo el nombre de pila del propietario a quien no puede ver propietarios', function () {
    $pet = petWithOwner();
    $vaccination = Vaccination::factory()->proximaAVencer(2)->create(['pet_id' => $pet->id]);
    $surgery = Surgery::factory()->create(['pet_id' => $pet->id, 'date' => today()->addDay()]);
    $test = Test::factory()->create(['pet_id' => $pet->id, 'result' => null]);

    actingAsWithoutOwnerAccess(['view_any_vaccination', 'view_any_surgery', 'view_any_test']);

    Livewire::test(UpcomingVaccinationsWidget::class)->assertTableColumnFormattedStateSet('pet.owner.first_name', 'Ana', $vaccination);
    Livewire::test(UpcomingSurgeriesWidget::class)->assertTableColumnFormattedStateSet('pet.owner.first_name', 'Ana', $surgery);
    Livewire::test(TestsWithoutResultWidget::class)->assertTableColumnFormattedStateSet('pet.owner.first_name', 'Ana', $test);
});

it('los widgets del dashboard muestran el nombre completo a quien puede ver propietarios', function () {
    $pet = petWithOwner();
    $vaccination = Vaccination::factory()->proximaAVencer(2)->create(['pet_id' => $pet->id]);

    actingAsRole('admin');

    Livewire::test(UpcomingVaccinationsWidget::class)->assertTableColumnFormattedStateSet('pet.owner.first_name', 'Ana Benítez', $vaccination);
});

it('los PDF no incluyen apellido, CI, teléfono ni dirección del propietario sin permiso sobre propietarios', function () {
    $pet = petWithOwner();
    $consultation = Consultation::factory()->create(['pet_id' => $pet->id]);

    actingAsWithoutOwnerAccess(['view_pet', 'view_any_pet', 'view_any_consultation', 'view_any_vaccination']);

    $captured = [];
    View::composer(['pdf.pet-id-card', 'pdf.clinical-history'], function ($view) use (&$captured): void {
        $captured[$view->name()] ??= $view->getData();
    });

    $this->get(route('filament.admin.pdf.pet-id-card', $pet))->assertOk();
    $this->get(route('filament.admin.pdf.clinical-history', $pet))->assertOk();

    $html = collect($captured)->map(fn (array $data, string $name): string => view($name, $data)->render())->all();

    expect($html['pdf.pet-id-card'])->toContain('Ana')
        ->not->toContain('Benítez')
        ->not->toContain('1234567')
        ->not->toContain('0981123456')
        ->not->toContain('Calle Secreta 12')
        ->and($html['pdf.clinical-history'])->toContain('Ana')->not->toContain('Benítez')->not->toContain('0981123456');
});

it('los PDF incluyen los datos completos del propietario para quien puede ver propietarios', function () {
    $pet = petWithOwner();

    actingAsRole('admin');

    $captured = null;
    View::composer('pdf.pet-id-card', function ($view) use (&$captured): void {
        $captured ??= $view->getData();
    });

    $this->get(route('filament.admin.pdf.pet-id-card', $pet))->assertOk();

    expect(view('pdf.pet-id-card', $captured)->render())->toContain('Ana Benítez', '1234567', '0981123456', 'Calle Secreta 12');
});
