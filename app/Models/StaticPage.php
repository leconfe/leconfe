<?php

namespace App\Models;

use App\Facades\StaticPageBlockFacade;
use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StaticPage extends Model implements HasMedia
{
    use BelongsToScheduledConference, Metable, Cachable, InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
    }

    public function getUrl(): string
    {
        $routeName = 'livewirePageGroup.website.pages.static-page';

        if (app()->getCurrentScheduledConferenceId()) {
            $routeName = 'livewirePageGroup.scheduledConference.pages.static-page';
        }

        return route($routeName, [
            'staticPage' => $this->slug,
        ]);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->resolveRouteBindingQuery($this, $value, $field)->isDefault(false);

        return $query->firstOrFail();
    }

    public function scopeIsDefault($query, bool $isDefault = true)
    {
        $query->where('is_default', $isDefault);
    }

    public function getBlocks()
    {
        return collect($this->getMeta('blocks'))
            ->map(function($block){
                return StaticPageBlockFacade::initBlock($block['type'], $block['data']);
            })
            ->filter();
    }

    public static function getHome()
    {
        return static::firstOrCreate(
            ['slug' => 'home'],
            ['title' => 'Home', 'is_default' => true],
        );
    }


    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Manipulations::FIT_CROP, 300, 300)
            ->nonQueued();
    }
}
