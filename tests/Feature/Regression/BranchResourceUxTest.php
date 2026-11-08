<?php

use App\Filament\Resources\BranchResource;
use App\Filament\Resources\BranchResource\Pages\CreateBranch;
use App\Filament\Resources\BranchResource\Pages\EditBranch;
use App\Filament\Resources\BranchResource\Pages\ListBranches;
use App\Filament\Resources\BranchResource\Pages\ViewBranch;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Livewire\BranchSwitcher;
use App\Models\Branch;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\User;
use App\Support\BranchContext;
use Livewire\Livewire;

it('el administrador ve la lista de sucursales ordenada por código', function () {
    actingAsRole('admin');
    $segunda = Branch::factory()->create(['code' => '002']);

    Livewire::test(ListBranches::class)
        ->assertCanSeeTableRecords([Branch::main(), $segunda], inOrder: true)
        ->assertTableColumnExists('users_count')
        ->assertTableColumnExists('is_active');
});

it('filtra las sucursales por estado', function () {
    actingAsRole('admin');
    $inactiva = Branch::factory()->inactive()->create();

    Livewire::test(ListBranches::class)
        ->filterTable('is_active', true)
        ->assertCanNotSeeTableRecords([$inactiva])
        ->assertCanSeeTableRecords([Branch::main()])
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactiva])
        ->assertCanNotSeeTableRecords([Branch::main()]);
});

it('crea una sucursal con código de 3 dígitos único', function () {
    actingAsRole('admin');

    Livewire::test(CreateBranch::class)
        ->fillForm(['code' => '002', 'name' => 'Sucursal Luque', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Branch::where('code', '002')->value('name'))->toBe('Sucursal Luque');

    Livewire::test(CreateBranch::class)
        ->fillForm(['code' => '002', 'name' => 'Repetida'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);

    Livewire::test(CreateBranch::class)
        ->fillForm(['code' => '12', 'name' => 'Corta'])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('la sucursal principal no cambia de código ni se deshabilita', function () {
    actingAsRole('admin');
    $main = Branch::main();

    Livewire::test(EditBranch::class, ['record' => $main->getRouteKey()])
        ->assertFormFieldIsDisabled('code')
        ->assertFormFieldIsDisabled('is_active')
        ->fillForm(['name' => 'Casa matriz'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($main->fresh())->name->toBe('Casa matriz')->code->toBe('001')->is_active->toBeTrue();
});

it('la ficha muestra la sucursal sin subtítulo', function () {
    actingAsRole('admin');
    $branch = Branch::factory()->create(['name' => 'Luque']);

    $page = Livewire::test(ViewBranch::class, ['record' => $branch->getRouteKey()])->assertSuccessful();

    expect($page->instance()->getTitle())->toBe('Luque')
        ->and($page->instance()->getSubheading())->toBeNull();
});

it('no se puede eliminar la principal ni una sucursal con personal o registros', function () {
    actingAsRole('admin');

    $conPersonal = Branch::factory()->create();
    User::factory()->create(['branch_id' => $conPersonal->id]);

    $conRegistros = Branch::factory()->create();
    Owner::factory()->create(['branch_id' => $conRegistros->id]);

    foreach ([Branch::main(), $conPersonal, $conRegistros] as $branch) {
        Livewire::test(EditBranch::class, ['record' => $branch->getRouteKey()])
            ->assertActionDisabled('delete');
    }

    $libre = Branch::factory()->create();
    Livewire::test(EditBranch::class, ['record' => $libre->getRouteKey()])->callAction('delete');

    expect(Branch::find($libre->id))->toBeNull();
});

it('veterinarios y asistentes no ven las sucursales por defecto', function (string $role) {
    actingAsRole($role);

    expect(BranchResource::canViewAny())->toBeFalse();
    $this->get('/sucursales')->assertForbidden();
})->with(['veterinarian', 'assistant']);

it('el formulario de usuarios exige sucursal al personal y no al administrador', function () {
    actingAsRole('admin');
    $branch = Branch::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            ...domicilioDeUsuario(),
            'name' => 'Dra. Prueba',
            'email' => 'prueba@example.com',
            'role' => 'veterinarian',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])
        ->call('create')
        ->assertHasFormErrors(['branch_id' => 'required'])
        ->fillForm(['branch_id' => $branch->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'prueba@example.com')->value('branch_id'))->toBe($branch->id);
});

it('un usuario que pasa a administrador pierde su sucursal', function () {
    actingAsRole('admin');
    $vet = User::factory()->create(['branch_id' => Branch::main()->id]);
    $vet->assignRole('veterinarian');

    Livewire::test(EditUser::class, ['record' => $vet->getRouteKey()])
        ->fillForm(['role' => 'admin', ...domicilioDeUsuario()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vet->fresh()->branch_id)->toBeNull()->and($vet->fresh()->hasRole('admin'))->toBeTrue();
});

/** Domicilio válido para el formulario de usuarios, que lo exige completo. */
function domicilioDeUsuario(): array
{
    $neighborhood = Neighborhood::query()->with('city')->firstOrFail();

    return [
        'department_id' => $neighborhood->city->department_id,
        'city_id' => $neighborhood->city_id,
        'neighborhood_id' => $neighborhood->id,
        'address' => 'Calle 1',
    ];
}

describe('selector de sucursal activa', function () {
    it('no aparece mientras hay una sola sucursal habilitada', function () {
        actingAsRole('admin');

        Livewire::test(BranchSwitcher::class)->assertDontSee('Casa central');
    });

    it('lista las sucursales habilitadas y el administrador cambia la activa', function () {
        actingAsRole('admin');
        $luque = Branch::factory()->create(['code' => '002', 'name' => 'Luque']);
        Branch::factory()->inactive()->create(['name' => 'Cerrada']);

        Livewire::test(BranchSwitcher::class)
            ->assertSee('Casa central')
            ->assertSee('Luque')
            ->assertDontSee('Cerrada')
            ->call('switch', $luque->id);

        expect(BranchContext::currentId())->toBe($luque->id);
    });

    it('no admite una sucursal deshabilitada', function () {
        actingAsRole('admin');
        $inactiva = Branch::factory()->inactive()->create();

        Livewire::test(BranchSwitcher::class)->call('switch', $inactiva->id)->assertNotFound();
    });

    it('el personal con sucursal propia no lo ve ni puede usarlo', function () {
        $luque = Branch::factory()->create();
        $vet = User::factory()->create(['branch_id' => $luque->id]);
        $vet->assignRole('veterinarian');
        $this->actingAs($vet);

        Livewire::test(BranchSwitcher::class)
            ->assertDontSee('Casa central')
            ->call('switch', Branch::main()->id)
            ->assertForbidden();
    });

    it('se muestra en el panel para el administrador con varias sucursales', function () {
        actingAsRole('admin');
        Branch::factory()->create(['name' => 'Luque']);

        $this->get('/')->assertOk()->assertSee('Luque');
    });
});
