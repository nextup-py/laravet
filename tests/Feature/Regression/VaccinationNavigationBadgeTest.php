<?php

use App\Filament\Resources\VaccinationResource;
use App\Models\Vaccination;

it('el badge de navegación cuenta vacunas vencidas/próximas con el scope dedicado', function () {
    Vaccination::factory()->count(2)->proximaAVencer(3)->create();
    Vaccination::factory()->create(['next_application' => now()->addDays(30)]);

    expect(VaccinationResource::getNavigationBadge())
        ->toBe((string) Vaccination::upcomingOrOverdue()->count())
        ->toBe('2');
});
