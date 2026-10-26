<?php

namespace App\Providers\Filament;

use App\Http\Controllers\PdfDocumentController;
use App\Http\Controllers\TestResultController;
use App\Settings\ClinicSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
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
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Historial clínico')
                    ->icon('heroicon-o-clipboard-document-list'),
                NavigationGroup::make()
                    ->label('Pacientes')
                    ->icon('heroicon-o-heart'),
                NavigationGroup::make()
                    ->label('Configuración')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])
            ->authenticatedRoutes(function () {
                Route::controller(PdfDocumentController::class)->group(function () {
                    Route::get('/mascotas/{pet}/historia-clinica.pdf', 'clinicalHistory')->name('pdf.clinical-history');
                    Route::get('/mascotas/{pet}/ficha.pdf', 'petIdCard')->name('pdf.pet-id-card');
                    Route::get('/mascotas/{pet}/carnet-vacunacion.pdf', 'vaccineCard')->name('pdf.vaccine-card');
                    Route::get('/consultas/{consultation}/informe.pdf', 'consultation')->name('pdf.consultation');
                    Route::get('/cirugias/{surgery}/certificado.pdf', 'surgery')->name('pdf.surgery');
                    Route::get('/pruebas/{test}/reporte.pdf', 'test')->name('pdf.test');
                });
                Route::get('/pruebas/{test}/resultados/{index}', TestResultController::class)
                    ->whereNumber('index')
                    ->name('tests.result');
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
