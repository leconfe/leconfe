<?php

namespace App\Models;

use Plank\Metable\Metable;
use Illuminate\Support\Str;
use App\Interfaces\HasPayment;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\InteractsWithPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Participant extends Model implements HasPayment, HasMedia
{
    use HasFactory, Metable, InteractsWithPayment, InteractsWithMedia, BelongsToScheduledConference, BelongsToConference;

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
