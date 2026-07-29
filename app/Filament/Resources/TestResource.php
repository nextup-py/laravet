<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Concerns\HasClinicResourceAuthorization;
use App\Filament\Resources\TestResource\Pages;
use App\Models\Test;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Gestión de pruebas laboratoriales realizadas a las mascotas.
 */
class TestResource extends Resource
{
    use HasClinicResourceAuthorization;

    protected static ?string $model = Test::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $modelLabel = 'prueba laboratorial';

    protected static ?string $pluralModelLabel = 'pruebas laboratoriales';

    protected static function createRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    protected static function editRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    public static function typeOptions(): array
    {
        return [
            'Hematología' => 'Hematology',
            'Bioquímica' => 'Biochemistry',
            'Inmunología' => 'Immunology',
            'Microbiología' => 'Microbiology',
            'Parasitología' => 'Parasitology',
            'Uroanálisis' => 'Urinalysis',
            'Coprología' => 'Fecal Analysis',
            'Cultivo' => 'Culture',
            'Prueba Rápida' => 'Rapid Test',
            'PCR' => 'PCR',
            'Serología' => 'Serology',
            'Otros' => 'Others',
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
                            ->relationship('pet', 'name')
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
                        Forms\Components\FileUpload::make('result')
                            ->label('Resultados')
                            ->multiple()
                            ->maxParallelUploads(1)
                            ->panelLayout('grid')
                            ->openable()
                            ->downloadable()
                            ->uploadingMessage('Subiendo archivo adjunto...')
                            ->maxFiles(5)
                            ->columnSpanFull()
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
                Tables\Columns\TextColumn::make('result')
                    ->label('Resultados')
                    ->getStateUsing(function (Test $record) {
                        $files = $record->result;

                        return is_array($files) && count($files) > 0 ? count($files).' archivo(s)' : '—';
                    }),
                Tables\Columns\TextColumn::make('observation')
                    ->label('Observación')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado a las')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado a las')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ManageTests::route('/'),
        ];
    }
}
