<?php

namespace App\Providers\Filament;

use App\Settings\ClinicSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // La base de datos puede no estar disponible aún en este punto del arranque
        // (instalación nueva antes de migrar, composer install ejecutando
        // package:discover, CI sin base creada, o una migración de settings
        // pendiente tras agregar una propiedad nueva a ClinicSettings), así que
        // el panel cae a los valores por defecto ante cualquier fallo al leer
        // los settings, no solo tabla faltante.
        $brandName = config('app.name');
        $brandLogo = null;
        $favicon = null;
        $primaryColor = null;

        try {
            if (Schema::hasTable('settings')) {
                $settings = app(ClinicSettings::class);
                $brandName = $settings->name ?? $brandName;
                $brandLogo = $settings->logo ? Storage::url($settings->logo) : null;
                $favicon = $settings->favicon ? Storage::url($settings->favicon) : null;
                $primaryColor = $settings->primary_color;
            }
        } catch (\Throwable) {
            // Se mantienen los valores por defecto seteados arriba.
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->passwordReset()
            ->spa()
            ->profile(isSimple: false)
            ->sidebarCollapsibleOnDesktop()
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            ->brandName($brandName)
            ->brandLogo($brandLogo)
            ->favicon($favicon)
            ->colors([
                'primary' => $primaryColor ? Color::hex($primaryColor) : Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
