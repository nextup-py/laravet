<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Concerns\OpensViewFirst;
use App\Filament\Resources\RoleResource\Pages;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Forms\ShieldSelectAllToggle;
use BezhanSalleh\FilamentShield\Support\Utils;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource implements HasShieldPermissions
{
    use HasShieldFormComponents, OpensViewFirst;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Título del rol en migas, fichas y búsquedas: la etiqueta en español de los roles base.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? ClinicRoles::label($record->name) : null;
    }

    /**
     * Etiquetas de los permisos personalizados en español (lang/es/permissions.php). Shield solo
     * ofrece el nombre del permiso o su versión "headline" en inglés, por eso se sobrescribe aquí.
     * Un permiso nuevo sin traducción cae al nombre legible que genera Shield.
     */
    public static function getCustomPermissionOptions(): ?array
    {
        return FilamentShield::getCustomPermissions()
            ->reject(fn (string $permission): bool => static::isUnusedResourcePermission($permission))
            ->mapWithKeys(fn (string $permission): array => [
                $permission => Lang::has("permissions.custom.{$permission}")
                    ? __("permissions.custom.{$permission}")
                    : str($permission)->headline()->toString(),
            ])
            ->toArray();
    }

    /**
     * Permisos de recurso que el sistema no usa (no hay papelera, replicar ni reordenar): siguen en la
     * base de datos, pero Shield los mostraría como «personalizados» al quitarlos de los prefijos.
     */
    protected static function isUnusedResourcePermission(string $permission): bool
    {
        return (bool) preg_match('/^(restore|restore_any|replicate|reorder|force_delete|force_delete_any)_/', $permission);
    }

    /**
     * Igual que el de Shield, pero con el título del recurso en minúscula salvo la primera letra
     * («Reporte de problema» y no «Reporte De Problema»).
     *
     * @return array<int, Forms\Components\Section>|null
     */
    public static function getResourceEntitiesSchema(): ?array
    {
        return collect(FilamentShield::getResources())
            ->sortKeys()
            ->map(fn (array $entity): Forms\Components\Section => Forms\Components\Section::make(
                str($entity['fqcn']::getModelLabel())->ucfirst()->toString()
            )
                ->description(fn () => new HtmlString('<span style="word-break: break-word;">'.Utils::showModelPath($entity['fqcn']).'</span>'))
                ->compact()
                ->schema([static::getCheckBoxListComponentForResource($entity)])
                ->columnSpan(static::shield()->getSectionColumnSpan())
                ->collapsible())
            ->values()
            ->toArray();
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('filament-shield::filament-shield.field.name'))
                                    ->unique(
                                        ignoreRecord: true, /** @phpstan-ignore-next-line */
                                        modifyRuleUsing: fn (Unique $rule) => Utils::isTenancyEnabled() ? $rule->where(Utils::getTenantModelForeignKey(), Filament::getTenant()?->id) : $rule
                                    )
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn (?Model $record): bool => $record !== null && ClinicRoles::isBase($record->name))
                                    ->helperText(fn (?Model $record): ?string => $record !== null && ClinicRoles::isBase($record->name)
                                        ? 'El nombre de un rol base no se puede cambiar: el sistema lo usa para dar acceso. Sí puedes editar sus permisos.'
                                        : null),

                                Forms\Components\Hidden::make('guard_name')
                                    ->default(Utils::getFilamentAuthGuard()),

                                Forms\Components\Select::make(config('permission.column_names.team_foreign_key'))
                                    ->label(__('filament-shield::filament-shield.field.team'))
                                    ->placeholder(__('filament-shield::filament-shield.field.team.placeholder'))
                                    /** @phpstan-ignore-next-line */
                                    ->default([Filament::getTenant()?->id])
                                    ->options(fn (): Arrayable => Utils::getTenantModel() ? Utils::getTenantModel()::pluck('name', 'id') : collect())
                                    ->hidden(fn (): bool => ! (static::shield()->isCentralApp() && Utils::isTenancyEnabled()))
                                    ->dehydrated(fn (): bool => ! (static::shield()->isCentralApp() && Utils::isTenancyEnabled())),
                                ShieldSelectAllToggle::make('select_all')
                                    ->onIcon('heroicon-s-shield-check')
                                    ->offIcon('heroicon-s-shield-exclamation')
                                    ->label(__('filament-shield::filament-shield.field.select_all.name'))
                                    ->helperText(fn (): HtmlString => new HtmlString(__('filament-shield::filament-shield.field.select_all.message')))
                                    ->dehydrated(fn (bool $state): bool => $state)
                                    ->hidden(fn (?Model $record): bool => $record?->name === ClinicRoles::ADMIN),

                                Forms\Components\Placeholder::make('allPermissions')
                                    ->hiddenLabel()
                                    ->visible(fn (?Model $record): bool => $record?->name === ClinicRoles::ADMIN)
                                    ->content('El administrador tiene todos los permisos del sistema; no hace falta marcarlos uno por uno.')
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('roleUsers')
                                    ->label('Usuarios con este rol')
                                    ->visible(fn (?Model $record, string $operation): bool => $record !== null && $operation === 'view')
                                    ->content(fn (?Model $record): string => $record?->users()->orderBy('name')->pluck('name')->join(', ') ?: 'Ninguno')
                                    ->columnSpanFull(),

                            ])
                            ->columns([
                                'sm' => 2,
                                'lg' => 3,
                            ]),
                    ]),
                static::getShieldFormComponents()
                    ->hidden(fn (?Model $record): bool => $record?->name === ClinicRoles::ADMIN),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight('font-medium')
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->formatStateUsing(fn ($state): string => ClinicRoles::isBase($state) ? ClinicRoles::label($state) : Str::headline($state))
                    ->description(fn (Model $record): ?string => ClinicRoles::isBase($record->name) ? $record->name : null)
                    ->searchable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge()
                    ->color('warning')
                    ->label(__('filament-shield::filament-shield.column.guard_name')),
                Tables\Columns\TextColumn::make('team.name')
                    ->default('Global')
                    ->badge()
                    ->color(fn (mixed $state): string => str($state)->contains('Global') ? 'gray' : 'primary')
                    ->label(__('filament-shield::filament-shield.column.team'))
                    ->searchable()
                    ->visible(fn (): bool => static::shield()->isCentralApp() && Utils::isTenancyEnabled()),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->badge()
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->colors(['success']),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplicar')
                        ->icon('heroicon-o-document-duplicate')
                        ->visible(fn (): bool => static::canCreate())
                        ->form([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre del rol nuevo')
                                ->required()
                                ->maxLength(255)
                                ->unique('roles', 'name', modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('guard_name', 'web')),
                        ])
                        ->modalHeading(fn (Model $record): string => "Duplicar el rol «{$record->name}»")
                        ->modalDescription('Se crea un rol nuevo con los mismos permisos; luego puedes ajustarlos.')
                        ->action(function (Model $record, array $data) {
                            $copy = Role::create(['name' => $data['name'], 'guard_name' => $record->guard_name]);
                            $copy->syncPermissions($record->permissions);

                            Notification::make()->title('Rol duplicado')->success()->send();

                            return redirect(static::getUrl('edit', ['record' => $copy]));
                        }),
                ]),
            ]);
    }

    /**
     * Motivo por el que un rol no puede eliminarse, o null si puede. Los roles base los usa el
     * sistema (el seeder y `ClinicRoles`) y eliminar uno con usuarios los dejaría sin acceso.
     */
    public static function deletionBlockedReason(Model $role): ?string
    {
        if (ClinicRoles::isBase($role->name)) {
            return 'Es un rol base del sistema.';
        }

        if ($role->users()->exists()) {
            return 'Hay usuarios con este rol asignado. Cámbiales el rol primero.';
        }

        return null;
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getCluster(): ?string
    {
        return Utils::getResourceCluster() ?? static::$cluster;
    }

    public static function getModel(): string
    {
        return Utils::getRoleModel();
    }

    public static function getModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.roles');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Utils::isResourceNavigationRegistered();
    }

    public static function getNavigationGroup(): ?string
    {
        return Utils::isResourceNavigationGroupEnabled()
            ? __('filament-shield::filament-shield.nav.group')
            : '';
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-shield::filament-shield.nav.role.label');
    }

    public static function getNavigationIcon(): string
    {
        return __('filament-shield::filament-shield.nav.role.icon');
    }

    public static function getNavigationSort(): ?int
    {
        return Utils::getResourceNavigationSort();
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return Utils::getSubNavigationPosition() ?? static::$subNavigationPosition;
    }

    public static function getSlug(): string
    {
        return Utils::getResourceSlug();
    }

    public static function getNavigationBadge(): ?string
    {
        return Utils::isResourceNavigationBadgeEnabled()
            ? strval(static::getEloquentQuery()->count())
            : null;
    }

    public static function isScopedToTenant(): bool
    {
        return Utils::isScopedToTenant();
    }

    public static function canGloballySearch(): bool
    {
        return Utils::isResourceGloballySearchable() && count(static::getGloballySearchableAttributes()) && static::canViewAny();
    }
}
