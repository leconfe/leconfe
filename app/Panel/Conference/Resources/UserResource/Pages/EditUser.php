<?php

namespace App\Panel\Conference\Resources\UserResource\Pages;

use App\Actions\User\UserDeleteAction;
use App\Actions\User\UserUpdateAction;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function afterSave(): void {}

    protected function getHeaderActions(): array
    {
        return [
            Action::make('remove')
                ->color('danger')
                ->icon('heroicon-m-trash')
                ->requiresConfirmation()
                ->authorize('delete')
                ->action(function (User $record, Action $action) {
                    $result = $record->syncRoles([]);

                    if (! $result) {
                        $action->failure();

                        return;
                    }

                    $action->success();
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return UserUpdateAction::run($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['meta'] = $this->getRecord()->getAllMeta()->toArray();

        return $data;
    }
}
