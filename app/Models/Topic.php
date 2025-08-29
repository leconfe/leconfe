<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\LocalizedMetable;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use BelongsToConference, BelongsToScheduledConference, Cachable, LocalizedMetable, HasFactory;

    protected $fillable = ['name', 'conference_id'];

    public function submissions()
    {
        return $this->morphedByMany(Submission::class, 'topicable');
    }

    
}
