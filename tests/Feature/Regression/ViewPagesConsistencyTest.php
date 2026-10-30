<?php

use App\Filament\Resources\UserResource;
use App\Filament\Resources\VaccinationResource\Pages\ViewVaccination;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;

it('ninguna ficha define subtítulo', function () {
    $pages = collect(glob(app_path('Filament/Resources/*/Pages/View*.php')))
        ->map(fn (string $file): string => 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path()) + 1)));

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        expect((new ReflectionMethod($page, 'getSubheading'))->getDeclaringClass()->getName())
            ->not->toBe($page, "{$page} no debe definir getSubheading()");
    }
});

it('la ficha de vacunación muestra al veterinario con enlace a su ficha si puede ver usuarios', function () {
    actingAsRole('admin');
    $vet = User::factory()->create(['name' => 'Dra. Prueba Vacuna']);
    $vaccination = Vaccination::factory()->create(['user_id' => $vet->id]);

    Livewire::test(ViewVaccination::class, ['record' => $vaccination->getRouteKey()])
        ->assertSee('Veterinario')
        ->assertSee('Dra. Prueba Vacuna')
        ->assertSeeHtml(UserResource::getUrl('view', ['record' => $vet->id]));
});

it('la ficha de vacunación muestra al veterinario sin enlace si no puede ver usuarios', function () {
    actingAsRole('assistant');
    $vet = User::factory()->create(['name' => 'Dra. Prueba Vacuna']);
    $vaccination = Vaccination::factory()->create(['user_id' => $vet->id]);

    Livewire::test(ViewVaccination::class, ['record' => $vaccination->getRouteKey()])
        ->assertSee('Dra. Prueba Vacuna')
        ->assertDontSeeHtml(UserResource::getUrl('view', ['record' => $vet->id]));
});
