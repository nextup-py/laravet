<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Models\Pet;
use App\Settings\ClinicSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VaccinationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vaccinations';

    protected static ?string $modelLabel = 'vacunación';

    protected static ?string $title = 'Vacunaciones';

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
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->maxDate(now()),
                Forms\Components\DatePicker::make('next_application')
                    ->translateLabel()
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
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
                Forms\Components\Textarea::make('observation')
                    ->translateLabel()
                    ->autosize(),
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
                Tables\Columns\TextColumn::make('vaccine')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_date')
                    ->translateLabel()
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_application')
                    ->translateLabel()
                    ->date('d/m/Y')
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
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $query) => $query->where('next_application', '<', now())),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Próximas (7 días)')
                    ->query(fn (Builder $query) => $query->whereBetween('next_application', [now(), now()->addDays(7)])),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    }),
                Tables\Actions\Action::make('downloadVaccineCard')
                    ->label(__('Descargar PDF'))
                    ->color('success')
                    ->action(function () {
                        $pet = $this->getOwnerRecord(); // Obtenemos el Pet desde el contexto padre

                        if (! $pet) {
                            throw new \Exception('Mascota no encontrada');
                        }

                        return response()->streamDownload(
                            function () use ($pet) {
                                echo Pdf::loadView('pdf.vaccine-card', [
                                    'pet' => $pet,
                                    'vaccinations' => $pet->vaccinations, // Accedemos a la relación
                                    'clinic' => app(ClinicSettings::class),
                                ])->stream();
                            },
                            "Carnet-Vacunacion-{$pet->name}.pdf"
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Generar carnet de vacunación')
                    ->modalDescription('¿Desea descargar el PDF con el historial completo?'),
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
