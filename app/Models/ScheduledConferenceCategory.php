<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class ScheduledConferenceCategory extends Model
{
    use Sushi;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $schema = [
        'id' => 'integer',
        'name' => 'string',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    public function getRows()
    {
        $site = Site::getSite();

        return $site->getMeta('scheduled_conference_categories', []);
    }

    protected function sushiShouldCache()
    {
        return false;
    }
}
