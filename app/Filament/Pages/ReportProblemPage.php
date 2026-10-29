<?php

namespace App\Filament\Pages;

use App\Enums\ProblemReportStatus;
use App\Enums\ProblemReportType;
use App\Models\ProblemReport;
use App\Notifications\ProblemReportNotification;
use App\Settings\ClinicSettings;
use App\Support\SystemInfo;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Notification as MailNotification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * «Ayuda → Reportar un problema»: cualquier usuario del panel cuenta qué falla, una duda o una
 * sugerencia. Queda registrado (lo ve quien administra los reportes) y se avisa por correo a soporte.
 */
class ReportProblemPage extends Page implements HasForms
{
    use InteractsWithForms;

    /** Reportes por usuario y por hora, para que el formulario no se use para enviar spam. */
    private const MAX_PER_HOUR = 5;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Reportar un problema';

    protected static ?string $title = 'Reportar un problema';

    protected static ?string $slug = 'reportar-un-problema';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.report-problem-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $previous = url()->previous();

        $this->form->fill([
            'type' => ProblemReportType::Error->value,
            // La pantalla desde la que se abrió el formulario; si llegó directo, queda vacía.
            'screen' => $previous !== url()->current() ? $previous : null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('type')
                    ->label('¿Qué quieres contarnos?')
                    ->options(ProblemReportType::class)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Cuéntanos qué pasó')
                    ->helperText('Qué estabas haciendo, qué esperabas que pasara y qué pasó en cambio.')
                    ->rows(6)
                    ->required()
                    ->minLength(10)
                    ->maxLength(3000),
                Forms\Components\TextInput::make('screen')
                    ->label('Pantalla donde ocurrió')
                    ->helperText('Se completa sola con la pantalla desde la que entraste; puedes corregirla.')
                    ->maxLength(500),
                Forms\Components\FileUpload::make('attachment')
                    ->label('Captura de pantalla (opcional)')
                    ->helperText('PNG o JPG, hasta 4 MB.')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                    ->maxSize(4096)
                    ->disk('problem-reports')
                    ->directory('capturas')
                    ->visibility('private'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $user = auth()->user();
        $key = 'problem-report:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            Notification::make()
                ->title('Ya enviaste varios reportes en la última hora')
                ->body('Espera un rato antes de enviar otro, o escribe directamente a soporte.')
                ->warning()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $report = ProblemReport::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'type' => $data['type'],
            'description' => $data['description'],
            'screen' => $data['screen'] ?? null,
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500) ?: null,
            'app_version' => SystemInfo::version(),
            'attachment' => $data['attachment'] ?? null,
            'status' => ProblemReportStatus::New,
        ]);

        RateLimiter::hit($key, 3600);

        MailNotification::route('mail', ClinicSettings::tryGet()?->support_email ?: ClinicSettings::DEFAULT_SUPPORT_EMAIL)
            ->notify(new ProblemReportNotification($report));

        $this->form->fill(['type' => ProblemReportType::Error->value]);

        Notification::make()
            ->title('Reporte enviado')
            ->body('Gracias: lo recibió el equipo de soporte y te responderán por correo.')
            ->success()
            ->send();
    }
}
