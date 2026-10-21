<?php

use App\Models\Consultation;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filament.default_filesystem_disk', 'public'));
    $this->demo = require base_path('database/demo-2026.php');
    $this->staff = require base_path('database/demo-staff-2026.php');
});

it('aborta si todavía no hay datos demo', function () {
    expect(($this->staff)(dryRun: false))->toHaveKey('abortado')
        ->and(User::count())->toBe(0);
});

it('en dry-run no deja nada escrito', function () {
    ($this->demo)(dryRun: false);
    $before = User::count();

    $result = ($this->staff)();

    expect($result['modo'])->toContain('DRY-RUN')
        ->and(User::count())->toBe($before);
});

it('crea los usuarios con sus roles y reparte la autoría respetando las fechas de ingreso', function () {
    ($this->demo)(dryRun: false);

    $result = ($this->staff)(dryRun: false);

    $vet = User::where('email', 'sebastian.ayala@mbopivet.com.py')->first();
    $lucia = User::where('email', 'lucia.romero@mbopivet.com.py')->first();
    $daniel = User::where('email', 'daniel.cabrera@mbopivet.com.py')->first();

    expect($vet->hasRole('veterinarian'))->toBeTrue()
        ->and($vet->hasPermissionTo('use_ai_diagnostics'))->toBeTrue()
        ->and($lucia->hasRole('assistant'))->toBeTrue()
        ->and($daniel->hasRole('assistant'))->toBeTrue()
        ->and($result['credenciales'])->toHaveCount(3);

    // el veterinario solo firma registros posteriores a su ingreso
    $mine = Consultation::where('user_id', $vet->id)->get();
    expect($mine->count())->toBeGreaterThan(5)
        ->and($mine->every(fn ($c) => $c->consultation_date->gte('2026-06-15')))->toBeTrue();

    // las asistentes solo registran mascotas posteriores a su ingreso, sin solaparse
    expect(Pet::where('user_id', $lucia->id)->count())->toBeGreaterThan(3)
        ->and(Pet::where('user_id', $daniel->id)->count())->toBeGreaterThan(0)
        ->and(Pet::where('user_id', $daniel->id)->where('created_at', '<', '2026-07-01')->count())->toBe(0);
    expect(Consultation::whereIn('user_id', [$lucia->id, $daniel->id])->count())->toBe(0);
});

it('con login: false no devuelve contraseñas', function () {
    ($this->demo)(dryRun: false);

    expect(($this->staff)(dryRun: false, login: false)['credenciales'])->toBeString();
});

it('el cleanup devuelve los registros a otro veterinario antes de borrar a los usuarios', function () {
    ($this->demo)(dryRun: false);
    ($this->staff)(dryRun: false);
    $totalConsultations = Consultation::count();
    $totalPets = Pet::count();

    $result = ($this->staff)(cleanup: true, dryRun: false);

    expect($result['modo'])->toBe('CLEANUP')
        ->and(User::whereIn('email', ['sebastian.ayala@mbopivet.com.py', 'lucia.romero@mbopivet.com.py', 'daniel.cabrera@mbopivet.com.py'])->count())->toBe(0)
        ->and(Consultation::count())->toBe($totalConsultations)
        ->and(Pet::count())->toBe($totalPets);
});
