<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RoleUpdateAction
{
    use AsAction;

    public function handle(Role $role, array $data)
    {
        try {
            DB::beginTransaction();

            $role->update($data);

            if ($meta = data_get($data, 'meta')) {
                $role->setManyMeta($meta);
            }

            if (isset($data['permissions'])) {
                // filter out protected permissions
                $protectedPermissionContexts = Permission::getProtectedPermissionContexts();
                $data['permissions'] = collect($data['permissions'])
                    ->filter(fn ($value, $permission) => ! in_array(explode(':', $permission)[0], $protectedPermissionContexts))
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
