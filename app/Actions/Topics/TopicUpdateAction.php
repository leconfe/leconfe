<?php

namespace App\Actions\Topics;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TopicUpdateAction
{
    use AsAction;

    public function handle(Topic $topic, array $data): Topic
    {
        try {
            DB::beginTransaction();

            $topic->update($data);

            if (data_get($data, 'meta')) {
                $topic->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $topic;
    }
}
