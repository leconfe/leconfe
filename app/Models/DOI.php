<?php

namespace App\Models;

use App\Models\Enums\DOIStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Plank\Metable\Metable;

class DOI extends Model
{
    use HasFactory, Metable;

    protected $table = 'dois';

    protected $casts = [
        'status' => DOIStatus::class,
    ];

    protected $fillable = [
        'doi',
    ];

    public $timestamps = false;

    /**
     * Get the parent doi model
     */
    public function doiable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'doiable_type', 'doiable_id');
    }

    public function getUrl(): string
    {
        return 'https://doi.org/'.$this->doi;
    }
}
