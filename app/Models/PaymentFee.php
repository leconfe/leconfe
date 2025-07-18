<?php

namespace App\Models;

use App\Managers\PaymentManager;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class PaymentFee extends Model implements Sortable
{
    use BelongsToConference, BelongsToScheduledConference, HasFactory, Metable, SortableTrait;

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
        'scheduled_conference_id',
        'conference_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'opened_at' => 'date',
        'closed_at' => 'date',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (PaymentFee $paymentFee) {
            $paymentFee->load('payments');

            $paymentFee->payments->each->delete();
        });
    }

    public function scopeType($query, $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query, $active = true): Builder
    {
        return $query->where('is_active', $active);
    }

    public function formItems(): HasMany
    {
        return $this->hasMany(PaymentFeeFormItem::class);
    }

    public function getPaymentType()
    {
        return PaymentManager::get()->getPaymentTypeName($this->type);
    }

    public function getFormattedFee()
    {
        return money($this->amount, $this->currency, true)->formatWithoutZeroes();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'additional_items' => [],
        ];
    }
}
