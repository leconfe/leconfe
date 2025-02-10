<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Enums\RegistrationPaymentState;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Metable\Metable;

/**
 * @deprecated deprecated since version 1.2.0
 */
class RegistrationType extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory, Metable;

    public const LEVEL_PARTICIPANT = 1;

    public const LEVEL_AUTHOR = 2;

    protected $fillable = [
        'type',
        'cost',
        'quota',
        'level',
        'currency',
        'active',
        'order_column',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'opened_at' => 'date',
        'closed_at' => 'date',
    ];

    public function getRegisteredUserCount()
    {
        return $this->registration()->count();
    }

    public function getPaidParticipantCount()
    {
        return $this->registration()
            ->whereHas('registrationPayment', function ($query) {
                $query->where('state', RegistrationPaymentState::Paid->value);
            })
            ->count();
    }

    public function getQuotaLeft()
    {
        return $this->quota - $this->getPaidParticipantCount();
    }

    public function isQuotaFull()
    {
        return $this->getQuotaLeft() <= 0;
    }

    public function isOpen()
    {
        return (now()->greaterThanOrEqualTo($this->opened_at) && ! $this->isExpired()) && ! $this->isQuotaFull();
    }

    public function isExpired()
    {
        return now()->greaterThan($this->closed_at);
    }

    public function registration(): HasMany
    {
        return $this->hasMany(Registration::class, 'registration_type_id', 'id');
    }

    public static function getLevelOptions(): array
    {
        return [
            self::LEVEL_PARTICIPANT => 'Participant',
            self::LEVEL_AUTHOR => 'Author',
        ];
    }
}
