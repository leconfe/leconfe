<?php

namespace App\Actions\StaticPages;

use App\Models\StaticPage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StaticPageCreateAction
{
    use AsAction;

    public function handle(array $data)
    {
        try {
            DB::beginTransaction();

            $staticPage = new StaticPage;
            $data = $staticPage->syncTranslatableState($data);
            $staticPage->fill($data)->save();

            if (data_get($data, 'meta')) {
                $staticPage->setManyMeta(data_get($data, 'meta'));
            }

            $staticPage->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $staticPage;
    }
}
