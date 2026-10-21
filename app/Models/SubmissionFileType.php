<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasTranslatableContent;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class SubmissionFileType extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, HasFactory, HasTranslatableContent, SortableTrait;

    protected $fillable = [
        'name',
        'required',
        'scheduled_conference_id',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    /**
     * @var array<int, string>
     */
    protected array $translatableColumns = ['name'];

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }
}
