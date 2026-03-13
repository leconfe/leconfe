<?php

namespace App\Models\Concerns;

use App\Models\Topic;
use ArrayAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTopics
{
    public static function bootHasTopics()
    {
        static::deleted(function (Model $deletedModel) {
            $topics = $deletedModel->topics()->get();

            $deletedModel->detachTopics($topics);
        });
    }

    public function topics(): MorphToMany
    {
        return $this
            ->morphToMany(Topic::class, 'topicable');
    }

    public function attachTopics(array|ArrayAccess|Topic $topics, ?string $type = null): static
    {
        $topics = collect(Topic::findOrCreate($topics, $type));

        $this->topics()->syncWithoutDetaching($topics->pluck('id')->toArray());

        return $this;
    }

    public function attachTopic(string|Topic $topic, ?string $type = null)
    {
        return $this->attachTopics([$topic], $type);
    }

    public function detachTopics($topics): static
    {
        collect($topics)
            ->filter()
            ->each(fn(Topic $topic) => $this->topics()->detach($topic));

        return $this;
    }
}
