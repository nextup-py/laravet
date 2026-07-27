<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SurgeriesRelationManager extends RelationManager
{
    protected static string $relationship = 'surgeries';
    protected static ?string $modelLabel = 'cirugía';
    protected static ?string $title = 'Cirugías';

    public function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function canEditAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->options([
                        'Esterilización' => __('Sterilization'),
                        'Ovariohisterectomía' => __('Ovariohysterectomy'),
                        'Castración' => __('Neutering'),
                        'Cirugía Dental' => __('Dental Surgery'),
                        'Extracción de Tumores' => __('Tumor Removal'),
                        'Cirugía de Cuerpo Extraño' => __('Foreign Body Surgery'),
                        'Reparación de Fracturas' => __('Fracture Repair'),
                        'Cesárea' => __('Cesarean Section'),
                        'Amputación' => __('Amputation'),
                        'Enucleación Ocular' => __('Eye Enucleation'),
                        'Cirugía de Ligamento Cruzado' => __('Cruciate Ligament Surgery'),
                        'Gastropexia Preventiva' => __('Prophylactic Gastropexy'),
                        'Desungulación' => __('Declawing'),
                        'Herniorrafia' => __('Hernia Repair'),
                        'Laparotomía Exploratoria' => __('Exploratory Laparotomy'),
                        'Onychectomía' => __('Onychectomy'),
                        'Cistotomía' => __('Cystotomy'),
                        'Uretrostomía Perineal' => __('Perineal Urethrostomy'),
                        'Esplenectomía' => __('Splenectomy'),
                        'Tiroidectomía' => __('Thyroidectomy'),
                        'Cirugía de Luxación de Rótula' => __('Patellar Luxation Surgery'),
                        'Toracotomía' => __('Thoracotomy'),
                        'Resección Intestinal' => __('Intestinal Resection'),
                        'Cirugía de Glándulas Perianales' => __('Perianal Gland Surgery'),
                    ])
                    ->required(),
                Forms\Components\Textarea::make('observation')
                    ->translateLabel()
                    ->autosize()
                    ->columnSpanFull()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('date')
                    ->translateLabel()
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
