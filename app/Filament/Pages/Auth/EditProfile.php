<?php

namespace App\Filament\Pages\Auth;

use App\Enums\StaffAlert;
use App\Filament\Resources\UserResource;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

/**
 * Perfil del propio usuario: foto, datos personales, domicilio y acceso
 * (correo y contraseña, que exigen la contraseña actual). El rol no se
 * edita acá: lo gestiona un admin desde Usuarios.
 */
class EditProfile extends BaseEditProfile
{
    protected static ?string $slug = 'perfil';

    /**
     * Avisos que el usuario puede recibir según los permisos de su rol.
     *
     * @return array<int, StaffAlert>
     */
    private function availableAlerts(): array
    {
        $user = $this->getUser();

        return array_values(array_filter(
            StaffAlert::cases(),
            fn (StaffAlert $alert): bool => $user->can($alert->permission()),
        ));
    }

    /**
     * Muestra marcados los canales vigentes (por defecto, solo la campanita).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getUser();

        foreach ($this->availableAlerts() as $alert) {
            $data['notification_preferences'][$alert->value] = $user->notificationChannels($alert);
        }

        return $data;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos personales')
                    ->columns(2)
                    ->schema([
                        Forms\Components\FileUpload::make('avatar_url')
                            ->label('Foto de perfil')
                            ->avatar()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->imageEditor()
                            ->circleCropper()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        $this->getNameFormComponent(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(30),
                    ]),

                UserResource::locationSection(),

                Section::make('Notificaciones')
                    ->description('Elige qué avisos diarios recibes y por dónde: la campanita del panel y/o el correo. Solo aparecen los de los registros que tu rol puede ver.')
                    ->visible(fn (): bool => $this->availableAlerts() !== [])
                    ->schema(collect($this->availableAlerts())->map(
                        fn (StaffAlert $alert): Forms\Components\CheckboxList => Forms\Components\CheckboxList::make("notification_preferences.{$alert->value}")
                            ->label($alert->getLabel())
                            ->helperText($alert->description())
                            ->options(['database' => 'Campanita del panel', 'mail' => 'Correo electrónico'])
                            ->columns(2)
                    )->all()),

                Section::make('Acceso')
                    ->description('Para cambiar el correo o la contraseña debes confirmar tu contraseña actual.')
                    ->columns(2)
                    ->schema([
                        $this->getEmailFormComponent()
                            ->live(onBlur: true),
                        Forms\Components\TextInput::make('current_password')
                            ->label('Contraseña actual')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->currentPassword()
                            ->required(fn (Get $get): bool => filled($get('password'))
                                || strtolower((string) $get('email')) !== strtolower($this->getUser()->email))
                            ->dehydrated(false),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ]),
            ])
            ->operation('edit')
            ->model($this->getUser())
            ->statePath('data')
            ->inlineLabel(! static::isSimple());
    }
}
