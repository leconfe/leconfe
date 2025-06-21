<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;

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

    
}
