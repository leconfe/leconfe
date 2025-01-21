<?php

namespace App\Models;

use App\Frontend\ScheduledConference\Pages\PaymentForm;
use App\Managers\PaymentManager;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Payment extends Model implements HasMedia
{
    use HasFactory, Metable, BelongsToScheduledConference, BelongsToConference, InteractsWithMedia;

    protected $fillable = [
        'type',
        'model_type',
        'model_id',
        'payment_fee_id',
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'expired_at',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'date',
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

    public function fee(): BelongsTo
    {
        return $this->belongsTo(PaymentFee::class, 'payment_fee_id');
    }

    public static function deleteExpired()
    {
        return self::query()
            ->whereNull('paid_at')
            ->whereNotNull('expired_date')
            ->where('expired_date', '<', now())
            ->delete();
    }

    public function scopePaid(Builder $query, $isPaid = true)
    {
        if($isPaid){
            return $query->whereNotNull('paid_at');
        }

        return $query->whereNull('paid_at');
    }

    public function scopeExpired(Builder $query, $isExpired = true)
    {
        $operator = $isExpired ? '<=' : '>';

        return $query->where('expired_date', $operator, now());
    }

    public function isExpired(): bool
    {
        if(!$this->paid_at){
            return false;
        }

        if (!$this->expired_at) {
            return false;
        }

        return now()->gte($this->expired_at);
    }

    public function getPaymentType()
    {
        return PaymentManager::get()->getPaymentTypeName($this->type);
    }

    public function getFormattedFee()
    {
        return money($this->amount, $this->currency, true)->formatWithoutZeroes();
    }

    public function isPaid(): bool
    {
        return $this->paid_at ? true : false;
    }

    public function getPaymentUrl()
    {
        return route(PaymentForm::getRouteName('scheduledConference'), [
            'payment' => $this->getKey(),
            'conference' => $this->conference->path,
            'serie' => $this->scheduledConference->path,
        ]);
    }
}
