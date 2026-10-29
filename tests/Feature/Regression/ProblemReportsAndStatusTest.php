<?php

use App\Enums\ProblemReportStatus;
use App\Enums\ProblemReportType;
use App\Filament\Pages\ReportProblemPage;
use App\Filament\Pages\SystemStatusPage;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Resources\ProblemReportResource\Pages\ManageProblemReports;
use App\Models\ProblemReport;
use App\Notifications\ProblemReportNotification;
use App\Settings\ClinicSettings;
use App\Support\SystemInfo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('problem-reports');
    RateLimiter::clear('problem-report:1');
});

it('cualquier usuario del panel abre Reportar un problema', function () {
    actingAsRole('assistant');

    $this->get(ReportProblemPage::getUrl())->assertOk()->assertSee('Reportar un problema');
});

it('enviar un reporte lo guarda con el usuario, el navegador y la versión, y avisa por correo a soporte', function () {
    Notification::fake();
    $settings = app(ClinicSettings::class);
    $settings->support_email = 'soporte@example.com';
    $settings->save();

    $user = actingAsRole('veterinarian');

    Livewire::test(ReportProblemPage::class)
        ->fillForm([
            'type' => ProblemReportType::Question->value,
            'description' => 'No encuentro cómo cargar el resultado de una prueba.',
            'screen' => 'https://laravet.test/pruebas',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified('Reporte enviado');

    $report = ProblemReport::firstOrFail();

    expect($report->user_id)->toBe($user->id)
        ->and($report->user_name)->toBe($user->name)
        ->and($report->type)->toBe(ProblemReportType::Question)
        ->and($report->status)->toBe(ProblemReportStatus::New)
        ->and($report->screen)->toBe('https://laravet.test/pruebas');

    Notification::assertSentOnDemand(
        ProblemReportNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'soporte@example.com',
    );
});

it('la descripción es obligatoria y tiene un mínimo', function () {
    actingAsRole('assistant');

    Livewire::test(ReportProblemPage::class)
        ->fillForm(['description' => ''])
        ->call('submit')
        ->assertHasFormErrors(['description' => 'required'])
        ->fillForm(['description' => 'corto'])
        ->call('submit')
        ->assertHasFormErrors(['description' => 'min:10']);

    expect(ProblemReport::count())->toBe(0);
});

it('la captura va al disco privado y solo admite PNG o JPG', function () {
    Notification::fake();
    actingAsRole('assistant');

    Livewire::test(ReportProblemPage::class)
        ->fillForm([
            'description' => 'Se ve cortado el botón de guardar.',
            'attachment' => UploadedFile::fake()->image('captura.png'),
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    $report = ProblemReport::firstOrFail();

    expect($report->attachment)->not->toBeNull();
    Storage::disk('problem-reports')->assertExists($report->attachment);

    Livewire::test(ReportProblemPage::class)
        ->fillForm([
            'description' => 'Un archivo que no es una imagen.',
            'attachment' => UploadedFile::fake()->create('virus.html', 10, 'text/html'),
        ])
        ->call('submit')
        ->assertHasFormErrors(['attachment']);
});

it('limita a cinco los reportes por usuario y hora', function () {
    Notification::fake();
    $user = actingAsRole('assistant');

    foreach (range(1, 5) as $i) {
        Livewire::test(ReportProblemPage::class)
            ->fillForm(['description' => "Reporte número {$i} con detalle."])
            ->call('submit');
    }

    Livewire::test(ReportProblemPage::class)
        ->fillForm(['description' => 'Un sexto reporte dentro de la hora.'])
        ->call('submit')
        ->assertNotified('Ya enviaste varios reportes en la última hora');

    expect(ProblemReport::where('user_id', $user->id)->count())->toBe(5);
});

it('el correo a soporte responde al usuario y adjunta la captura', function () {
    $report = ProblemReport::factory()->create(['attachment' => 'capturas/foto.png']);
    Storage::disk('problem-reports')->put('capturas/foto.png', 'png');

    $mail = (new ProblemReportNotification($report))->toMail(new stdClass);

    expect($mail->subject)->toContain('Algo falla')
        ->and(collect($mail->replyTo)->pluck(0)->all())->toBe([$report->user->email])
        ->and($mail->attachments)->not->toBeEmpty();
});

it('solo quien tiene manage_problem_reports ve la lista de reportes', function () {
    ProblemReport::factory()->count(2)->create();

    actingAsRole('assistant');
    $this->get(ProblemReportResource::getUrl())->assertForbidden();

    actingAsRole('admin');
    $this->get(ProblemReportResource::getUrl())->assertOk();

    expect(Permission::where('name', 'manage_problem_reports')->exists())->toBeTrue();
});

it('el administrador marca los reportes como vistos y resueltos, y el contador cuenta los nuevos', function () {
    actingAsRole('admin');
    $reports = ProblemReport::factory()->count(3)->create();

    expect(ProblemReportResource::getNavigationBadge())->toBe('3');

    Livewire::test(ManageProblemReports::class)
        ->assertCanSeeTableRecords($reports)
        ->callTableAction('markSeen', $reports[0])
        ->callTableAction('markResolved', $reports[1]);

    expect($reports[0]->fresh()->status)->toBe(ProblemReportStatus::Seen)
        ->and($reports[1]->fresh()->status)->toBe(ProblemReportStatus::Resolved)
        ->and(ProblemReportResource::getNavigationBadge())->toBe('1');
});

it('la captura solo la entrega a quien administra los reportes y fuerza los tipos no imagen como descarga', function () {
    $report = ProblemReport::factory()->create(['attachment' => 'capturas/foto.png']);
    Storage::disk('problem-reports')->put('capturas/foto.png', 'contenido');

    actingAsRole('assistant');
    $this->get(route('filament.admin.problem-reports.attachment', $report))->assertForbidden();

    actingAsRole('admin');
    $this->get(route('filament.admin.problem-reports.attachment', $report))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('el estado del sistema es solo para administradores', function () {
    actingAsRole('veterinarian');
    $this->get(SystemStatusPage::getUrl())->assertForbidden();

    actingAsRole('admin');
    $this->get(SystemStatusPage::getUrl())
        ->assertOk()
        ->assertSee('Cola de trabajos')
        ->assertSee('Tareas programadas')
        ->assertSee('Correo')
        ->assertSee('Entorno');
});

it('el estado del sistema avisa si falta el email de la clínica o el programador no corre', function () {
    clinicEmail(null);
    Cache::forget(SystemInfo::SCHEDULER_HEARTBEAT);

    expect(SystemInfo::mail()['warnings'])->toContain('Falta el email de la clínica: no se envían recordatorios a propietarios y las respuestas no tienen a dónde llegar.')
        ->and(SystemInfo::schedulerLastRun())->toBeNull();

    actingAsRole('admin');
    $this->get(SystemStatusPage::getUrl())->assertSee('No hay una corrida reciente');

    Cache::forever(SystemInfo::SCHEDULER_HEARTBEAT, now()->toIso8601String());
    clinicEmail('clinica@example.com');

    $this->get(SystemStatusPage::getUrl())->assertDontSee('No hay una corrida reciente');
});

it('el estado del sistema cuenta la cola y no expone claves del entorno', function () {
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

    expect(SystemInfo::queue()['pending'])->toBe(1);

    actingAsRole('admin');
    $html = $this->get(SystemStatusPage::getUrl())->getContent();

    expect($html)->not->toContain(config('app.key'))->not->toContain('RESEND');
});

it('el programador deja su marca de vida cada minuto', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => $event->description === 'scheduler-heartbeat');

    expect($events)->toHaveCount(1);
});
