<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class RegistrationType extends Model implements Sortable
{
    use HasFactory, Metable, BelongsToScheduledConference, SortableTrait;

    protected $fillable = [
        'name',
        'currency',
        'cost',
        'limit',
        'order_column',
        'from',
        'to',
        'scheduled_conference_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'from' => 'date',
        'to' => 'date',
    ];
}
