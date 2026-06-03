<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class SubmissionFileType extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, HasFactory, SortableTrait;

    protected $fillable = [
        'name',
        'required',
        'scheduled_conference_id',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }
}
