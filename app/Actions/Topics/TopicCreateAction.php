<?php

namespace App\Actions\Topics;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TopicCreateAction
{
    use AsAction;

    public function handle(array $data): Topic
    {
        try {
            DB::beginTransaction();

            $topic = Topic::create($data);

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
