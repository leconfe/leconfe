<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Plank\Metable\Metable;

class PaymentCompleted extends Model
{
    use HasFactory, Metable, BelongsToScheduledConference, BelongsToConference;

     /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_completed';

    protected $fillable = [
        'type',
        'model_type',
        'model_id',
        'amount',
        'currency',
        'payment_method',
    ];

    public function scopeType($query, $type): Builder
    {
        return $query->where('type', $type);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
