<?php

namespace App\Panel\Administration\Resources\StaticPageResource\Pages;

use App\Panel\Administration\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->setMeta('blocks', $data['blocks']);
    }
    

    protected function fillForm(): void
    {
        /** @internal Read the DocBlock above the following method. */
        $this->fillFormWithDataAndCallHooks($this->getRecord(), [
            'blocks' => $this->getRecord()->getMeta('blocks'),
        ]);
    }

}
