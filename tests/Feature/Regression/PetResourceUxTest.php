<?php

use App\Filament\Resources\PetResource;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('la lista de mascotas muestra solo las activas por defecto', function () {
    actingAsRole('admin');
    $activa = Pet::factory()->create(['active' => true]);
    $inactiva = Pet::factory()->inactiva()->create();

    Livewire::test(ListPets::class)
        ->assertCanSeeTableRecords([$activa])
        ->assertCanNotSeeTableRecords([$inactiva])
        ->removeTableFilter('active')
        ->assertCanSeeTableRecords([$activa, $inactiva]);
});

it('filtra mascotas por propietario', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create();
    $suya = Pet::factory()->create(['owner_id' => $owner->id]);
    $ajena = Pet::factory()->create();

    Livewire::test(ListPets::class)
        ->filterTable('owner_id', $owner->id)
        ->assertCanSeeTableRecords([$suya])
        ->assertCanNotSeeTableRecords([$ajena]);
});

it('filtra mascotas con vacunas vencidas o por vencer', function () {
    actingAsRole('admin');
    $vencida = Pet::factory()->create();
    Vaccination::factory()->create(['pet_id' => $vencida->id, 'next_application' => now()->subDays(3)]);
    $alDia = Pet::factory()->create();
    Vaccination::factory()->create(['pet_id' => $alDia->id, 'next_application' => now()->addMonths(6)]);

    Livewire::test(ListPets::class)
        ->filterTable('vaccination_due', true)
        ->assertCanSeeTableRecords([$vencida])
        ->assertCanNotSeeTableRecords([$alDia])
        ->filterTable('vaccination_due', false)
        ->assertCanSeeTableRecords([$alDia])
        ->assertCanNotSeeTableRecords([$vencida]);
});

it('ordena por edad de forma coherente con la fecha de nacimiento', function () {
    actingAsRole('admin');
    $vieja = Pet::factory()->create(['birthdate' => now()->subYears(10)]);
    $joven = Pet::factory()->create(['birthdate' => now()->subYear()]);

    Livewire::test(ListPets::class)
        ->sortTable('age', 'desc')
        ->assertCanSeeTableRecords([$vieja, $joven], inOrder: true);
});

it('busca mascotas por nombre del propietario', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create(['last_name' => 'Zzapellido']);
    $suya = Pet::factory()->create(['owner_id' => $owner->id]);
    $ajena = Pet::factory()->create();

    Livewire::test(ListPets::class)
        ->searchTable('Zzapellido')
        ->assertCanSeeTableRecords([$suya])
        ->assertCanNotSeeTableRecords([$ajena]);
});

it('la mascota sin foto tiene un avatar con su inicial', function () {
    $pet = Pet::factory()->make(['name' => 'Rocky']);

    expect($pet->initials_avatar_url)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($pet->initials_avatar_url, strlen('data:image/svg+xml;base64,'))))->toContain('>R<');
});

it('la ficha de la mascota usa su nombre como título y enlaza al propietario', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(ViewPet::class, ['record' => $pet->getRouteKey()])
        ->assertSee($pet->name)
        ->assertSee($pet->owner->full_name)
        ->assertSee($pet->owner->whatsapp_url);
});

it('la búsqueda global de mascotas incluye al propietario en el detalle', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    $details = PetResource::getGlobalSearchResultDetails($pet);

    expect($details['Propietario'])->toBe($pet->owner->full_name);
});

it('sin permiso sobre propietarios no se filtran apellido, CI ni teléfono en mascotas', function () {
    $role = Role::create(['name' => 'solo-mascotas', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    $owner = Owner::factory()->create(['last_name' => 'Zzapellido', 'phone' => '0981999888']);
    $pet = Pet::factory()->create(['owner_id' => $owner->id]);

    expect(PetResource::getGloballySearchableAttributes())->toBe(['name', 'breed'])
        ->and(PetResource::getGlobalSearchResultDetails($pet))->not->toHaveKey('Propietario');

    Livewire::test(ViewPet::class, ['record' => $pet->getRouteKey()])
        ->assertSee($owner->first_name)
        ->assertDontSee('Zzapellido')
        ->assertDontSee('0981999888');

    Livewire::test(ListPets::class)
        ->assertCanSeeTableRecords([$pet])
        ->assertDontSee('Zzapellido');
});
