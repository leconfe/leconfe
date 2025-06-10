<?php

namespace App\Panel\Administration\Resources\StaticPageResource\Pages;

use App\Panel\Administration\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStaticPage extends CreateRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->setMeta('contents', $data['contents']);
    }
}
