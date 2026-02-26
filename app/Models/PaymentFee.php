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

    public function getAdditionalItems(): array
    {
        $additionalItems = $this->getMeta('additional_items', []);

        if (! is_array($additionalItems)) {
            return [];
        }

        return collect($additionalItems)
            ->values()
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $name = trim((string) data_get($item, 'name', ''));
                $amount = (float) data_get($item, 'amount', 0);

                if ($name === '' || $amount < 0) {
                    return null;
                }

                $description = data_get($item, 'description');
                $normalizedKey = md5($name.'|'.$amount.'|'.(string) $description);

                return [
                    'key' => 'addon_'.$normalizedKey,
                    'name' => $name,
                    'description' => $description,
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function getAdditionalItemOptions(): array
    {
        return collect($this->getAdditionalItems())
            ->mapWithKeys(function (array $item) {
                $formattedAmount = money($item['amount'], $this->currency, true)->formatWithoutZeroes();

                return [
                    $item['key'] => "{$item['name']} ({$formattedAmount})",
                ];
            })
            ->all();
    }

    public function resolveSelectedAdditionalItems(?array $selectedKeys = null): array
    {
        if (! is_array($selectedKeys) || $selectedKeys === []) {
            return [];
        }

        $selectedKeys = array_values(array_unique(array_map('strval', $selectedKeys)));

        return collect($this->getAdditionalItems())
            ->whereIn('key', $selectedKeys)
            ->values()
            ->all();
    }

    public function getAdditionalItemsTotal(?array $selectedKeys = null): float
    {
        return collect($this->resolveSelectedAdditionalItems($selectedKeys))
            ->sum('amount');
    }

    public function getAmountWithAdditionalItems(?array $selectedKeys = null): float
    {
        return (float) $this->amount + $this->getAdditionalItemsTotal($selectedKeys);
    }
}
