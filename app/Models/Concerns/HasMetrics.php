<?php

namespace App\Models\Concerns;

use App\Models\Metric;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMetrics
{
    public function metrics() : MorphMany
    {
        return $this->morphMany(Metric::class, 'model');
    }
}
