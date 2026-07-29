<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Concerns\HasClinicResourceAuthorization;
use App\Filament\Resources\VaccinationResource\Pages;
use App\Models\Vaccination;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gestión de vacunaciones aplicadas a las mascotas.
 */
class VaccinationResource extends Resource
{
    use HasClinicResourceAuthorization;

    protected static ?string $model = Vaccination::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'vacunación';

    protected static ?string $pluralModelLabel = 'vacunaciones';

    protected static function createRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    protected static function editRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::upcomingOrOverdue()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('next_application', '<', now())->exists() ? 'danger' : 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vaccine')
                    ->label('Vacuna')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->options([
                        'Rabia' => 'Rabia',
                        'Moquillo' => 'Distemper',
                        'Parvovirus' => 'Parvovirus',
                        'Adenovirus' => 'Adenovirus',
                        'Leptospirosis' => 'Leptospirosis',
                        'Parainfluenza' => 'Parainfluenza',
                        'Bordetella' => 'Bordetella',
                        'Leucemia Felina' => 'Feline Leukemia',
                        'Panleucopenia' => 'Panleukopenia',
                        'Calicivirus' => 'Calicivirus',
                        'Rinotraqueítis Felina' => 'Feline Herpesvirus',
                        'Triple Felina' => 'FVRCP Vaccine',
                        'Lyme' => 'Lyme Disease',
                        'Gripe Canina' => 'Influenza canina',
                        'Tos de las Perreras' => 'Kennel Cough',
                        'Coronavirus Canino' => 'Canine Coronavirus',
                        'Giardia' => 'Giardia',
                        'Rabia Recombinante' => 'Recombinant Rabies',
                        'Vacuna Antirrábica' => 'Anti-Rabies Vaccine',
                        'Herpesvirus Equino' => 'Equine Herpesvirus',
                        'Mixomatosis' => 'Myxomatosis',
                        'Enfermedad Hemorrágica Vírica' => 'Viral Haemorrhagic Disease',
                        'Vacuna Polivalente' => 'Polyvalent Vaccine',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('application_date')
                    ->label('Fecha de aplicación')
                    ->required()
                    ->native(false)
                    ->maxDate(now()),
                Forms\Components\DatePicker::make('next_application')
                    ->label('Próxima aplicación')
                    ->required()
                    ->native(false)
                    ->minDate(now()),
                Forms\Components\TextInput::make('batch')
                    ->label('Lote')
                    ->string()
                    ->required(),
                Forms\Components\Select::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->options([
                        'Zoetis' => 'Zoetis',
                        'MSD' => 'MSD',
                        'Elanco' => 'Elanco',
                        'Boehringer Ingelheim' => 'Boehringer Ingelheim',
                        'Merial' => 'Merial',
                        'Virbac' => 'Virbac',
                        'Ceva' => 'Ceva',
                        'Heska' => 'Heska',
                        'Bayer' => 'Bayer',
                        'Vetoquinol' => 'Vetoquinol',
                        'Phibro' => 'Phibro',
                        'Hipra' => 'Hipra',
                        'Biogénesis Bagó' => 'Biogénesis Bagó',
                        'Bioiberica' => 'Bioiberica',
                        'Syva' => 'Syva',
                        'IDT Biologika' => 'IDT Biologika',
                        'VECOL' => 'VECOL',
                        'Karnov' => 'Karnov',
                        'Labiana' => 'Labiana',
                    ])
                    ->required(),
                Forms\Components\Select::make('pet_id')
                    ->label('Pet id')
                    ->relationship('pet', 'name')
                    ->searchable(['name', 'id'])
                    ->preload()
                    ->live()
                    ->required(),
                Forms\Components\Textarea::make('observation')
                    ->label('Observación')
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
                    ->label('Mascota')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vaccine')
                    ->label('Vacuna')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_date')
                    ->label('Fecha de aplicación')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_application')
                    ->label('Próxima aplicación')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('batch')
                    ->label('Lote')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado a las')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado a las')
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVaccinations::route('/'),
            'create' => Pages\CreateVaccination::route('/create'),
            'view' => Pages\ViewVaccination::route('/{record}'),
            'edit' => Pages\EditVaccination::route('/{record}/edit'),
        ];
    }
}
