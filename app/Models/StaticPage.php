<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

class StaticPage extends Model
{
    use BelongsToConference, BelongsToScheduledConference, Metable;

    protected $fillable = [
        'title',
        'slug',
    ];

    protected static function booted(): void
    {
        parent::booted();
    }

    public function getUrl(): string
    {
        $routeName = 'livewirePageGroup.website.pages.static-page';

        if (app()->getCurrentConference()) {
            $routeName = 'livewirePageGroup.conference.pages.static-page';
        }

        if (app()->getCurrentScheduledConferenceId()) {
            $routeName = 'livewirePageGroup.scheduledConference.pages.static-page';
        }

        return route($routeName, [
            'staticPage' => $this->slug,
        ]);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->resolveRouteBindingQuery($this, $value, $field);

        if (! app()->getCurrentScheduledConferenceId()) {
            $query->where('scheduled_conference_id', 0);
        }

        return $query->firstOrFail();
    }
}
