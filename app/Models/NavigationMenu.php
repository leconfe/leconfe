<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenu extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'conference_id',
        'scheduled_conference_id',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (NavigationMenu $navigationMenu) {
            $navigationMenu->items->each->delete();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class);
    }
}
