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

            $data = $topic->syncTranslatableState($data);

            $topic->update($data);

            $topic->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $topic;
    }
}
