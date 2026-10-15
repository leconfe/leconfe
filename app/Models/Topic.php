<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasTranslatableContent;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Topic extends Model implements Sortable
{
    use BelongsToConference, BelongsToScheduledConference, Cachable, HasFactory, HasTranslatableContent, SortableTrait;

    protected $fillable = ['name', 'conference_id', 'order_column'];

    /**
     * Columns whose content can be translated.
     *
     * @var array<int, string>
     */
    protected array $translatableColumns = ['name'];

    public function buildSortQuery()
    {
        return static::query()->where('scheduled_conference_id', $this->scheduled_conference_id);
    }

    public function submissions()
    {
        return $this->morphedByMany(Submission::class, 'topicable');
    }
}
