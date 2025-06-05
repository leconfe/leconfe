<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

class StaticPage extends Model
{
    use BelongsToScheduledConference, Metable, Cachable;

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

    public static function getHome()
    {
        return static::firstOrCreate(
            ['slug' => 'home'],
            ['title' => 'Home', 'is_default' => true],
        );
    }
}
