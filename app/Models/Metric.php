<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Metric extends Model
{
    use HasFactory, BelongsToConference, BelongsToScheduledConference, Cachable;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    protected $cast = [
        'log_at' => 'date',
    ];
    

    public static function purgeBySource(string $source)
    {
        return static::where('source', $source)->delete();
    }

    public function metricable(): MorphTo
    {
        return $this->morphTo('model');
    }
}
