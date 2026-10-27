<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ClinicRoles;
use App\Settings\ClinicSettings;
use App\Support\ReminderMessage;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ClinicSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Datos de la clínica';

    protected static ?string $title = 'Datos de la clínica';

    protected static ?string $slug = 'clinic-settings';

    protected static string $view = 'filament.pages.clinic-settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(app(ClinicSettings::class)->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Configuración')
                    ->persistTabInQueryString()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-building-office')
                            ->schema($this->generalTab()),
                        Forms\Components\Tabs\Tab::make('Recordatorios')
                            ->icon('heroicon-o-bell-alert')
                            ->schema($this->remindersTab()),
                        Forms\Components\Tabs\Tab::make('Documentos PDF')
                            ->icon('heroicon-o-document-text')
                            ->schema($this->pdfTab()),
                        Forms\Components\Tabs\Tab::make('Listas')
                            ->icon('heroicon-o-list-bullet')
                            ->schema($this->listsTab()),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Imágenes de la clínica: solo formatos raster, porque un SVG servido desde
     * el origen de la app podría ejecutar script al abrirse.
     *
     * @return array<int, string>
     */
    private function rasterTypes(): array
    {
        return ['image/png', 'image/jpeg', 'image/webp'];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function generalTab(): array
    {
        return [
            Forms\Components\Section::make('Datos básicos')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre de la clínica')
                        ->helperText('Aparece en el encabezado de los PDFs generados y en los correos.')
                        ->required(),
                    Forms\Components\TextInput::make('short_name')
                        ->label('Nombre corto')
                        ->helperText('Opcional. Si lo completas, se usa como nombre del panel en lugar del nombre completo.')
                        ->maxLength(40),
                    Forms\Components\ColorPicker::make('primary_color')
                        ->label('Color primario del panel')
                        ->helperText('Color de acento usado en el panel y en el encabezado de los PDFs.'),
                    Forms\Components\Select::make('timezone')
                        ->label('Zona horaria')
                        ->helperText('Rige las fechas del sistema y la hora de los recordatorios.')
                        ->options(fn (): array => collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn (string $zone): array => [$zone => $zone])->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo')
                        ->helperText('Aparece en el encabezado de todos los PDFs generados. PNG, JPG o WebP.')
                        ->image()
                        ->acceptedFileTypes($this->rasterTypes())
                        ->directory('clinic'),
                    Forms\Components\FileUpload::make('favicon')
                        ->label('Favicon')
                        ->helperText('PNG, JPG o WebP.')
                        ->image()
                        ->acceptedFileTypes($this->rasterTypes())
                        ->directory('clinic'),
                ]),

            Forms\Components\Section::make('Contacto y ubicación')
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('address')
                        ->label('Dirección')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Teléfono')
                        ->helperText('Aparece en el encabezado de los PDFs generados.')
                        ->tel(),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->helperText('Aparece en el encabezado de los PDFs generados, junto al teléfono.')
                        ->tel(),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email(),
                    Forms\Components\TextInput::make('business_hours')
                        ->label('Horario de atención')
                        ->helperText('Aparece en el encabezado de los PDFs generados.'),
                ]),

            Forms\Components\Section::make('Datos legales y fiscales')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('ruc')
                        ->label('RUC'),
                    Forms\Components\TextInput::make('razon_social')
                        ->label('Razón social'),
                ]),

            Forms\Components\Section::make('Redes sociales y web')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('facebook')
                        ->label('Facebook')
                        ->url(),
                    Forms\Components\TextInput::make('instagram')
                        ->label('Instagram')
                        ->url(),
                    Forms\Components\TextInput::make('website')
                        ->label('Sitio web')
                        ->url(),
                ]),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function remindersTab(): array
    {
        $placeholders = collect(ReminderMessage::PLACEHOLDERS)
            ->map(fn (string $description, string $placeholder): string => "{$placeholder} = {$description}")
            ->implode('; ');

        return [
            Forms\Components\Section::make('Recordatorios de vacunación por email')
                ->description('Se envían a diario a los propietarios con vacunas vigentes vencidas o por vencer.')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('reminders_enabled')
                        ->label('Enviar recordatorios automáticos')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('reminder_days_before')
                        ->label('Avisar con (días de anticipación)')
                        ->helperText('Desde cuántos días antes del vencimiento se avisa.')
                        ->numeric()->integer()->minValue(0)->maxValue(60)->required(),
                    Forms\Components\TextInput::make('reminder_days_overdue')
                        ->label('Seguir avisando hasta (días de atraso)')
                        ->helperText('Una vacuna vencida hace más días que esto ya no genera aviso.')
                        ->numeric()->integer()->minValue(0)->maxValue(365)->required(),
                    Forms\Components\TimePicker::make('reminder_time')
                        ->label('Hora de envío')
                        ->helperText('En la zona horaria de la clínica. Se aplica desde la próxima ejecución del programador.')
                        ->seconds(false)
                        ->format('H:i')
                        ->required(),
                ]),

            Forms\Components\Section::make('Textos de los mensajes')
                ->description("Marcadores disponibles: {$placeholders}.")
                ->schema([
                    Forms\Components\Textarea::make('reminder_email_message')
                        ->label('Texto del email')
                        ->placeholder(ClinicSettings::DEFAULT_REMINDER_EMAIL)
                        ->helperText('Déjalo vacío para usar el texto por defecto.')
                        ->rows(3),
                    Forms\Components\Textarea::make('reminder_whatsapp_message')
                        ->label('Texto de WhatsApp')
                        ->placeholder(ClinicSettings::DEFAULT_REMINDER_WHATSAPP)
                        ->helperText('Déjalo vacío para usar el texto por defecto.')
                        ->rows(3),
                ]),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function pdfTab(): array
    {
        return [
            Forms\Components\Section::make('Pie y firma')
                ->description('Se agregan a todos los PDFs generados (historia clínica, consultas, cirugías, pruebas, carnet y ficha).')
                ->schema([
                    Forms\Components\TextInput::make('pdf_footer')
                        ->label('Pie de página')
                        ->helperText('Una línea extra bajo la razón social y el RUC.')
                        ->maxLength(160),
                    Forms\Components\FileUpload::make('pdf_signature')
                        ->label('Firma o sello')
                        ->helperText('PNG, JPG o WebP. Se imprime al final del documento.')
                        ->image()
                        ->acceptedFileTypes($this->rasterTypes())
                        ->directory('clinic'),
                    Forms\Components\Textarea::make('pdf_legal_text')
                        ->label('Texto legal')
                        ->helperText('Aviso al final de cada documento (por ejemplo, validez o confidencialidad).')
                        ->rows(3)
                        ->maxLength(600),
                ]),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function listsTab(): array
    {
        $list = fn (string $name, string $label, string $help): Forms\Components\TagsInput => Forms\Components\TagsInput::make($name)
            ->label($label)
            ->helperText($help.' Escribe y presiona Enter para agregar; arrastra para reordenar.')
            ->reorderable()
            ->required()
            ->columnSpanFull();

        return [
            Forms\Components\Section::make('Opciones de los formularios')
                ->description('Quitar una opción no modifica los registros que ya la usan.')
                ->schema([
                    $list('vaccines', 'Vacunas', 'Opciones del campo «Vacuna».'),
                    $list('manufacturers', 'Fabricantes de vacunas', 'Opciones del campo «Fabricante».'),
                    $list('surgery_types', 'Tipos de cirugía', 'Se muestran en orden alfabético.'),
                    $list('test_types', 'Tipos de prueba laboratorial', 'Opciones del campo «Tipo» de las pruebas.'),
                ]),
        ];
    }

    public function save(): void
    {
        $settings = app(ClinicSettings::class);
        $settings->fill($this->form->getState());
        $settings->save();

        Notification::make()
            ->title('Datos de la clínica guardados')
            ->success()
            ->send();
    }
}
