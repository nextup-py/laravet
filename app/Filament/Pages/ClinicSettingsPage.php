<?php

namespace App\Filament\Pages;

use App\Settings\ClinicSettings;
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

    protected static ?string $navigationLabel = 'Datos de la clínica';

    protected static ?string $title = 'Datos de la clínica';

    protected static ?string $slug = 'clinic-settings';

    protected static string $view = 'filament.pages.clinic-settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(app(ClinicSettings::class)->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos básicos')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la clínica')
                            ->required(),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color primario del panel'),
                        Forms\Components\FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->directory('clinic'),
                        Forms\Components\FileUpload::make('favicon')
                            ->label('Favicon')
                            ->image()
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
                            ->tel(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),
                        Forms\Components\TextInput::make('business_hours')
                            ->label('Horario de atención'),
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
            ])
            ->statePath('data');
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
