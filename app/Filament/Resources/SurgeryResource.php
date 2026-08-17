<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurgeryResource\Pages;
use App\Models\Surgery;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gestión de cirugías realizadas a las mascotas.
 */
class SurgeryResource extends Resource
{
    protected static ?string $model = Surgery::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $modelLabel = 'cirugía';

    public static function typeOptions(): array
    {
        return [
            'Esterilización' => 'Esterilización',
            'Ovariohisterectomía' => 'Ovariohisterectomía',
            'Castración' => 'Castración',
            'Cirugía Dental' => 'Cirugía Dental',
            'Extracción de Tumores' => 'Extracción de Tumores',
            'Cirugía de Cuerpo Extraño' => 'Cirugía de Cuerpo Extraño',
            'Reparación de Fracturas' => 'Reparación de Fracturas',
            'Cesárea' => 'Cesárea',
            'Amputación' => 'Amputación',
            'Enucleación Ocular' => 'Enucleación Ocular',
            'Cirugía de Ligamento Cruzado' => 'Cirugía de Ligamento Cruzado',
            'Gastropexia Preventiva' => 'Gastropexia Preventiva',
            'Desungulación' => 'Desungulación',
            'Herniorrafia' => 'Herniorrafia',
            'Laparotomía Exploratoria' => 'Laparotomía Exploratoria',
            'Onicectomía' => 'Onicectomía',
            'Cistotomía' => 'Cistotomía',
            'Uretrostomía Perineal' => 'Uretrostomía Perineal',
            'Esplenectomía' => 'Esplenectomía',
            'Tiroidectomía' => 'Tiroidectomía',
            'Cirugía de Luxación de Rótula' => 'Cirugía de Luxación de Rótula',
            'Toracotomía' => 'Toracotomía',
            'Resección Intestinal' => 'Resección Intestinal',
            'Cirugía de Glándulas Perianales' => 'Cirugía de Glándulas Perianales',
            'Sutura de herida' => 'Sutura de herida',
            'Extracción dental' => 'Extracción dental',
            'Desobstrucción uretral' => 'Desobstrucción uretral',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información general')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('pet_id')
                            ->label('Pet id')
                            ->relationship('pet', 'name', modifyQueryUsing: fn (Builder $query, ?Surgery $record) => $query->where(
                                fn (Builder $q) => $q->where('active', true)
                                    ->when($record?->pet_id, fn (Builder $q, $petId) => $q->orWhere('id', $petId))
                            ))
                            ->searchable(['name', 'id'])
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->label('Fecha')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->maxDate(now()),
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(self::typeOptions())
                            ->required(),
                        Forms\Components\Textarea::make('observation')
                            ->label('Observación')
                            ->autosize()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('observation')
                    ->label('Observación')
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurgeries::route('/'),
            'create' => Pages\CreateSurgery::route('/create'),
            'view' => Pages\ViewSurgery::route('/{record}'),
            'edit' => Pages\EditSurgery::route('/{record}/edit'),
        ];
    }
}
