<?php

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource;
use App\Support\SeedReport;
use Illuminate\Console\Command;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('los roles base tienen etiqueta en español y los demás se muestran tal cual', function () {
    expect(ClinicRoles::label('admin'))->toBe('Administrador')
        ->and(ClinicRoles::label('veterinarian'))->toBe('Veterinario')
        ->and(ClinicRoles::label('assistant'))->toBe('Asistente')
        ->and(ClinicRoles::label('recepcion'))->toBe('recepcion')
        ->and(ClinicRoles::isBase('assistant'))->toBeTrue()
        ->and(ClinicRoles::isBase('recepcion'))->toBeFalse();
});

it('las opciones de rol de usuarios usan las mismas etiquetas', function () {
    Role::create(['name' => 'recepcion', 'guard_name' => 'web']);

    expect(UserResource::roleOptions())->toMatchArray([
        'admin' => 'Administrador',
        'veterinarian' => 'Veterinario',
        'assistant' => 'Asistente',
        'recepcion' => 'recepcion',
    ]);
});

it('el título del rol en migas y fichas usa la etiqueta en español', function () {
    expect(RoleResource::getRecordTitle(Role::findByName('veterinarian')))->toBe('Veterinario')
        ->and(RoleResource::getRecordTitle(Role::create(['name' => 'recepcion', 'guard_name' => 'web'])))->toBe('recepcion');
});

it('la lista de roles muestra la etiqueta en español y el identificador de los roles base', function () {
    actingAsRole('admin');

    Livewire::test(ListRoles::class)
        ->assertTableColumnFormattedStateSet('name', 'Veterinario', Role::findByName('veterinarian'))
        ->assertTableColumnFormattedStateSet('name', 'Administrador', Role::findByName('admin'))
        ->assertTableColumnHasDescription('name', 'veterinarian', Role::findByName('veterinarian'));
});

it('la columna guard_name del listado de roles queda oculta por defecto', function () {
    actingAsRole('admin');

    Livewire::test(ListRoles::class)->assertTableColumnExists('guard_name', fn ($column): bool => $column->isToggledHiddenByDefault());
});

it('el nombre de un rol base no se puede cambiar, pero sí el de un rol propio', function () {
    actingAsRole('admin');
    $base = Role::findByName('veterinarian');
    $custom = Role::create(['name' => 'recepcion', 'guard_name' => 'web']);

    Livewire::test(EditRole::class, ['record' => $base->getRouteKey()])
        ->assertFormFieldIsDisabled('name')
        ->fillForm(['name' => 'otro'])
        ->call('save');

    Livewire::test(EditRole::class, ['record' => $custom->getRouteKey()])
        ->assertFormFieldIsEnabled('name')
        ->fillForm(['name' => 'recepcion-central'])
        ->call('save');

    expect($base->fresh()->name)->toBe('veterinarian')
        ->and($custom->fresh()->name)->toBe('recepcion-central');
});

it('la tabla de la siembra muestra la etiqueta con el identificador del rol', function () {
    $report = new SeedReport;
    $report->user('a@mbopivet-demo.test', 'veterinarian', 'password');
    $report->user('b@mbopivet-demo.test', 'recepcion', null);

    $command = Mockery::mock(Command::class);
    $command->shouldReceive('newLine', 'line', 'warn');
    $command->shouldReceive('table')->once()->withArgs(fn (array $headers, array $rows): bool => $rows[0][1] === 'Veterinario (veterinarian)' && $rows[1][1] === 'recepcion');

    $report->render($command);
});
