<?php

use App\Filament\Pages\UserGuidePage;
use App\Support\UserGuide;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('todos los temas de la guía tienen título, orden y un permiso que existe', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    UserGuide::all()->each(function (array $topic): void {
        expect($topic['title'])->not->toBeEmpty()
            ->and($topic['body'])->not->toBeEmpty()
            ->and($topic['order'])->toBeLessThan(100);

        if ($topic['permission']) {
            expect(Permission::where('name', $topic['permission'])->exists())
                ->toBeTrue("El permiso {$topic['permission']} del tema {$topic['slug']} no existe");
        }
    });
});

it('los slugs de la guía no incluyen el prefijo numérico del archivo', function () {
    expect(UserGuide::all()->keys()->all())->toContain('primeros-pasos', 'vacunaciones')
        ->and(UserGuide::all()->keys()->filter(fn (string $slug): bool => preg_match('/^\d/', $slug) === 1))->toBeEmpty();
});

it('cada rol ve solo los temas de los recursos a los que tiene acceso', function () {
    actingAsRole('assistant');
    $asistente = UserGuide::topicsFor(auth()->user())->keys()->all();

    expect($asistente)->toContain('primeros-pasos', 'vacunaciones', 'propietarios-y-mascotas')
        ->and($asistente)->not->toContain('administracion');

    actingAsRole('admin');
    expect(UserGuide::topicsFor(auth()->user())->keys()->all())->toContain('administracion', 'cirugias');
});

it('cualquier usuario del panel abre la guía y ve su primer tema', function () {
    actingAsRole('assistant');

    $this->get(UserGuidePage::getUrl())->assertOk()->assertSee('Primeros pasos');

    Livewire::test(UserGuidePage::class)
        ->assertSee('Bienvenido al sistema')
        ->set('topic', 'vacunaciones')
        ->assertSee('vigente')
        ->set('topic', 'administracion')
        ->assertSee('Bienvenido al sistema');
});

it('un tema al que el rol no tiene acceso no se puede abrir por la URL', function () {
    actingAsRole('veterinarian');

    Livewire::withQueryParams(['tema' => 'administracion'])
        ->test(UserGuidePage::class)
        ->assertDontSee('Roles y permisos');
});

it('el Markdown de la guía escapa HTML y descarta enlaces peligrosos', function () {
    $html = UserGuide::toHtml("<script>alert(1)</script>\n\n[x](javascript:alert(1))");

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('javascript:');
});
