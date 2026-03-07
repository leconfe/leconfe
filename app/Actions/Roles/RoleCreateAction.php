<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RoleCreateAction
{
    use AsAction;

    public function handle(array $data): Role
    {
        try {
            DB::beginTransaction();

            $data['conference_id'] ??= app()->getCurrentConferenceId();

            $role = Role::create($data);

            if ($meta = data_get($data, 'meta')) {
                $role->setManyMeta($meta);
            }

            if (isset($data['permissions'])) {
                $protectedPermissionContexts = Permission::getProtectedPermissionContexts();
                $data['permissions'] = collect($data['permissions'])
                    ->filter(fn($value, $permission) => !in_array(explode(':', $permission)[0], $protectedPermissionContexts))
                    ->keys()
                    ->toArray();

                $role->syncPermissions($data['permissions']);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $role;
    }
}
