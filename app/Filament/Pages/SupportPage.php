<?php

namespace App\Filament\Pages;

use App\Settings\ClinicSettings;
use Filament\Pages\Page;

/**
 * Contacto con soporte del sistema: muestra el correo configurado en «Datos de la clínica»
 * y un botón que abre el correo con el asunto listo. La ve cualquier usuario del panel.
 */
class SupportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Soporte';

    protected static ?string $title = 'Contacto con soporte';

    protected static ?string $slug = 'soporte';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.support-page';

    public function getSupportEmail(): string
    {
        return ClinicSettings::tryGet()?->support_email ?: ClinicSettings::DEFAULT_SUPPORT_EMAIL;
    }

    public function getMailtoUrl(): string
    {
        $user = auth()->user();
        $subject = 'Consulta sobre el sistema de la clínica';
        $body = "Hola, soy {$user?->name}.\n\nPantalla donde ocurre: \nQué estaba haciendo: \nQué esperaba que pasara: \n";

        return 'mailto:'.$this->getSupportEmail().'?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
    }
}
