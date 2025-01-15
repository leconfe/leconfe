<?php

namespace App\Models;

use App\Frontend\ScheduledConference\Pages\Payment;
use App\Managers\PaymentManager;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

class PaymentQueue extends Model
{
    use HasFactory, Metable, BelongsToConference, BelongsToScheduledConference;

    protected $fillable = [
        'type',
        'model_type',
        'model_id',
        'expired_at',
    ];

    protected $cast = [
        'expired_at' => 'date', 
    ];

    public static function deleteExpired()
    {
        return self::query()
            ->whereNotNull('expired_date')
            ->where('expired_date', '<', now())
            ->delete();
    }

    public function scopeExpired(Builder $query, $isExpired = true)
    {
        $operator = $isExpired ? '<=' : '>';

        return $query->where('expired_date', $operator, now());
    }

    public function isExpired() : bool
    {
        if(!$this->expired_at){
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
        return money($this->getMeta('amount'), $this->getMeta('currency'), true);
    }

    public function getPaymentUrl()
    {
        return route(Payment::getRouteName('scheduledConference'), [
            'paymentQueue' => $this->getKey(),
            'conference' => $this->conference->path,
            'serie' => $this->scheduledConference->path,
        ]);
    }
}
