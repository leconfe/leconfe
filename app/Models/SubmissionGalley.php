<?php

namespace App\Models;

use App\Frontend\Conference\Pages\PaperGalley;
use App\Models\Concerns\HasMetrics;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SubmissionGalley extends Model implements HasMedia, Sortable
{
    use Cachable, InteractsWithMedia, SortableTrait, HasMetrics;

    protected $table = 'submission_galleys';

    protected $fillable = [
        'label',
        'remote_url',
        'submission_id',
        'submission_file_id',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function file()
    {
        return $this->belongsTo(SubmissionFile::class, 'submission_file_id');
    }

    public function getUrl()
    {
        return $this->remote_url ?? route(PaperGalley::getRouteName('conference'), ['galley' => $this->id, 'submission' => $this->submission_id]);
    }

    public function isPdf()
    {
        if ($this->file?->media->mime_type === 'application/pdf') {
            return true;
        }

        if ($this->remote_url && Str::endsWith($this->remote_url, '.pdf')) {
            return true;
        }

        return false;
    }
}
