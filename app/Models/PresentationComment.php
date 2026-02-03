<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Metable\Metable;

class PresentationComment extends Model
{
    use HasFactory, Metable, Cachable;

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    protected $fillable = [
        'is_hidden',
        'user_id',
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
