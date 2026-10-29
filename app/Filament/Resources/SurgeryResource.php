<?php

namespace App\Filament\Resources;

use App\Enums\PetSpecies;
use App\Filament\Resources\SurgeryResource\Pages;
use App\Models\Pet;
use App\Models\Surgery;
use App\Settings\ClinicSettings;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Gestión de cirugías realizadas o programadas a las mascotas.
 */
class SurgeryResource extends Resource
{
    protected static ?string $model = Surgery::class;

    protected static ?string $slug = 'cirugias';

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'cirugía';

    protected static ?string $recordTitleAttribute = 'type';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? static::getGlobalSearchResultTitle($record) : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['type', 'observation', 'pet.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('pet');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->type} · {$record->pet->name}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Fecha' => Carbon::parse($record->date)->format('d/m/Y'),
            'Estado' => $record->status->getLabel(),
        ];
    }

    /**
     * Tipos de cirugía, ordenados alfabéticamente (sin distinguir acentos ni mayúsculas).
     * Los valores guardados no cambian: clave y etiqueta son siempre el mismo texto.
     */
    public static function typeOptions(): array
    {
        $types = array_values(ClinicSettings::catalog('surgery_types'));

        usort($types, fn (string $a, string $b): int => strcmp(Str::lower(Str::ascii($a)), Str::lower(Str::ascii($b))));

        return array_combine($types, $types);
    }

    /**
     * Resumen clínico de la mascota seleccionada y sus cirugías previas, para tener contexto al registrar.
     */
    protected static function petContext(?Pet $pet, ?Surgery $current): HtmlString
    {
        if (! $pet) {
            return new HtmlString('');
        }

        $previous = $pet->surgeries()
            ->when($current?->exists, fn (Builder $q) => $q->whereKeyNot($current->getKey()))
            ->latest('date')
            ->limit(3)
            ->get()
            ->map(fn (Surgery $s): string => '<li>'.e(Carbon::parse($s->date)->format('d/m/Y').': '.$s->type).'</li>')
            ->implode('');

        $list = $previous !== ''
            ? '<p class="mt-2 font-medium">Cirugías previas</p><ul class="list-disc pl-5">'.$previous.'</ul>'
            : '<p class="mt-2">Sin cirugías previas registradas.</p>';

        return new HtmlString('<div class="text-sm text-gray-600 dark:text-gray-300"><p class="font-medium">'.e($pet->summaryLine()).'</p>'.$list.'</div>');
    }

    /**
     * Campos del formulario, compartidos con la SurgeriesRelationManager de Pet
     * (que no incluye el selector de mascota porque ya está fijada por contexto).
     */
    public static function formFields(): array
    {
        return [
            Forms\Components\DatePicker::make('date')
                ->label('Fecha')
                ->helperText('Puede ser una fecha futura para programar la cirugía.')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->closeOnDateSelection(),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->searchable()
                ->preload()
                ->options(self::typeOptions())
                ->required(),
            Forms\Components\Textarea::make('observation')
                ->label('Observación')
                ->autosize()
                ->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información general')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('pet_id')
                            ->label('Mascota')
                            ->relationship('pet', 'name', modifyQueryUsing: fn (Builder $query, ?Surgery $record) => $query->where(
                                fn (Builder $q) => $q->where('active', true)
                                    ->when($record?->pet_id, fn (Builder $q, $petId) => $q->orWhere('id', $petId))
                            ))
                            ->default(fn () => request()->query('pet_id'))
                            ->searchable(['name', 'id'])
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\Placeholder::make('petContext')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => filled($get('pet_id')))
                            ->content(fn (Get $get, ?Surgery $record) => static::petContext(Pet::find($get('pet_id')), $record)),
                        ...self::formFields(),
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->description(fn (Surgery $record): ?string => OwnerResource::canViewAny() ? $record->pet->owner?->full_name : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Veterinario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('observation')
                    ->label('Observación')
                    ->limit(50)
                    ->tooltip(fn (Tables\Columns\TextColumn $column): ?string => strlen((string) $column->getState()) > 50 ? $column->getState() : null)
                    ->searchable(),
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
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(self::typeOptions())
                    ->multiple()
                    ->searchable(),
                Tables\Filters\Filter::make('date')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Hasta')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                        ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('date', '<=', $date))),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Veterinario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('pet_id')
                    ->label('Mascota')
                    ->relationship('pet', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? null,
                        fn (Builder $q, array $values) => $q->whereHas('pet', fn (Builder $p) => $p->whereIn('species', $values))
                    )),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('openCertificate')
                        ->label('Certificado PDF')
                        ->icon('heroicon-o-document-text')
                        ->authorize(fn (Surgery $record): bool => static::canView($record))
                        ->url(fn (Surgery $record): string => route('filament.admin.pdf.surgery', $record))
                        ->openUrlInNewTab(),
                    PetResource::viewPetAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurgeries::route('/'),
            'create' => Pages\CreateSurgery::route('/create'),
            'view' => Pages\ViewSurgery::route('/{record}'),
            'edit' => Pages\EditSurgery::route('/{record}/edit'),
        ];
    }
}
