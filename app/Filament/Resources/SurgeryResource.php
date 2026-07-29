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
use Illuminate\Database\Eloquent\Model;

class SurgeryResource extends Resource
{
    protected static ?string $model = Surgery::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $modelLabel = 'cirugía';

    public static function typeOptions(): array
    {
        return [
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
            'Sutura de herida' => __('Wound Suture'),
            'Extracción dental' => __('Dental Extraction'),
            'Desobstrucción uretral' => __('Urethral Unblocking'),
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
                Tables\Columns\TextColumn::make('observation')
                    ->translateLabel()
                    ->limit(50)
                    ->searchable(),
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
            'index' => Pages\ManageSurgeries::route('/'),
        ];
    }
}
