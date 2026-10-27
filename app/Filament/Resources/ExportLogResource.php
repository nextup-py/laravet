<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExportLogResource\Pages;
use App\Models\ExportLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de exportaciones (solo lectura): quién sacó qué lista, cuántas filas y en qué formato.
 * Lo ve quien tiene el permiso `view_export_log` (admin, por ser Super Admin de Shield).
 */
class ExportLogResource extends Resource
{
    protected static ?string $model = ExportLog::class;

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'exportación';

    protected static ?string $pluralModelLabel = 'exportaciones';

    protected static ?string $navigationLabel = 'Registro de exportaciones';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_export_log') ?? false;
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

    public static function canView(Model $record): bool
    {
        return false;
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
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('list')
                    ->label('Lista')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rows')
                    ->label('Filas')
                    ->numeric(thousandsSeparator: '.', decimalSeparator: ',')
                    ->sortable(),
                Tables\Columns\TextColumn::make('format')
                    ->label('Formato')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\IconColumn::make('includes_owner_data')
                    ->label('Datos de propietarios')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('columns')
                    ->label('Columnas')
                    ->formatStateUsing(fn (array|string|null $state): string => is_array($state) ? count($state).' columnas' : '')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('list')
                    ->label('Lista')
                    ->options(fn (): array => ExportLog::query()->distinct()->orderBy('list')->pluck('list', 'list')->all()),
                Tables\Filters\TernaryFilter::make('includes_owner_data')
                    ->label('Datos de propietarios')
                    ->placeholder('Todas')
                    ->trueLabel('Con datos de propietarios')
                    ->falseLabel('Sin datos de propietarios'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExportLogs::route('/'),
        ];
    }
}
