<?php

namespace App\Models;

use App\Frontend\Conference\Pages\StaticPage as PagesStaticPage;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToSerie;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

class StaticPage extends Model
{
    use BelongsToConference, BelongsToSerie, Metable;

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
        $routeName = app()->getCurrentSerieId() ? PagesStaticPage::getRouteName('series') : PagesStaticPage::getRouteName('conference');

        return route($routeName, [
            'staticPage' => $this->slug,
        ]);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->resolveRouteBindingQuery($this, $value, $field);

        if(!app()->getCurrentSerieId()){
            $query->where('serie_id', 0);
        }
        
        return $query->firstOrFail();
    }
}
