<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ConsultationResource;
use App\Models\Consultation;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentConsultationsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_consultation') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Consultas recientes')
            ->emptyStateHeading('Todavía no hay consultas registradas')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->query(
                Consultation::query()
                    ->with(['pet', 'user'])
                    ->latest('consultation_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota'),
                Tables\Columns\TextColumn::make('diagnosis')
                    ->label('Diagnóstico')
                    ->limit(60),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Veterinario'),
                Tables\Columns\TextColumn::make('consultation_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->recordUrl(fn (Consultation $record) => ConsultationResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
