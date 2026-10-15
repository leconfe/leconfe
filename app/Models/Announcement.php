<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Announcement extends Model implements HasMedia
{
    use BelongsToScheduledConference, Cachable, InteractsWithMedia, Metable;

    protected $fillable = [
        'title',
        'expires_at',
    ];

    protected static function booted(): void
    {
        parent::booted();
    }

    public function getUrl()
    {
        return route('livewirePageGroup.scheduledConference.pages.announcement-page', [
            'conference' => $this->scheduledConference->conference->path,
            'serie' => $this->scheduledConference->path,
            'announcement' => $this->id,
        ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('small')
            ->keepOriginalImageFormat()
            ->width(200);

        $this->addMediaConversion('thumb')
            ->keepOriginalImageFormat()
            ->width(400);

        $this->addMediaConversion('thumb-xl')
            ->keepOriginalImageFormat()
            ->width(600);
    }
}
