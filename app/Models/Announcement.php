<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasTranslatableContent;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Announcement extends Model implements HasMedia
{
    use BelongsToScheduledConference, Cachable, HasTranslatableContent, InteractsWithMedia, Metable;

    protected $fillable = [
        'title',
        'expires_at',
    ];

    /**
     * Columns whose content can be translated.
     *
     * @var array<int, string>
     */
    protected array $translatableColumns = ['title'];

    /**
     * Meta keys whose content can be translated.
     *
     * @var array<int, string>
     */
    protected array $translatableMeta = ['summary', 'content'];

    protected static function booted(): void
    {
        parent::booted();
    }

    public function getUrl()
    {
        return route('livewirePageGroup.scheduledConference.pages.announcement-page', [
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
