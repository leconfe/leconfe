<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory;

    protected $fillable = ['name', 'conference_id'];

    public function submissions()
    {
        return $this->morphedByMany(Submission::class, 'topicable');
    }
}
