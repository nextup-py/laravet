<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VaccinationResource\Pages;
use App\Models\Vaccination;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VaccinationResource extends Resource
{
    protected static ?string $model = Vaccination::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'vacunación';

    protected static ?string $pluralModelLabel = 'vacunaciones';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vaccine')
                    ->translateLabel()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->options([
                        'Rabia' => __('Rabies'),
                        'Moquillo' => __('Distemper'),
                        'Parvovirus' => __('Parvovirus'),
                        'Adenovirus' => __('Adenovirus'),
                        'Leptospirosis' => __('Leptospirosis'),
                        'Parainfluenza' => __('Parainfluenza'),
                        'Bordetella' => __('Bordetella'),
                        'Leucemia Felina' => __('Feline Leukemia'),
                        'Panleucopenia' => __('Panleukopenia'),
                        'Calicivirus' => __('Calicivirus'),
                        'Rinotraqueítis Felina' => __('Feline Herpesvirus'),
                        'Triple Felina' => __('FVRCP Vaccine'),
                        'Lyme' => __('Lyme Disease'),
                        'Gripe Canina' => __('Canine Influenza'),
                        'Tos de las Perreras' => __('Kennel Cough'),
                        'Coronavirus Canino' => __('Canine Coronavirus'),
                        'Giardia' => __('Giardia'),
                        'Rabia Recombinante' => __('Recombinant Rabies'),
                        'Vacuna Antirrábica' => __('Anti-Rabies Vaccine'),
                        'Herpesvirus Equino' => __('Equine Herpesvirus'),
                        'Mixomatosis' => __('Myxomatosis'),
                        'Enfermedad Hemorrágica Vírica' => __('Viral Haemorrhagic Disease'),
                        'Vacuna Polivalente' => __('Polyvalent Vaccine'),
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('application_date')
                    ->translateLabel()
                    ->required()
                    ->native(false)
                    ->maxDate(now()),
                Forms\Components\DatePicker::make('next_application')
                    ->translateLabel()
                    ->required()
                    ->native(false)
                    ->minDate(now()),
                Forms\Components\TextInput::make('batch')
                    ->translateLabel()
                    ->string()
                    ->required(),
                Forms\Components\Select::make('manufacturer')
                    ->translateLabel()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->options([
                        'Zoetis' => __('Zoetis'),
                        'MSD' => __('MSD'),
                        'Elanco' => __('Elanco'),
                        'Boehringer Ingelheim' => __('Boehringer Ingelheim'),
                        'Merial' => __('Merial'),
                        'Virbac' => __('Virbac'),
                        'Ceva' => __('Ceva'),
                        'Heska' => __('Heska'),
                        'Bayer' => __('Bayer'),
                        'Vetoquinol' => __('Vetoquinol'),
                        'Phibro' => __('Phibro'),
                        'Hipra' => __('Hipra'),
                        'Biogénesis Bagó' => __('Biogénesis Bagó'),
                        'Bioiberica' => __('Bioiberica'),
                        'Syva' => __('Syva'),
                        'IDT Biologika' => __('IDT Biologika'),
                        'VECOL' => __('VECOL'),
                        'Karnov' => __('Karnov'),
                        'Labiana' => __('Labiana'),
                    ])
                    ->required(),
                Forms\Components\Select::make('pet_id')
                    ->translateLabel()
                    ->relationship('pet', 'name')
                    ->searchable(['name', 'id'])
                    ->preload()
                    ->live()
                    ->required(),
                Forms\Components\Textarea::make('observation')
                    ->translateLabel()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('pet.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vaccine')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_date')
                    ->translateLabel()
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_application')
                    ->translateLabel()
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('batch')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer')
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $query) => $query->where('next_application', '<', now())),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Próximas (7 días)')
                    ->query(fn (Builder $query) => $query->whereBetween('next_application', [now(), now()->addDays(7)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->selectCurrentPageOnly();
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVaccinations::route('/'),
            'create' => Pages\CreateVaccination::route('/create'),
            'edit' => Pages\EditVaccination::route('/{record}/edit'),
        ];
    }
}
