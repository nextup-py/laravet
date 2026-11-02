<?php

namespace App\Filament\Actions;

use App\Enums\PetSize;
use App\Enums\PetWeightSource;
use App\Filament\Resources\PetResource;
use App\Models\Pet;
use App\Models\PetWeight;
use App\Support\PetSizeSuggestion;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Auth;

/**
 * Acción «Registrar peso»: agrega un pesaje a la mascota sin pasar por una consulta. El peso actual de
 * la mascota pasa a ser el del pesaje más reciente y, si el peso sugiere otro tamaño, se ofrece cambiarlo
 * (nunca se cambia solo).
 */
final class RegisterWeight
{
    /** Para el menú de la fila de una tabla. */
    public static function forTable(): TableAction
    {
        return self::configure(TableAction::make('registerWeight'));
    }

    /** Para el encabezado de la ficha (el registro es el de la página). */
    public static function forPage(): PageAction
    {
        return self::configure(PageAction::make('registerWeight'));
    }

    private static function configure(TableAction|PageAction $action): TableAction|PageAction
    {
        return $action
            ->label('Registrar peso')
            ->icon('heroicon-o-scale')
            ->modalHeading('Registrar peso')
            ->modalSubmitActionLabel('Guardar peso')
            ->visible(fn (Pet $record): bool => $record->active && PetResource::canEdit($record))
            ->form(fn (Pet $record): array => [
                Forms\Components\DatePicker::make('weighed_at')
                    ->label('Fecha del pesaje')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->maxDate(today())
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('weight')
                    ->label('Peso')
                    ->suffix('kg')
                    ->numeric()
                    ->inputMode('decimal')
                    ->minValue(0.1)
                    ->maxValue(150)
                    ->helperText("Peso actual: {$record->weight} kg.")
                    ->live(onBlur: true)
                    ->required(),
                Forms\Components\Placeholder::make('sizeHint')
                    ->hiddenLabel()
                    ->visible(fn (Get $get): bool => self::suggestedSize($record, $get) !== null)
                    ->content(fn (Get $get): string => 'Con '.$get('weight').' kg se propone el tamaño «'
                        .self::suggestedSize($record, $get)?->getLabel().'» (hoy: «'.$record->size->getLabel().'»).'),
                Forms\Components\Toggle::make('update_size')
                    ->label(fn (Get $get): string => 'Cambiar el tamaño a «'.self::suggestedSize($record, $get)?->getLabel().'»')
                    ->visible(fn (Get $get): bool => self::suggestedSize($record, $get) !== null)
                    ->default(false),
            ])
            ->action(function (Pet $record, array $data): void {
                PetWeight::create([
                    'pet_id' => $record->getKey(),
                    'weight' => $data['weight'],
                    'weighed_at' => $data['weighed_at'],
                    'user_id' => Auth::id(),
                    'source' => PetWeightSource::Weighing,
                ]);

                $record->refresh();

                if (($data['update_size'] ?? false) && ($size = PetSizeSuggestion::for($record))) {
                    $record->update(['size' => $size]);
                }

                Notification::make()
                    ->title('Peso registrado')
                    ->body("{$record->name} pesa {$record->weight} kg.")
                    ->success()
                    ->send();
            });
    }

    /**
     * El tamaño que se propondría, solo si el pesaje será el más reciente (es el que pasa a ser el peso actual).
     */
    private static function suggestedSize(Pet $pet, Get $get): ?PetSize
    {
        $weight = $get('weight');
        $date = $get('weighed_at');

        if (! is_numeric($weight) || blank($date)) {
            return null;
        }

        $latest = $pet->weights()->max('weighed_at');

        if ($latest && $date < substr((string) $latest, 0, 10)) {
            return null;
        }

        return PetSizeSuggestion::differingFor($pet, $weight);
    }
}
