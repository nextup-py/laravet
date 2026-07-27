<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurgeryResource\Pages;
use App\Filament\Resources\SurgeryResource\RelationManagers;
use App\Models\Surgery;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SurgeryResource extends Resource
{
    protected static ?string $model = Surgery::class;
    protected static ?string $navigationGroup = 'Procedures';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'cirugía';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pet_id')
                    ->relationship('pet', 'name')
                    ->searchable(['name', 'id'])
                    ->preload()
                    ->live()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->native(false)
                    ->maxDate(now()),
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\Textarea::make('observation')
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('observation')->limit(50)
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

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
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
