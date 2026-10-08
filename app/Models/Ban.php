<?php

namespace App\Models;

use App\Support\UserBanScope;
use Illuminate\Database\Eloquent\Builder;
use Mchev\Banhammer\Models\Ban as BaseBan;

class Ban extends BaseBan
{
    protected $fillable = [
        'created_by_type', 'created_by_id', 'comment', 'ip', 'expired_at', 'metas',
        'conference_id', 'scheduled_conference_id',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'metas' => 'array',
        'conference_id' => 'integer',
        'scheduled_conference_id' => 'integer',
    ];

    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('expired_at')
            ->orWhere('expired_at', '>', now()));
    }

    public function scopeInScope(Builder $query, UserBanScope $scope): void
    {
        $query->where($scope->attributes());
    }

    public function scopeAffectingScope(Builder $query, UserBanScope $scope): void
    {
        $query->where(function (Builder $query) use ($scope) {
            $query->where(fn (Builder $query) => $query->inScope(new UserBanScope));

            if ($scope->conferenceId) {
                $query->orWhere(fn (Builder $query) => $query->inScope(new UserBanScope($scope->conferenceId)));
            }

            if ($scope->conferenceId && $scope->scheduledConferenceId) {
                $query->orWhere(fn (Builder $query) => $query->inScope($scope));
            }
        });
    }

    public function scope(): UserBanScope
    {
        return new UserBanScope($this->conference_id, $this->scheduled_conference_id);
    }

    public function isActive(): bool
    {
        return $this->expired_at === null || $this->expired_at->isFuture();
    }

    public function affectsScope(UserBanScope $scope): bool
    {
        return $this->scope()->isGlobal()
            || ($this->conference_id === $scope->conferenceId
                && $this->conference_id !== null
                && ($this->scheduled_conference_id === null
                    || $this->scheduled_conference_id === $scope->scheduledConferenceId));
    }
}
