<?php

namespace App\Panel\Conference\Resources\UserResource\Pages;

use App\Actions\User\CreateParticipantFromUserAction;
use App\Actions\User\UserDeleteAction;
use App\Actions\User\UserUpdateAction;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function afterSave(): void
    {
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
            ->using(function (?array $data, User $record, DeleteAction $action){
                try {
                    $user = UserDeleteAction::run($data, $record);
                    return $user;
                } catch (\Throwable $th) {
                    $action->failureNotificationTitle($th->getMessage());

                    return false;
                }
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
