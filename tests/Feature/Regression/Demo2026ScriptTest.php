<?php

use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->pdfDisk = config('filament.default_filesystem_disk', 'public');
    Storage::fake($this->pdfDisk);
    $this->demo = require base_path('database/demo-2026.php');
});

it('en dry-run no deja nada escrito, ni siquiera los veterinarios', function () {
    $result = ($this->demo)();

    expect($result['modo'])->toContain('DRY-RUN')
        ->and($result['registros']['consultas'])->toBeGreaterThan(100)
        ->and(Owner::count())->toBe(0)
        ->and(Pet::count())->toBe(0)
        ->and(Consultation::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and(Storage::disk($this->pdfDisk)->allFiles())->toBeEmpty();
});

it('siembra datos coherentes entre febrero y octubre de 2026', function () {
    $result = ($this->demo)(dryRun: false);

    expect($result['registros'])->toMatchArray([
        'propietarios' => 70,
        'mascotas' => 113,
    ])
        ->and(Owner::count())->toBe(70)
        ->and(Pet::count())->toBe(113)
        ->and(Owner::where('email', 'not like', '%@example.com')->count())->toBe(0);

    // consultas: dentro del rango, ninguna futura ni anterior al nacimiento de la mascota
    // promedio mensual de ~70 consultas (feb a oct = 9 meses)
    expect(Consultation::count())->toBeBetween(615, 645)
        ->and(substr(Consultation::min('consultation_date'), 0, 10))->toBeGreaterThanOrEqual('2026-02-02')
        ->and(substr(Consultation::max('consultation_date'), 0, 10))->toBeLessThanOrEqual('2026-10-21');
    expect(Consultation::all()->every(fn ($c) => $c->created_at->hour >= 8 && $c->created_at->hour <= 16))->toBeTrue();
    expect(Consultation::with('pet')->get()->filter(fn ($c) => $c->consultation_date->lt($c->pet->birthdate))->count())->toBe(0);

    // IA: solo desde junio, con rastro completo y por veterinarios con el permiso
    $ai = Consultation::whereNotNull('ai_suggested_at')->get();
    expect($ai->count())->toBeBetween(70, 130)
        ->and($ai->every(fn ($c) => $c->consultation_date->gte('2026-08-03')))->toBeTrue()
        ->and($ai->every(fn ($c) => $c->ai_urgency !== null && $c->ai_input_tokens > 0 && $c->ai_output_tokens > 0
            && filled($c->ai_diagnosis_suggestion) && filled($c->ai_treatment_suggestion)))->toBeTrue()
        ->and($ai->every(fn ($c) => $c->user->hasPermissionTo('use_ai_diagnostics')))->toBeTrue();
    expect(Consultation::whereNull('ai_suggested_at')->whereNotNull('ai_urgency')->count())->toBe(0);

    // vacunas: nunca se enviaría un recordatorio real a un propietario demo
    expect(Vaccination::count())->toBeBetween(200, 330)
        ->and(Vaccination::whereNull('reminder_sent_at')->count())->toBe(0);

    // cirugías: la agenda futura llega hasta fin de octubre
    expect(Surgery::where('date', '>', '2026-10-21')->count())->toBe(3)
        ->and(substr(Surgery::max('date'), 0, 10))->toBeLessThanOrEqual('2026-10-31');

    // exámenes: sin fechas futuras, con PDF adjunto salvo los pendientes
    expect(Test::where('date', '>', '2026-10-21')->count())->toBe(0);
    $withoutResult = Test::withoutResult()->count();
    expect($withoutResult)->toBeBetween(8, 30);
    $withResult = Test::whereNotNull('result')->get();
    expect($withResult->count())->toBeGreaterThan(15)
        ->and($withResult->every(fn ($t) => Storage::disk($this->pdfDisk)->exists($t->result[0])))->toBeTrue();

    // ninguna mascota tiene dos esterilizaciones de hembra
    $doubleSpay = Surgery::whereIn('type', ['Ovariohisterectomía', 'Esterilización'])
        ->get()->groupBy('pet_id')->filter(fn ($g) => $g->count() > 1);
    expect($doubleSpay)->toHaveCount(0);
});

it('se niega a correr dos veces y el cleanup borra solo lo demo', function () {
    ($this->demo)(dryRun: false);

    $again = ($this->demo)(dryRun: false);
    expect($again)->toHaveKey('abortado')
        ->and(Owner::count())->toBe(70);

    $real = Owner::factory()->create(['email' => 'real@clinica.com.py']);

    ($this->demo)(cleanup: true, dryRun: false);

    expect(Owner::pluck('id')->all())->toBe([$real->id])
        ->and(Pet::count())->toBe(0)
        ->and(Consultation::count())->toBe(0)
        ->and(Vaccination::count())->toBe(0)
        ->and(Surgery::count())->toBe(0)
        ->and(Test::count())->toBe(0)
        ->and(Storage::disk($this->pdfDisk)->allFiles())->toBeEmpty();
});

it('no mezcla datos con una clínica que ya tiene propietarios reales salvo que se pida', function () {
    Owner::factory()->create(['email' => 'real@clinica.com.py']);

    expect(($this->demo)(dryRun: false))->toHaveKey('abortado')
        ->and(Pet::count())->toBe(0);

    $result = ($this->demo)(dryRun: false, allowExisting: true);

    expect($result['modo'])->toBe('ESCRITO')
        ->and(Owner::count())->toBe(71);
});

it('atribuye los registros solo a los veterinarios indicados', function () {
    $vet = User::factory()->create(['email' => 'solo@clinica.com.py']);
    $vet->assignRole('veterinarian');
    $other = User::factory()->create(['email' => 'otro@clinica.com.py']);
    $other->assignRole('veterinarian');

    expect(($this->demo)(dryRun: false, vetEmails: ['inexistente@clinica.com.py']))->toHaveKey('abortado');

    ($this->demo)(dryRun: false, vetEmails: ['solo@clinica.com.py']);

    expect(Consultation::pluck('user_id')->unique()->all())->toBe([$vet->id])
        ->and(Pet::pluck('user_id')->unique()->all())->toBe([$vet->id]);
});
