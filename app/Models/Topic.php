<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use ArrayAccess;
use Plank\Metable\Metable;

class Topic extends Model
{
    use BelongsToConference, BelongsToScheduledConference, Cachable, HasFactory, Metable;

    protected $fillable = ['name', 'conference_id'];

    public function submissions()
    {
        return $this->morphedByMany(Submission::class, 'topicable');
    }

    public function scheduledConferences()
    {
        return $this->morphedByMany(ScheduledConference::class, 'topicable');
    }

    public function scopeWebsiteTopics($query)
    {
        return $query->whereHas('meta', function ($q) {
            $q->where('key', 'type')
                ->where('value', 'website');
        });
    }

    /**
     * Find existing topics or create them.
     * Accepts single name/id/Topic or array of those values.
     * Returns array of Topic instances.
     *
     * @param  mixed  $topics
     * @param  string|null  $type (unused, kept for compatibility)
     * @return array<int, Topic>
     */
    public static function findOrCreate(mixed $topics, ?string $type = null): array
    {
        $items = [];

        if ($topics instanceof Topic) {
            return [$topics];
        }

        if ($topics instanceof ArrayAccess || is_array($topics)) {
            $items = is_array($topics) ? $topics : iterator_to_array($topics);
        } else {
            $items = [$topics];
        }

        $results = [];

        foreach ($items as $item) {
            if ($item instanceof Topic) {
                $results[] = $item;
                continue;
            }

            // numeric id
            if (is_numeric($item)) {
                $found = static::withoutGlobalScopes()->find($item);
                if ($found) {
                    $results[] = $found;
                }
                continue;
            }

            // array with name
            if (is_array($item) && isset($item['name'])) {
                $name = (string) $item['name'];
            } else {
                $name = (string) $item;
            }

            $query = static::withoutGlobalScopes()->where('name', $name);

            // Try to narrow by current conference if available
            if (app()->has('currentConferenceId') || method_exists(app(), 'getCurrentConferenceId')) {
                try {
                    $confId = app()->getCurrentConferenceId();
                } catch (\Throwable $e) {
                    $confId = null;
                }

                if ($confId) {
                    $query->where('conference_id', $confId);
                }
            }

            $found = $query->first();

            if (!$found) {
                $found = static::create([
                    'name' => $name,
                    'conference_id' => app()->getCurrentConferenceId() ?: null,
                ]);
            }

            $results[] = $found;
        }

        return $results;
    }
}
