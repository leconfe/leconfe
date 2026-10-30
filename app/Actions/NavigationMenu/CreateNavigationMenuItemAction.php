<?php

namespace App\Actions\NavigationMenu;

use App\Models\NavigationMenuItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateNavigationMenuItemAction
{
    use AsAction;

    public function handle($data)
    {
        try {
            DB::beginTransaction();

            $navigationMenuItem = new NavigationMenuItem;
            $data = $navigationMenuItem->syncTranslatableState($data);
            $navigationMenuItem->fill($data)->save();
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
