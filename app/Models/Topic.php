<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Topic extends Model implements Sortable
{
    use BelongsToConference, BelongsToScheduledConference, Cachable, HasFactory, SortableTrait;

    protected $fillable = ['name', 'conference_id', 'order_column'];

    public function buildSortQuery()
    {
        return static::query()->where('scheduled_conference_id', $this->scheduled_conference_id);
    }

    public function submissions()
    {
        return $this->morphedByMany(Submission::class, 'topicable');
    }
}
