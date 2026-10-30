<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
