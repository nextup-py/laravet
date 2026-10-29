<?php

namespace App\Filament\Resources;

use App\Enums\ProblemReportStatus;
use App\Enums\ProblemReportType;
use App\Filament\Resources\ProblemReportResource\Pages;
use App\Models\ProblemReport;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Reportes de problemas del personal (ver «Ayuda → Reportar un problema»): solo lectura y seguimiento
 * (nuevo, visto, resuelto). Lo ve quien tiene el permiso `manage_problem_reports` (admin, por ser Super Admin).
 */
class ProblemReportResource extends Resource
{
    protected static ?string $model = ProblemReport::class;

    protected static ?string $slug = 'reportes-de-problemas';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'reporte de problema';

    protected static ?string $pluralModelLabel = 'reportes de problemas';

    protected static ?string $navigationLabel = 'Reportes de problemas';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage_problem_reports') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::new()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Reportes nuevos sin revisar';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('type')->label('Tipo')->badge(),
            Infolists\Components\TextEntry::make('status')->label('Estado')->badge(),
            Infolists\Components\TextEntry::make('user_name')->label('Reportó'),
            Infolists\Components\TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
            Infolists\Components\TextEntry::make('description')->label('Descripción')->columnSpanFull(),
            Infolists\Components\TextEntry::make('screen')->label('Pantalla')->placeholder('Sin indicar')->columnSpanFull(),
            Infolists\Components\TextEntry::make('user_agent')->label('Navegador')->placeholder('Sin indicar')->columnSpanFull(),
            Infolists\Components\TextEntry::make('app_version')->label('Versión del sistema')->placeholder('Sin indicar'),
            Infolists\Components\TextEntry::make('attachment')
                ->label('Captura')
                ->placeholder('Sin captura')
                ->formatStateUsing(fn (): string => 'Abrir captura')
                ->url(fn (ProblemReport $record): ?string => $record->attachment ? route('filament.admin.problem-reports.attachment', $record) : null, shouldOpenInNewTab: true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Reportó')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(80)
                    ->searchable(),
                Tables\Columns\TextColumn::make('screen')
                    ->label('Pantalla')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ProblemReportStatus::class),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ProblemReportType::class),
            ])
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make()->extraAttributes(['class' => 'hidden']),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('markSeen')
                        ->label('Marcar como visto')
                        ->icon('heroicon-o-eye')
                        ->visible(fn (ProblemReport $record): bool => $record->status === ProblemReportStatus::New)
                        ->action(fn (ProblemReport $record) => $record->update(['status' => ProblemReportStatus::Seen])),
                    Tables\Actions\Action::make('markResolved')
                        ->label('Marcar como resuelto')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn (ProblemReport $record): bool => $record->status !== ProblemReportStatus::Resolved)
                        ->action(fn (ProblemReport $record) => $record->update(['status' => ProblemReportStatus::Resolved])),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProblemReports::route('/'),
        ];
    }
}
