<?php

namespace App\Panel\Conference\Resources\StaticPageResource\Pages;

use App\Actions\StaticPages\StaticPageUpdateAction;
use App\Panel\Conference\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('view')
                ->icon('heroicon-o-eye')
                ->label(__('translation.button.preview'))
                ->color('success')
                ->url(fn ($record) => $record->getUrl()),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return StaticPageUpdateAction::run($record, $data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;

        $data['author'] = $user ? $user->full_name : 'Cannot find the author';
        $data['common_tags'] = $this->record->tags()->pluck('id')->toArray();
        $data['meta'] = $this->record->getAllMeta();

        return $data;
    }
}
