<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Track extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, Metable, SortableTrait;

    protected $fillable = [
        'title',
        'abbreviation',
        'scheduled_conference_id',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Track $track) {
            if ($track->submissions()->exists()) {
                throw new \Exception('Before this track can be deleted, you must move paper submitted to it into other track');
            }
        });
    }

    public function buildSortQuery()
    {
        return static::query()->where('scheduled_conference_id', $this->scheduled_conference_id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'do_not_require_abstract' => false,
            'abstract_word_count' => 0,
            'submit_only_for_editors' => false,
            'hide_author' => false,
            'track_editors' => [],
        ];
    }
}
