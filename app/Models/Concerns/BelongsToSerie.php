<?php

namespace App\Models\Concerns;

use App\Models\Serie;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSerie
{
    public static function bootBelongsToSerie()
    {
        static::creating(function (Model $model) {
            if(App::getCurrentSerieId()){
                $model->serie_id ??= App::getCurrentSerieId();
            }
        });
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }
}
