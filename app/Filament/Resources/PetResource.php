<?php

namespace App\Filament\Resources;

use App\Enums\PetGender;
use App\Enums\PetReproductionStatus;
use App\Enums\PetSize;
use App\Enums\PetSpecies;
use App\Filament\Resources\PetResource\Pages;
use App\Filament\Resources\PetResource\RelationManagers;
use App\Models\Pet;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Gestión de mascotas y su historial clínico asociado.
 */
class PetResource extends Resource
{
    protected static ?string $model = Pet::class;

    protected static ?string $navigationGroup = 'Pacientes';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'mascota';

    /**
     * Schema del formulario, compartido con la PetsRelationManager de Owner
     * (que lo usa tal cual, sin el campo owner_id porque ya está fijado por contexto).
     */
    public static function formSchema(): array
    {
        return [
            Section::make('Datos identificatorios')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('id')
                        ->label('ID')
                        ->hiddenOn('create')
                        ->readOnly(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->string()
                        ->required(),
                    Forms\Components\Select::make('species')
                        ->label('Especie')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(PetSpecies::class)
                        ->required(),
                    Forms\Components\TextInput::make('breed')
                        ->label('Raza')
                        ->string()
                        ->required(),
                    Forms\Components\DatePicker::make('birthdate')
                        ->label('Fecha de nacimiento')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->minDate(now()->subYears(25))
                        ->maxDate(now()),
                    Forms\Components\Radio::make('gender')
                        ->label('Género')
                        ->required()
                        ->options(PetGender::class)
                        ->inline()
                        ->inlineLabel(false),
                    Forms\Components\Select::make('reproduction')
                        ->label('Reproducción')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(PetReproductionStatus::class)
                        ->required(),
                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->hiddenOn('create')
                        ->onColor('success')
                        ->offColor('danger')
                        ->inline(false),
                ]),

            Section::make('Más información')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('size')
                        ->label('Tamaño')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(PetSize::class)
                        ->required(),
                    Forms\Components\TextInput::make('weight')
                        ->label('Peso')
                        ->suffix('kg')
                        ->required()
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0.1)
                        ->maxValue(150),
                    Forms\Components\TextInput::make('fur')
                        ->label('Pelaje')
                        ->string()
                        ->required(),
                    Forms\Components\FileUpload::make('image')
                        ->label('Imagen')
                        ->image()
                        ->imageEditor()
                        ->downloadable()
                        ->columnSpanFull()
                        ->uploadingMessage('Subiendo archivo adjunto...')
                        ->required(),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        $schema = self::formSchema();

        $schema[0] = $schema[0]->schema([
            ...$schema[0]->getChildComponents(),
            Forms\Components\Select::make('owner_id')
                ->label('Owner id')
                ->relationship('owner', 'first_name')
                ->searchable(['first_name', 'ci'])
                ->preload()
                ->live()
                ->required(),
        ]);

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Imagen')
                    ->circular(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('species')
                    ->label('Especie')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label('Raza')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('age')
                    ->label('Edad')
                    ->getStateUsing(fn (Pet $record) => $record->age),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner.first_name')
                    ->label('Propietario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Género')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size')
                    ->label('Tamaño')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reproduction')
                    ->label('Reproducción')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
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
            ->filters([
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('size')
                    ->label('Tamaño')
                    ->options(PetSize::class),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Género')
                    ->options(PetGender::class),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado'),
            ])
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
            ])->selectCurrentPageOnly();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VaccinationsRelationManager::class,
            RelationManagers\ConsultationsRelationManager::class,
            RelationManagers\SurgeriesRelationManager::class,
            RelationManagers\TestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPets::route('/'),
            'create' => Pages\CreatePet::route('/create'),
            'view' => Pages\ViewPet::route('/{record}'),
            'edit' => Pages\EditPet::route('/{record}/edit'),
        ];
    }
}
