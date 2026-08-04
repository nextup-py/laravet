<?php

use App\Filament\Widgets\RecentConsultationsWidget;
use App\Models\Consultation;
use Livewire\Livewire;

it('muestra la fecha de consulta, no la fecha de carga, en el widget de consultas recientes', function () {
    actingAsRole('veterinarian');
    Consultation::factory()->create(['consultation_date' => '2026-01-15']);

    $columns = Livewire::test(RecentConsultationsWidget::class)
        ->instance()
        ->getTable()
        ->getColumns();

    $names = array_map(fn ($column) => $column->getName(), $columns);

    expect($names)->toContain('consultation_date')
        ->not->toContain('created_at');
});
