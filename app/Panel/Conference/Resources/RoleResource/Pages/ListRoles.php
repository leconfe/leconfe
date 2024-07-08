<?php

namespace App\Panel\Conference\Resources\RoleResource\Pages;

use App\Panel\Conference\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    // protected static ?string $title = 'Role Management';


    public function getTitle(): string
    {
        return __('translation.roleResource.getModelLabel');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
