<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class StakeholderLevel extends Model implements HasMedia, Sortable
{
    use BelongsToConference, BelongsToScheduledConference, Cachable, HasFactory, InteractsWithMedia, SortableTrait;

    public const TYPE_SPONSOR = 1;

    public const TYPE_PARTNER = 2;

    protected $fillable = [
        'conference_id',
        'scheduled_conference_id',
        'type',
        'name',
        'description',
    ];

    protected $casts = [
        'type' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (StakeholderLevel $stakeholderLevel) {
            $stakeholderLevel->stakeholders->each->delete();
        });
    }

    public function scopeSponsors($query)
    {
        return $query->where('type', self::TYPE_SPONSOR);
    }

    public function scopePartners($query)
    {
        return $query->where('type', self::TYPE_PARTNER);
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(Stakeholder::class, 'level_id');
    }

    public function buildSortQuery()
    {
        return static::query()
            ->where('scheduled_conference_id', $this->scheduled_conference_id);
    }
}
