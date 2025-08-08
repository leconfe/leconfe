<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\LocalizedMetable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class CommitteeRole extends Model implements Sortable
{
    use BelongsToScheduledConference, HasFactory, SortableTrait, LocalizedMetable;

    protected $table = 'committee_roles';

    protected $fillable = [
        'scheduled_conference_id',
        'parent_id',
        'name',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (CommitteeRole $committeeRole) {
            $committeeRole->committees->each->delete();
        });
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeByActiveSeries($query, $scheduledConferencesId)
    {
        return $query->where('scheduled_conference_id', $scheduledConferencesId)
            ->whereHas('committees')
            ->with(['committees' => ['media', 'meta']]);
    }
}
