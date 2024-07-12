<?php

namespace App\Panel\Conference\Resources\StaticPageResource\Pages;

use App\Actions\StaticPages\StaticPageCreateAction;
use App\Models\StaticPage;
use App\Panel\Conference\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaticPages extends ListRecords
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(fn(StaticPage $record, array $data) => StaticPageCreateAction::run($record, $data)),
        ];
    }
}
