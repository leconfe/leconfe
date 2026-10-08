<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\ScheduledConference;

final class UserBanScope
{
    public function __construct(
        public readonly ?int $conferenceId = null,
        public readonly ?int $scheduledConferenceId = null,
    ) {}

    public static function current(): self
    {
        return new self(
            app()->getCurrentConferenceId() ?: null,
            app()->getCurrentScheduledConferenceId() ?: null,
        );
    }

    public function attributes(): array
    {
        return [
            'conference_id' => $this->conferenceId,
            'scheduled_conference_id' => $this->scheduledConferenceId,
        ];
    }

    public function isGlobal(): bool
    {
        return $this->conferenceId === null && $this->scheduledConferenceId === null;
    }

    public function isValid(): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        if (! $this->conferenceId) {
            return false;
        }

        if ($this->scheduledConferenceId) {
            return ScheduledConference::withoutGlobalScopes()
                ->whereKey($this->scheduledConferenceId)
                ->where('conference_id', $this->conferenceId)
                ->exists();
        }

        return Conference::withoutGlobalScopes()->whereKey($this->conferenceId)->exists();
    }

    public function label(): string
    {
        return $this->scheduledConferenceId
            ? __('general.scheduled_conference')
            : ($this->conferenceId ? __('general.conference') : __('ban.global'));
    }
}
