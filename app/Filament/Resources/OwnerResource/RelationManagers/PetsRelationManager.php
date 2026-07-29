<?php

namespace App\Filament\Resources\OwnerResource\RelationManagers;

use App\Enums\PetGender;
use App\Enums\PetReproductionStatus;
use App\Enums\PetSize;
use App\Enums\PetSpecies;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PetsRelationManager extends RelationManager
{
    protected static string $relationship = 'pets';

    protected static ?string $modelLabel = 'mascota';

    protected static ?string $title = 'Mascotas asociadas';

    public function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public function canEditAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('ID information'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('ID')
                            ->hiddenOn('create')
                            ->readOnly(),
                        Forms\Components\TextInput::make('name')
                            ->translateLabel()
                            ->string()
                            ->required(),
                        Forms\Components\Select::make('species')
                            ->translateLabel()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(PetSpecies::class)
                            ->required(),
                        Forms\Components\TextInput::make('breed')
                            ->translateLabel()
                            ->string()
                            ->required(),
                        Forms\Components\DatePicker::make('birthdate')
                            ->translateLabel()
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->minDate(now()->subYears(25))
                            ->maxDate(now()),
                        Forms\Components\Radio::make('gender')
                            ->translateLabel()
                            ->required()
                            ->options(PetGender::class)
                            ->inline()
                            ->inlineLabel(false),
                        Forms\Components\Select::make('reproduction')
                            ->translateLabel()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(PetReproductionStatus::class)
                            ->required(),
                        Forms\Components\Toggle::make('active')
                            ->translateLabel()
                            ->hiddenOn('create')
                            ->onColor('success')
                            ->offColor('danger')
                            ->inline(false),
                    ]),

                Section::make(__('More information'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('size')
                            ->translateLabel()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(PetSize::class)
                            ->required(),
                        Forms\Components\TextInput::make('weight')
                            ->translateLabel()
                            ->suffix('kg')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(0.1)
                            ->maxValue(150),
                        Forms\Components\TextInput::make('fur')
                            ->label(__('Pelage'))
                            ->string()
                            ->required(),
                        Forms\Components\FileUpload::make('image')
                            ->translateLabel()
                            ->image()
                            ->imageEditor()
                            ->downloadable()
                            ->columnSpanFull()
                            ->uploadingMessage('Subiendo archivo adjunto...')
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->translateLabel()
                    ->circular(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('species')
                    ->translateLabel()
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->translateLabel()
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    }),
            ])
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
}
