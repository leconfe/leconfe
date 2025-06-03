<?php

namespace App\Models;

use App\Interfaces\HasPayment;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\InteractsWithPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Participant extends Model implements HasMedia, HasPayment
{
    use BelongsToScheduledConference, HasFactory, InteractsWithMedia, InteractsWithPayment, Metable;

    protected $fillable = [
        'given_name',
        'family_name',
        'public_name',
        'email',
        'scheduled_conference_id',
        'conference_id',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Participant $record) {
            $record->uuid ??= Str::orderedUuid();
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->public_name ?? Str::squish($this->given_name.' '.$this->family_name);
            },
        );
    }
}
