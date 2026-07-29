<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestResource\Pages;
use App\Models\Test;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TestResource extends Resource
{
    protected static ?string $model = Test::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $modelLabel = 'prueba laboratorial';

    protected static ?string $pluralModelLabel = 'pruebas laboratoriales';

    public static function typeOptions(): array
    {
        return [
            'Hematología' => __('Hematology'),
            'Bioquímica' => __('Biochemistry'),
            'Inmunología' => __('Immunology'),
            'Microbiología' => __('Microbiology'),
            'Parasitología' => __('Parasitology'),
            'Uroanálisis' => __('Urinalysis'),
            'Coprología' => __('Fecal Analysis'),
            'Cultivo' => __('Culture'),
            'Prueba Rápida' => __('Rapid Test'),
            'PCR' => __('PCR'),
            'Serología' => __('Serology'),
            'Otros' => __('Others'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('General information'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('pet_id')
                            ->translateLabel()
                            ->relationship('pet', 'name')
                            ->searchable(['name', 'id'])
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->translateLabel()
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->maxDate(now()),
                        Forms\Components\Select::make('type')
                            ->translateLabel()
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
                            ->translateLabel()
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
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->translateLabel()
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('result')
                    ->label('Resultados')
                    ->getStateUsing(function (Test $record) {
                        $files = $record->result;

                        return is_array($files) && count($files) > 0 ? count($files).' archivo(s)' : '—';
                    }),
                Tables\Columns\TextColumn::make('observation')
                    ->translateLabel()
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTests::route('/'),
        ];
    }
}
