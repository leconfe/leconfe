<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class SpeakerRole extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, HasFactory, SortableTrait;

    protected $table = 'speaker_roles';

    protected $fillable = [
        'scheduled_conference_id',
        'name',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (SpeakerRole $speakerRole) {
            $speakerRole->speakers->each->delete();
        });
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeByActiveSeries($query, $scheduledConferencesId)
    {
        return $query->where('scheduled_conference_id', $scheduledConferencesId)
            ->whereHas('speakers')
            ->with(['speakers' => ['media', 'meta']]);
    }
}
