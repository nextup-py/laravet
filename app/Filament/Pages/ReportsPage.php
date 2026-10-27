<?php

namespace App\Filament\Pages;

use App\Enums\PetSpecies;
use App\Filament\Actions\DownloadExport;
use App\Models\ExportLog;
use App\Models\SavedReport;
use App\Models\User;
use App\Reports\FixedReport;
use App\Reports\ReportEntity;
use App\Reports\ReportResult;
use App\Services\PdfGeneratorService;
use App\Support\TabularDownload;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes: una lista de reportes fijos (solo piden el rango de fechas) y un constructor
 * simple de reportes personalizados (tipo de registro, fechas, filtros y columnas), con
 * descarga en Excel, CSV y PDF. Cada descarga queda en el registro de exportaciones.
 */
class ReportsPage extends Page implements HasForms
{
    use InteractsWithForms;

    /** Filas que se muestran en pantalla; la descarga trae todas. */
    private const PREVIEW_ROWS = 100;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $title = 'Reportes';

    protected static ?string $slug = 'reportes';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.reports-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<int, array<int, mixed>> */
    public array $preview = [];

    public int $total = 0;

    public ?string $resultTitle = null;

    public ?string $resultSubtitle = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_reports') ?? false;
    }

    public function mount(): void
    {
        $entity = $this->availableEntities()[0] ?? null;

        $this->form->fill([
            'report' => ($this->availableReports()[0] ?? null)?->value ?? 'custom',
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'entity' => $entity?->value,
            'columns' => $entity ? array_keys($entity->columnOptionsFor($this->user())) : [],
        ]);
    }

    private function user(): User
    {
        return auth()->user();
    }

    /**
     * @return array<int, FixedReport>
     */
    private function availableReports(): array
    {
        return array_values(array_filter(
            FixedReport::cases(),
            fn (FixedReport $report): bool => $report->isAvailableTo($this->user()),
        ));
    }

    /**
     * @return array<int, ReportEntity>
     */
    private function availableEntities(): array
    {
        return array_values(array_filter(
            ReportEntity::cases(),
            fn (ReportEntity $entity): bool => $this->user()->can($entity->permission()),
        ));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Reporte')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('report')
                            ->label('Tipo de reporte')
                            ->options(fn (): array => array_filter([
                                'Reportes fijos' => collect($this->availableReports())->mapWithKeys(fn (FixedReport $report): array => [$report->value => $report->label()])->all(),
                                'Personalizado' => $this->availableEntities() === [] ? [] : ['custom' => 'Reporte personalizado'],
                            ]))
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->clearResult())
                            ->required(),
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->visible(fn (Get $get): bool => $this->usesDateRange($get('report')))
                            ->required(),
                        Forms\Components\DatePicker::make('to')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from')
                            ->visible(fn (Get $get): bool => $this->usesDateRange($get('report')))
                            ->required(),
                        Forms\Components\Placeholder::make('description')
                            ->label('')
                            ->content(fn (Get $get): ?string => FixedReport::tryFrom((string) $get('report'))?->description())
                            ->visible(fn (Get $get): bool => $get('report') !== 'custom')
                            ->columnSpanFull(),
                    ]),

                Section::make('Reporte personalizado')
                    ->description('Elige qué registros incluir, cómo filtrarlos y qué columnas ver. Puedes guardar la configuración para repetirla después.')
                    ->columns(3)
                    ->visible(fn (Get $get): bool => $get('report') === 'custom')
                    ->schema([
                        Forms\Components\Select::make('saved')
                            ->label('Reportes guardados')
                            ->placeholder('Cargar un reporte guardado…')
                            ->options(fn (): array => $this->user()->savedReports()->orderBy('name')->pluck('name', 'id')->all())
                            ->live()
                            ->afterStateUpdated(fn (?string $state, Set $set) => $this->loadSaved($state, $set))
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('entity')
                            ->label('Registros')
                            ->options(fn (): array => collect($this->availableEntities())->mapWithKeys(fn (ReportEntity $entity): array => [$entity->value => $entity->label()])->all())
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $entity = ReportEntity::tryFrom((string) $state);
                                $set('columns', $entity ? array_keys($entity->columnOptionsFor($this->user())) : []);
                                $this->clearResult();
                            })
                            ->required(),
                        Forms\Components\Select::make('species')
                            ->label('Especie')
                            ->placeholder('Todas')
                            ->options(PetSpecies::class),
                        Forms\Components\Select::make('vet_id')
                            ->label('Veterinario')
                            ->placeholder('Todos')
                            ->options(fn (): array => User::query()->whereHas('roles')->orderBy('name')->pluck('name', 'id')->all())
                            ->visible(fn (): bool => ! $this->user()->seesOnlyOwnRecords()),
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Columnas')
                            ->options(fn (Get $get): array => ReportEntity::tryFrom((string) $get('entity'))?->columnOptionsFor($this->user()) ?? [])
                            ->columns(3)
                            ->bulkToggleable()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    private function usesDateRange(?string $report): bool
    {
        return $report === 'custom' || (FixedReport::tryFrom((string) $report)?->usesDateRange() ?? false);
    }

    private function clearResult(): void
    {
        $this->headers = [];
        $this->preview = [];
        $this->total = 0;
        $this->resultTitle = null;
        $this->resultSubtitle = null;
    }

    private function loadSaved(?string $id, Set $set): void
    {
        $saved = $id ? $this->user()->savedReports()->find($id) : null;

        if (! $saved || ! in_array($saved->entity, $this->availableEntities(), true)) {
            return;
        }

        $allowed = array_keys($saved->entity->columnOptionsFor($this->user()));

        $set('entity', $saved->entity->value);
        $set('columns', array_values(array_intersect($allowed, $saved->config['columns'] ?? [])));
        $set('species', $saved->config['species'] ?? null);
        $set('vet_id', $saved->config['vet_id'] ?? null);
        $this->clearResult();
    }

    /**
     * Arma el reporte con lo que hay en el formulario, o null (avisando) si no se puede.
     */
    private function build(): ?ReportResult
    {
        $state = $this->form->getState();
        $user = $this->user();

        $from = isset($state['from']) ? Carbon::parse($state['from']) : today();
        $to = isset($state['to']) ? Carbon::parse($state['to']) : today();

        if ($state['report'] !== 'custom') {
            $report = FixedReport::tryFrom($state['report']);

            abort_unless($report && $report->isAvailableTo($user), 403);

            return $report->generate($from, $to, $user);
        }

        $entity = ReportEntity::from($state['entity']);
        abort_unless($user->can($entity->permission()), 403);

        $columns = collect($entity->columns())
            ->only(array_intersect(array_keys($entity->columnOptionsFor($user)), $state['columns'] ?? []));

        if ($columns->isEmpty()) {
            $this->warn('Elige al menos una columna.');

            return null;
        }

        $query = $entity->query($from, $to, ['species' => $state['species'] ?? null, 'vet_id' => $state['vet_id'] ?? null], $user);
        $total = $query->toBase()->getCountForPagination();

        if ($total > DownloadExport::$maxRows) {
            $this->warn('El reporte tiene '.Number::format($total).' filas y el máximo es '.Number::format(DownloadExport::$maxRows).'. Acota las fechas o agrega filtros.');

            return null;
        }

        $rows = $query->get()
            ->map(fn ($record): array => $columns->map(fn (array $column) => $column['value']($record))->values()->all())
            ->all();

        return new ReportResult(
            $entity->label().' (personalizado)',
            $columns->pluck('label')->values()->all(),
            $rows,
            includesOwnerData: $columns->contains(fn (array $column): bool => $column['owner']),
            subtitle: 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y').' · por '.$entity->dateLabel(),
        );
    }

    private function warn(string $message): void
    {
        Notification::make()->title('No se pudo generar el reporte')->body($message)->warning()->send();
    }

    public function generate(): void
    {
        $result = $this->build();

        if (! $result) {
            $this->clearResult();

            return;
        }

        $this->headers = $result->headers;
        $this->preview = array_slice($result->rows, 0, self::PREVIEW_ROWS);
        $this->total = count($result->rows);
        $this->resultTitle = $result->title;
        $this->resultSubtitle = $result->subtitle;
    }

    private function download(string $format): ?StreamedResponse
    {
        abort_unless($this->user()->can('export_data'), 403);

        $result = $this->build();

        if (! $result) {
            return null;
        }

        ExportLog::create([
            'user_id' => $this->user()->getKey(),
            'user_name' => $this->user()->name,
            'list' => 'Reporte: '.$result->title,
            'rows' => count($result->rows),
            'format' => $format,
            'columns' => $result->headers,
            'includes_owner_data' => $result->includesOwnerData,
        ]);

        $name = Str::slug($result->title).'-'.now()->format('Y-m-d');

        return match ($format) {
            'csv' => TabularDownload::csv($name.'.csv', $result->headers, TabularDownload::protectFormulas($result->rows)),
            'xlsx' => TabularDownload::xlsx($name.'.xlsx', $result->headers, TabularDownload::protectFormulas($result->rows)),
            default => response()->streamDownload(
                fn () => print (app(PdfGeneratorService::class)->output(
                    'pdf.report',
                    ['report' => $result, 'generatedBy' => $this->user()->name],
                    count($result->headers) > 6 ? 'landscape' : 'portrait',
                )),
                $name.'.pdf',
                ['Content-Type' => 'application/pdf'],
            ),
        };
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $canExport = fn (): bool => $this->user()->can('export_data');

        return [
            Action::make('saveReport')
                ->label('Guardar configuración')
                ->icon('heroicon-o-bookmark')
                ->color('gray')
                ->visible(fn (): bool => ($this->data['report'] ?? null) === 'custom')
                ->modalHeading('Guardar reporte personalizado')
                ->modalDescription('Se guardan los registros, filtros y columnas (no las fechas). Si ya tienes uno con ese nombre, se reemplaza.')
                ->modalSubmitActionLabel('Guardar')
                ->form([
                    Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(80),
                ])
                ->action(function (array $data): void {
                    $state = $this->form->getRawState();

                    $this->user()->savedReports()->updateOrCreate(
                        ['name' => $data['name']],
                        [
                            'entity' => $state['entity'],
                            'config' => [
                                'columns' => array_values($state['columns'] ?? []),
                                'species' => $state['species'] ?? null,
                                'vet_id' => $state['vet_id'] ?? null,
                            ],
                        ],
                    );

                    Notification::make()->title('Reporte guardado')->success()->send();
                }),
            Action::make('deleteSaved')
                ->label('Eliminar guardado')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => ($this->data['report'] ?? null) === 'custom' && filled($this->data['saved'] ?? null))
                ->requiresConfirmation()
                ->modalHeading('Eliminar reporte guardado')
                ->action(function (): void {
                    SavedReport::query()
                        ->whereBelongsTo($this->user())
                        ->whereKey($this->data['saved'])
                        ->delete();

                    $this->data['saved'] = null;
                    Notification::make()->title('Reporte eliminado')->success()->send();
                }),
            Action::make('downloadXlsx')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->visible($canExport)
                ->action(fn (): ?StreamedResponse => $this->download('xlsx')),
            Action::make('downloadCsv')
                ->label('CSV')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible($canExport)
                ->action(fn (): ?StreamedResponse => $this->download('csv')),
            Action::make('downloadPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible($canExport)
                ->action(fn (): ?StreamedResponse => $this->download('pdf')),
        ];
    }
}
