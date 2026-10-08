<?php

namespace App\Models\Concerns;

use App\Models\Ban;
use App\Support\UserBanScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Mchev\Banhammer\Traits\Bannable;

trait HasScopedBans
{
    use Bannable;

    // Package middleware checks global bans; contextual access uses its own check.
    public function isBanned(): bool
    {
        return $this->activeBanForScope(new UserBanScope) !== null;
    }

    public function activeBanForScope(UserBanScope $scope): ?Ban
    {
        return $this->bans
            ->filter(fn (Ban $ban) => $ban->isActive() && $ban->affectsScope($scope))
            ->sortBy(fn (Ban $ban) => $ban->scope()->isGlobal() ? 0 : ($ban->scheduled_conference_id ? 2 : 1))
            ->first();
    }

    public function activeBanInCurrentContext(): ?Ban
    {
        return $this->activeBanForScope(UserBanScope::current());
    }

    public function isBannedInCurrentContext(): bool
    {
        return $this->activeBanInCurrentContext() !== null;
    }

    public function hasBanInCurrentScope(): bool
    {
        return $this->bans()->active()->inScope(UserBanScope::current())->exists();
    }

    public function banInCurrentContext(array $attributes = []): Ban
    {
        Gate::authorize('disable', $this);

        $ban = $this->bans()->create([
            ...Arr::only($attributes, ['comment', 'expired_at']),
            ...UserBanScope::current()->attributes(),
        ]);
        $this->unsetRelation('bans');

        return $ban;
    }

    public function unbanInCurrentContext(): void
    {
        Gate::authorize('enable', $this);

        $this->bans()->inScope(UserBanScope::current())->each(fn (Ban $ban) => $ban->delete());
        $this->unsetRelation('bans');
    }

    public function scopeBanned(Builder $query, bool $banned = true): void
    {
        $banned
            ? $query->whereHas('bans', fn (Builder $query) => $query->active()->inScope(new UserBanScope))
            : $this->scopeNotBanned($query);
    }

    public function scopeNotBanned(Builder $query): void
    {
        $this->scopeNotBannedInScope($query, new UserBanScope);
    }

    public function scopeNotBannedInScope(Builder $query, UserBanScope $scope): void
    {
        $query->whereDoesntHave('bans', fn (Builder $query) => $query->active()->affectingScope($scope));
    }
}
