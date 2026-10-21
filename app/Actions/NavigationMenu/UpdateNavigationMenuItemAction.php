<?php

namespace App\Actions\NavigationMenu;

use App\Models\NavigationMenuItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateNavigationMenuItemAction
{
    use AsAction;

    public function handle(NavigationMenuItem $navigationMenuItem, array $data)
    {
        try {
            DB::beginTransaction();

            $data = $navigationMenuItem->syncTranslatableState($data);

            $navigationMenuItem->update($data);
            if (data_get($data, 'meta')) {
                $navigationMenuItem->setManyMeta(data_get($data, 'meta'));
            }

            $navigationMenuItem->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $navigationMenuItem;
    }
}
