<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasTranslatableContent;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

class StaticPage extends Model
{
    use BelongsToConference, BelongsToScheduledConference, HasTranslatableContent, Metable;

    protected $fillable = [
        'title',
        'slug',
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
    protected array $translatableMeta = ['content'];

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
