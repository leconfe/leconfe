<?php

namespace App\Panel\Administration\Resources\StaticPageResource\Pages;

use App\Models\StaticPage;
use App\Panel\Administration\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected static ?string $navigationGroup = 'Pages';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')
                ->url(fn(StaticPage $record) => $record->getUrl()),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        $this->getRecord()->setMeta('contents', $data['contents']);
    }
    

    protected function fillForm(): void
    {
        /** @internal Read the DocBlock above the following method. */
        $this->fillFormWithDataAndCallHooks($this->getRecord(), [
            'contents' => $this->getRecord()->getMeta('contents'),
        ]);
    }

}
