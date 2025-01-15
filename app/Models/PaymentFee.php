<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class PaymentFee extends Model implements Sortable
{
    use HasFactory, Metable, BelongsToScheduledConference, BelongsToConference, SortableTrait;

    protected $fillable = [
        'name',
        'type',
        'amount',
        'currency',
        'is_active',
        'is_public',
        'limit',
        'order_column',
        'opened_at',
        'closed_at',
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'opened_at' => 'date',
        'closed_at' => 'date',
    ];

    public function scopeType($query, $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query, $active = true): Builder
    {
        return $query->where('is_active', $active);
    }
    
}
