<?php

namespace App\Actions\Authors;

use App\Models\AuthorRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthorRoleCreateAction
{
    use AsAction;

    public function handle(array $data): AuthorRole
    {
        try {
            DB::beginTransaction();

            $authorRole = new AuthorRole;
            $data = $authorRole->syncTranslatableState($data);
            $authorRole->fill($data)->save();

            $authorRole->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $authorRole;
    }
}
