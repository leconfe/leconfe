<?php

namespace App\Models;

use App\Managers\PaymentManager;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Payment extends Model implements HasMedia
{
    use BelongsToConference, BelongsToScheduledConference, HasFactory, InteractsWithMedia, Metable;

    public const INVOICE_SENT_AT_META = 'invoice_sent_at';

    public const LEGACY_SUBMISSION_INVOICE_NOTIFIED_AT_META = 'submission_invoice_notified_at';

    protected $fillable = [
        'type',
        'model_type',
        'model_id',
        'payment_fee_id',
        'user_id',
        'amount',
        'currency',
        'invoice',
        'receipt',
        'payment_method',
        'expired_at',
        'paid_at',
        'scheduled_conference_id',
        'conference_id',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Payment $payment) {
            $scheduledConference = $payment->scheduledConference ?: app()->getCurrentScheduledConference();
            if ($scheduledConference?->isReceiptEnabled()) {
                $receiptNumber = $scheduledConference->getLatestReceiptNumber();

                $payment->update([
                    'receipt' => $scheduledConference->generateReceiptNumber($receiptNumber),
                ]);

                $scheduledConference->updateLatestReceiptNumber($receiptNumber + 1);
            }
        });
    }

    public function ensureInvoice(): bool
    {
        if ($this->invoice) {
            return false;
        }

        $scheduledConference = ScheduledConference::withoutGlobalScopes()
            ->find($this->scheduled_conference_id)
            ?: app()->getCurrentScheduledConference();

        if (! $scheduledConference?->isInvoiceEnabled()) {
            return false;
        }

        $this->setRelation('scheduledConference', $scheduledConference);

        $number = $scheduledConference->getLatestInvoiceNumber();

        $this->update([
            'invoice' => $scheduledConference->generateInvoiceNumber($number),
        ]);

        $scheduledConference->updateLatestInvoiceNumber($number + 1);

        return true;
    }

    public function hasInvoiceBeenSent(): bool
    {
        return filled($this->getInvoiceSentAtValue());
    }

    public function markInvoiceAsSent(): void
    {
        $this->setMeta(self::INVOICE_SENT_AT_META, now()->toDateTimeString());
    }

    public function getInvoiceSentAt(): ?Carbon
    {
        $sentAt = $this->getInvoiceSentAtValue();

        return $sentAt ? Carbon::parse($sentAt) : null;
    }

    protected function getInvoiceSentAtValue(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return $this->getMeta(self::INVOICE_SENT_AT_META)
            ?: $this->getMeta(self::LEGACY_SUBMISSION_INVOICE_NOTIFIED_AT_META);
    }

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
        if ($isPaid) {
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
        if (! $this->paid_at) {
            return false;
        }

        if (! $this->expired_at) {
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

    public function getPaymentDetailUrl(): string
    {
        $scheduledConference = $this->scheduledConference;

        return route('filament.scheduledConference.pages.payment-detail', [
            'conference' => $scheduledConference->conference->path,
            'serie' => $scheduledConference->path,
            'record' => $this,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->paid_at ? true : false;
    }

    public function getFormItemResponse(PaymentFormItem $item)
    {
        $responses = $this->getMeta('form_responses');

        return Arr::get($responses, $item->getKey());
    }
}
