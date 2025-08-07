<?php

namespace App\Actions\Topics;

use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TopicCreateAction
{
    use AsAction;

    public function handle($data): Topic
    {
        try {
            DB::beginTransaction();

            $meta = $data['meta'] ?? [];
            unset($data['meta']);

            $topic = Topic::create($data);

            foreach ($meta as $key => $values) {
                if (is_array($values)) {
                    $topic->setMeta($key, $values);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $topic;
    }
}
