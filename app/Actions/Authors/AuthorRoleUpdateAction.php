<?php

namespace App\Actions\Authors;

use App\Models\AuthorRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthorRoleUpdateAction
{
    use AsAction;

    public function handle(AuthorRole $authorRole, array $data): AuthorRole
    {
        try {
            DB::beginTransaction();

            $data = $authorRole->syncTranslatableState($data);

            $authorRole->update($data);

            $authorRole->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $authorRole;
    }
}
