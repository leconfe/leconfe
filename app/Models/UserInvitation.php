<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvitation extends Model
{
    use BelongsToConference, BelongsToScheduledConference;

    protected $fillable = [
        'email',
        'role_name',
        'conference_id',
        'scheduled_conference_id',
        'track_id',
        'token',
        'expires_at',
        'accepted_at',
        'status',
        'invited_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function getAcceptUrl(): string
    {
        if ($this->scheduledConference && $this->conference) {
            return route('livewirePageGroup.scheduledConference.pages.invitation-accept', [
                'conference' => $this->conference->path,
                'serie' => $this->scheduledConference->path,
                'token' => $this->token,
            ]);
        }

        if ($this->conference) {
            return route('livewirePageGroup.conference.pages.invitation-accept', [
                'conference' => $this->conference->path,
                'token' => $this->token,
            ]);
        }

        return route('livewirePageGroup.website.pages.invitation-accept', [
            'token' => $this->token,
        ]);
    }

    public function getRegisterUrl(): string
    {
        if ($this->scheduledConference && $this->conference) {
            return route('livewirePageGroup.scheduledConference.pages.invitation-register', [
                'conference' => $this->conference->path,
                'serie' => $this->scheduledConference->path,
                'token' => $this->token,
            ]);
        }

        if ($this->conference) {
            return route('livewirePageGroup.conference.pages.invitation-register', [
                'conference' => $this->conference->path,
                'token' => $this->token,
            ]);
        }

        return route('livewirePageGroup.website.pages.invitation-register', [
            'token' => $this->token,
        ]);
    }
}
