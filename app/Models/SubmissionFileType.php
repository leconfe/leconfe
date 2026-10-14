<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasBuiltinLabel;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class SubmissionFileType extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, HasBuiltinLabel, HasFactory, SortableTrait;

    public static function builtinNames(): array
    {
        return [
            'abstract' => 'Abstract',
            'full_paper' => 'Full Paper',
            'poster' => 'Poster',
            'other' => 'Other',
            'research_instrument' => 'Research Instrument',
            'research_material' => 'Research Material',
            'research_result' => 'Research Result',
            'transcripts' => 'Transcripts',
            'data_analysis' => 'Data Analysis',
            'data_set' => 'Data Set',
            'source_text' => 'Source Text',
        ];
    }

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
