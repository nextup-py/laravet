<?php

namespace App\Filament\Pages\Auth;

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
                            ->image()
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
