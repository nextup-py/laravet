<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Filament\Resources\VaccinationResource;
use App\Models\Vaccination;
use App\Services\PdfGeneratorService;
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
        return $form->schema(VaccinationResource::formFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withRenewedFlag())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
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
                    ->label('Registrada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                VaccinationResource::statusFilter(),
                Tables\Filters\SelectFilter::make('vaccine')
                    ->label('Vacuna')
                    ->options(VaccinationResource::vaccineOptions())
                    ->multiple()
                    ->searchable(),
            ])
            ->defaultSort('application_date', 'desc')
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
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make()->extraAttributes(['class' => 'hidden']),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('openRecord')
                        ->label('Abrir ficha')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->visible(fn (Vaccination $record): bool => VaccinationResource::canView($record))
                        ->url(fn (Vaccination $record): string => VaccinationResource::getUrl('view', ['record' => $record])),
                    Tables\Actions\EditAction::make()
                        ->extraModalFooterActions([
                            Tables\Actions\DeleteAction::make()
                                ->cancelParentActions(),
                        ]),
                    VaccinationResource::revaccinateAction(),
                    VaccinationResource::whatsappReminderAction(),
                ]),
            ]);
    }
}
