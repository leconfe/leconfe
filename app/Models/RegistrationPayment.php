<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Enums\RegistrationPaymentState;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Metable\Metable;

/**
 * @deprecated deprecated since version 1.2.0
 */
class RegistrationPayment extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory, Metable;

    protected $fillable = [
        'type',
        'name',
        'level',
        'description',
        'cost',
        'currency',
        'state',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'date',
    ];

    public static function getTypes()
    {
        return [
            RegistrationPaymentState::Paid => 'Paid',
            RegistrationPaymentState::Unpaid => 'Unpaid',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
