<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Illuminate\Support\Str;


class Registration extends Model
{
    use HasFactory, Metable, BelongsToScheduledConference;

    protected $fillable = [
        'email',
        'given_name',
        'family_name',
        'cost',
        'currency',
        'type',
    ];


    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::squish($this->given_name.' '.$this->family_name),
        );
    }
}
