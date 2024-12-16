<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UserDeleteAction
{
    use AsAction;

    public function handle($data, User $user)
    {
        try {
            DB::beginTransaction();

            $user->delete($data);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $user;
    }
}
