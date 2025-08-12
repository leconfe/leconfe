<?php

namespace App\Actions\AuthorRoles;

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

            $authorRole = AuthorRole::create($data);

            if (data_get($data, 'meta')) {
                $authorRole->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $authorRole;
    }
}