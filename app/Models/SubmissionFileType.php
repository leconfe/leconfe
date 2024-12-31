<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\SortableTrait;

class SubmissionFileType extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory, SortableTrait;

    protected $fillable = [
        'name',
        'scheduled_conference_id',
    ];

    public function files() : HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }
}
