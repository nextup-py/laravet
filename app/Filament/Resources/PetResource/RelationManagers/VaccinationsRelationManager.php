<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Filament\Resources\VaccinationResource;
use App\Services\PdfGeneratorService;
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vaccine')
                    ->label('Vacuna')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->options(VaccinationResource::vaccineOptions())
                    ->required(),
                Forms\Components\DatePicker::make('application_date')
                    ->label('Fecha de aplicación')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->maxDate(now()),
                Forms\Components\DatePicker::make('next_application')
                    ->label('Próxima aplicación')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
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
                Forms\Components\Textarea::make('observation')
                    ->label('Observación')
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
                    ->label('Vacuna')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_date')
                    ->label('Fecha de aplicación')
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_application')
                    ->label('Próxima aplicación')
                    ->date('d/m/Y')
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
                    ->label('Descargar PDF')
                    ->color('success')
                    ->action(function (PdfGeneratorService $pdfGenerator) {
                        $pet = $this->getOwnerRecord(); // Obtenemos el Pet desde el contexto padre

                        if (! $pet) {
                            throw new \Exception('Mascota no encontrada');
                        }

                        return $pdfGenerator->download(
                            'pdf.vaccine-card',
                            ['pet' => $pet, 'vaccinations' => $pet->vaccinations],
                            "Carnet-Vacunacion-{$pet->name}.pdf",
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Generar carnet de vacunación')
                    ->modalDescription('¿Desea descargar el PDF con el historial completo?'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
