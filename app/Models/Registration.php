<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $casts = [
        'paid_at' => 'datetime',
        'withdraw' => 'datetime',
    ];


    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => Str::squish($this->given_name . ' ' . $this->family_name),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function userSubmissions()
    {
        return $this->hasManyThrough(Submission::class, User::class, 'email', 'user_id', 'email', 'id');
    }

    public function getInfolistEntries(): array
    {
        return RegistrationForm::ordered()
            ->lazy()
            ->map(function (RegistrationForm $item){
                return $this->getTextEntry($item);
            })
            ->toArray();
    }

    public function getTextEntry($item): TextEntry
    {
        $formEntries = collect($this->getMeta('form_entries'));

        return TextEntry::make($item->getFieldId())
            ->label($item->label)
            ->getStateUsing(fn() => match ($item->type) {
                RegistrationForm::TYPE_SELECT, RegistrationForm::TYPE_RADIO, RegistrationForm::TYPE_CHECKBOX => $item->getMeta('options')[$formEntries->get($item->getKey())] ?? '-',
                default => $formEntries->get($item->getKey()) ?? '-'
            });
    }
}
